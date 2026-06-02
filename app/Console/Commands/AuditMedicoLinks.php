<?php

namespace App\Console\Commands;

use App\Models\Medico;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditMedicoLinks extends Command
{
    private const REL_DOCS_DIR = 'docs/migration';

    private const MEDICOS_JSON = 'medicos.json';

    private const ID_MAPPING_JSON = 'id-mapping.json';

    protected $signature = 'migration:audit-medico-links
                            {--fix : Aplica correções (remove pivots órfãos, zera/relinka medico_id inválido, relinks heurísticos, mislinks)}
                            {--regenerar-json : Regera docs/migration/medicos.json (e demais JSON) via migration:extrair-legado --sem-csv}
                            {--manual= : CSV opcional (;) medico_id;user_id;motivo — aplicado antes da heuristica}
                            {--merge-duplicates : Solo com --fix: funde médicos duplicados (nome_legado ou apelido+clínica) movendo dependências}';

    protected $description = 'Audita users.medico_id, pivot user_medico, backfill nome_legado, heurísticas (clínica direta/pivot, token, CRM) e mislinks óbvios.';

    /** @var \Illuminate\Support\Collection<int, User>|null */
    private $eligibleUsersMemo = null;

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $mergeDuplicates = (bool) $this->option('merge-duplicates');

        if ($mergeDuplicates && ! $fix) {
            $this->error('--merge-duplicates exige também --fix.');

            return 1;
        }

        if ($this->option('regenerar-json')) {
            $exit = Artisan::call('migration:extrair-legado', ['--sem-csv' => true]);
            if ($exit !== 0) {
                $this->error('migration:extrair-legado falhou (código '.$exit.').');

                return $exit;
            }
            $this->info('JSON regenerados em '.base_path(self::REL_DOCS_DIR).' (sem CSV).');
        }

        if (! $fix) {
            $this->warn('Modo dry-run: só simula correções (--fix altera médicos/users/pivots/merge-restante). nome_legado não é gravado até --fix.');
        }

        $this->newLine();
        $this->info('0) Backfill medicos.nome_legado desde docs/migration/medicos.json');
        $this->runBackfillNomeLegado(! $fix);

        $manual = trim((string) $this->option('manual'));
        if ($manual !== '') {
            $this->newLine();
            $this->info('Manual CSV: '.$manual);
            $this->applyManualAssignments($manual, $fix);
        }

        $this->newLine();
        $this->info('1) user_medico órfão (FK inexistentes)');
        $pivotBad = $this->countBadUserMedicoPivots();
        $this->line("   Linhas ruins: {$pivotBad}");
        if ($fix && $pivotBad > 0) {
            $this->info('   Removidas: '.$this->deleteBadUserMedicoPivots());
        }

        $this->newLine();
        $this->info('2) users.medico_id para médicos inexistentes');
        $orphanUsers = User::query()
            ->whereNotNull('medico_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('medicos')->whereColumn('medicos.id', 'users.medico_id'))
            ->get();
        foreach ($orphanUsers as $user) {
            $resolved = $this->resolveMedicoIdByUserName($user);
            if ($resolved === null) {
                $this->line("   - user #{$user->id} {$user->name}: medico_id={$user->medico_id} → ".($fix ? 'NULL' : '→ NULL'));
                if ($fix) {
                    $user->medico_id = null;
                    $user->saveQuietly();
                }
            } else {
                $this->line("   - user #{$user->id}: medico fake → médico #{$resolved} (nome)");
                if ($fix) {
                    $user->medico_id = $resolved;
                    $user->saveQuietly();
                }
            }
        }

        $this->newLine();
        $this->info('3) Mislink óbvio');
        $this->auditMislinkedUsers($fix);

        $this->newLine();
        $this->info('4) Médicos isolados sem user nem pivot válido → relink heurístico');
        foreach ($this->queryDisconnectedMedicos() as $medico) {
            $candidateId = $this->findUserIdForMedicoByNameHeuristic($medico);
            $nlTag = trim((string) ($medico->nome_legado ?? ''));
            if ($candidateId === null) {
                $this->line("   medico #{$medico->id} apelido=".($medico->apelido ?? 'null')." nome_legado=".($nlTag !== '' ? $nlTag : 'null').' crm='.($medico->crm ?? 'null').' sem candidato');

                continue;
            }
            $cand = User::find($candidateId);
            $this->line('   medico #'.$medico->id.' → user #'.$candidateId.' '.($cand?->name ?? '').($fix ? '' : ' (simulado)'));
            if (! $fix || ! $cand) {
                continue;
            }
            if ($cand->medico_id !== null && (int) $cand->medico_id !== (int) $medico->id && Medico::find($cand->medico_id)) {
                $this->warn('     Pulado — user já tem médico válido #' . $cand->medico_id);

                continue;
            }
            $cand->medico_id = $medico->id;
            if (! in_array($cand->role, [User::ROLE_MEDICO, User::ROLE_ADMIN], true)) {
                $cand->role = User::ROLE_MEDICO;
            }
            $cand->saveQuietly();
        }

        if ($mergeDuplicates) {
            $this->newLine();
            $this->info('5) Merge duplicados (opt-in)');
            $this->runDuplicateMerge();
        }

        $this->newLine();
        $this->info('Concluído.');

        return 0;
    }

    /** Preenche medicos.nome_legado vazio usando id-mapping.json, depois CPF/CRM quando inequívoco */
    private function runBackfillNomeLegado(bool $dryRun): void
    {
        $jsonPath = base_path(self::REL_DOCS_DIR . '/' . self::MEDICOS_JSON);
        if (! is_readable($jsonPath)) {
            $this->warn('   Sem medicos.json em ' . $jsonPath . ' — use migration:extrair-legado ou --regenerar-json.');

            return;
        }

        $mapMedicos = [];
        $mappingPath = base_path(self::REL_DOCS_DIR . '/' . self::ID_MAPPING_JSON);
        if (is_readable($mappingPath)) {
            $decoded = json_decode((string) file_get_contents($mappingPath), true);
            $mapMedicos = is_array($decoded) ? ($decoded['medicos'] ?? []) : [];
        }

        $items = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($items)) {
            $this->error('   medicos.json inválido.');

            return;
        }

        $updated = 0;
        $planned = 0;
        /** @var array<int, bool> */
        $handledMedIds = [];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $nl = isset($row['nome_legado']) ? trim((string) $row['nome_legado']) : '';
            if ($nl === '') {
                continue;
            }

            $candidateIds = [];

            $legadoId = $row['legado_id'] ?? null;
            $mapKeyLeg = match (true) {
                is_numeric($legadoId) => (string) ((int) $legadoId),
                is_string($legadoId) => $legadoId,
                default => null,
            };
            if ($mapKeyLeg !== null && isset($mapMedicos[$mapKeyLeg])) {
                $candidateIds[] = (int) $mapMedicos[$mapKeyLeg];
            }

            $cpfDig = $this->normalizeDigits($row['cpf'] ?? '');
            if (strlen($cpfDig) >= 11) {
                foreach (Medico::query()->whereNotNull('cpf')->cursor() as $m) {
                    if ($this->normalizeDigits($m->cpf) === $cpfDig) {
                        $candidateIds[] = (int) $m->id;
                    }
                }
            }

            $jsonCrm = $this->crmNormalizeKey(isset($row['crm']) ? (string) $row['crm'] : '');
            if ($jsonCrm !== '') {
                foreach (Medico::query()->whereNotNull('crm')->cursor() as $m) {
                    if ($this->crmNormalizeKey((string) $m->crm) === $jsonCrm) {
                        $candidateIds[] = (int) $m->id;
                    }
                }
            }

            $candidateIds = array_values(array_unique(array_filter($candidateIds, fn ($id) => $id > 0)));

            foreach ($candidateIds as $mid) {
                /** @var Medico|null $m */
                $m = Medico::find($mid);
                if (! $m) {
                    continue;
                }

                if (isset($handledMedIds[$mid])) {
                    continue;
                }

                if (trim((string) ($m->nome_legado ?? '')) !== '') {
                    continue;
                }

                $handledMedIds[$mid] = true;

                if ($dryRun) {
                    $planned++;
                    $this->line("   #{$m->id} ← {$nl} [dry-run]");
                    break;
                }
                $m->nome_legado = $nl;
                $m->saveQuietly();
                $updated++;
                $this->line("   #{$m->id} ← {$nl}");
                break;
            }
        }

        $this->info($dryRun ? "   nome_legado planejados: {$planned}" : "   nome_legado atualizados: {$updated}");
    }

    private function normalizeDigits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    private function crmNormalizeKey(?string $crm): string
    {
        $s = mb_strtolower(trim((string) $crm), 'UTF-8');

        return preg_replace('/\s+/u', ' ', $s);
    }

    private function applyManualAssignments(string $csvPath, bool $fix): void
    {
        $fullPath = $this->resolveCsvPath($csvPath);
        if ($fullPath === null) {
            $this->error('   CSV ilegível: ' . $csvPath);

            return;
        }

        $fh = fopen($fullPath, 'r');
        if ($fh === false) {
            $this->error('   Falha abrindo arquivo.');

            return;
        }

        $lineno = 0;
        while (($cells = fgetcsv($fh, 0, ';')) !== false) {
            $lineno++;
            $cells = array_map(static fn ($c) => trim((string) $c), $cells);
            if ($cells === []) {
                continue;
            }

            $isHeader =
                strtolower($cells[0] ?? '') === 'medico_id'
                && strtolower($cells[1] ?? '') === 'user_id';
            if ($lineno === 1 && $isHeader) {
                continue;
            }

            $mid = (int) ($cells[0] ?? 0);
            $uid = (int) ($cells[1] ?? 0);
            $motivo = $cells[2] ?? '';

            if ($mid <= 0 || $uid <= 0) {
                $this->warn("   Ignorando linha {$lineno} (IDs inválidos)");

                continue;
            }

            $user = User::find($uid);
            $doc = Medico::find($mid);
            if (! $user || ! $doc) {
                $this->warn("   Linha {$lineno}: registros ausentes (user {$uid}/medico {$mid})");

                continue;
            }

            $suffix = $motivo !== '' ? ' — ' . $motivo : '';
            $this->line('   user #' . $uid . ' → medico #' . $mid . $suffix);
            if ($fix) {
                $user->medico_id = $mid;
                $user->saveQuietly();
            }
        }

        fclose($fh);
        if (! $fix) {
            $this->line('   (simulação — use --fix)');
        }
    }

    private function resolveCsvPath(string $csvPath): ?string
    {
        $t = trim($csvPath);

        foreach ([
            base_path(str_replace('\\', '/', $t)),
            base_path(self::REL_DOCS_DIR.'/'.$t),
            $t,
        ] as $cand) {
            if ($cand !== '' && is_readable($cand)) {
                return $cand;
            }
        }

        return null;
    }

    private function countBadUserMedicoPivots(): int
    {
        return (int) DB::table('user_medico')
            ->where(fn ($w) => $w
                ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('users')->whereColumn('users.id', 'user_medico.user_id'))
                ->orWhereNotExists(fn ($s) => $s->selectRaw('1')->from('medicos')->whereColumn('medicos.id', 'user_medico.medico_id')))
            ->count();
    }

    private function deleteBadUserMedicoPivots(): int
    {
        return DB::table('user_medico')
            ->where(fn ($w) => $w
                ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('users')->whereColumn('users.id', 'user_medico.user_id'))
                ->orWhereNotExists(fn ($s) => $s->selectRaw('1')->from('medicos')->whereColumn('medicos.id', 'user_medico.medico_id')))
            ->delete();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Medico>
     */
    private function queryDisconnectedMedicos()
    {
        return Medico::query()
            ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('users')->whereColumn('users.medico_id', 'medicos.id'))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('user_medico')->join('users', 'users.id', '=', 'user_medico.user_id')
                    ->whereColumn('user_medico.medico_id', 'medicos.id');
            })
            ->get();
    }

    private function auditMislinkedUsers(bool $fix): void
    {
        foreach (User::query()->whereNotNull('medico_id')->cursor() as $user) {
            $curId = (int) $user->medico_id;

            $medico = Medico::find($curId);
            if (! $medico || $this->userTextsAlignWithMedico($user, $medico)) {
                continue;
            }

            $curRecipes = $this->receitasCountForMedico($curId);
            $alts = [];
            foreach (Medico::query()->cursor() as $candidate) {
                $cid = (int) $candidate->id;
                if ($cid === $curId) {
                    continue;
                }
                if (! $this->userTextsAlignWithMedico($user, $candidate)) {
                    continue;
                }
                $rcp = $this->receitasCountForMedico($cid);

                if ($curRecipes === 0 && $rcp > 0) {
                    $alts[] = $cid;
                }
            }

            $alts = array_values(array_unique($alts));

            if (count($alts) === 1) {
                $replacement = (int) $alts[0];
                $this->line("   {$user->name}: medico #{$curId} sem receitas; realocar → #{$replacement}");
                if ($fix) {
                    $user->medico_id = $replacement;
                    $user->saveQuietly();
                }

                continue;
            }

            if (count($alts) > 1) {
                $this->warn("   Mislink {$user->name}: alternativas ".implode(',', $alts));
            }
        }
    }

    private function receitasCountForMedico(int $id): int
    {
        return (int) DB::table('receitas')->where('medico_id', $id)->count();
    }

    private function pacientesCountForMedico(int $id): int
    {
        return (int) DB::table('pacientes')->where('medico_id', $id)->count();
    }

    private function resolveMedicoIdByUserName(User $user): ?int
    {
        $key = $this->normalizePersonName($user->name);
        if ($key === '') {
            return null;
        }

        $ids = $this->medicoIdsMatchingNormalizedPersonKey($key);

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * @return list<int>
     */
    private function medicoIdsMatchingNormalizedPersonKey(string $key): array
    {
        if ($key === '') {
            return [];
        }

        $found = [];

        foreach (Medico::query()->cursor() as $m) {
            foreach ($this->normalizedKeysFromMedico($m) as $mk) {
                if ($mk === $key) {
                    $found[] = (int) $m->id;

                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<string>
     */
    private function normalizedKeysFromMedico(Medico $medico): array
    {
        $vals = [];

        foreach ($this->rawPersonTextsForMedico($medico) as $raw) {
            $k = $this->normalizePersonName($raw);
            if ($k !== '') {
                $vals[$k] = true;
            }
        }

        return array_keys($vals);
    }

    /**
     * @return list<string>
     */
    private function rawPersonTextsForMedico(Medico $medico): array
    {
        $chunks = [];

        foreach ([(string) ($medico->nome_legado ?? ''), (string) ($medico->apelido ?? '')] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        foreach ($this->clinicaNomeStringsForMedico((int) $medico->id, $medico->clinica_id) as $nome) {
            $nome = trim($nome);
            if ($nome !== '') {
                $chunks[] = $nome;
            }
        }

        return array_values(array_unique($chunks));
    }

    /**
     * @return list<string>
     */
    private function clinicaNomeStringsForMedico(int $medicoId, mixed $fkClinicId): array
    {
        $labels = [];

        $pivot = DB::table('clinica_medico')
            ->join('clinicas', 'clinicas.id', '=', 'clinica_medico.clinica_id')
            ->where('clinica_medico.medico_id', $medicoId)
            ->pluck('clinicas.nome')
            ->all();

        foreach ($pivot as $p) {
            $labels[] = (string) $p;
        }

        if ($fkClinicId) {
            $nome = DB::table('clinicas')->where('id', $fkClinicId)->value('nome');
            if ($nome) {
                $labels[] = (string) $nome;
            }
        }

        return $labels;
    }

    private function userTextsAlignWithMedico(User $user, Medico $medico): bool
    {
        $uname = $this->normalizePersonName($user->name);
        if ($uname === '') {
            return false;
        }

        foreach ($this->normalizedKeysFromMedico($medico) as $nk) {
            if ($nk === $uname) {
                return true;
            }
        }

        if ($this->tokenOverlapCountBetweenMedicoAndUser($medico, $user->name) >= 2) {
            return true;
        }

        $seq = $this->meaningfulDigitsFromCrm((string) ($medico->crm ?? ''));

        return $seq !== '' && $this->userNameContainsDigitRun($seq, $user->name ?? '');
    }

    private function findUserIdForMedicoByNameHeuristic(Medico $medico): ?int
    {
        $exact = $this->candidateUserIdsExact($medico);
        if (count($exact) === 1) {
            return (int) $exact[array_key_first($exact)];
        }

        if (count($exact) > 1) {
            $narrow = $this->narrowUserIdsViaTokenOverlap($exact, $medico);
            if (count($narrow) === 1) {
                return (int) $narrow[array_key_first($narrow)];
            }
        }

        $tokenHits = [];
        foreach ($this->eligibleUsers() as $u) {
            if ($this->tokenOverlapCountBetweenMedicoAndUser($medico, $u->name) >= 2) {
                $tokenHits[] = (int) $u->id;
            }
        }

        $tokenHits = array_values(array_unique($tokenHits));
        if (count($tokenHits) === 1) {
            return (int) $tokenHits[0];
        }

        $crmHits = [];
        $seq = $this->meaningfulDigitsFromCrm((string) ($medico->crm ?? ''));
        if ($seq !== '') {
            foreach ($this->eligibleUsers() as $u) {
                if ($this->userNameContainsDigitRun($seq, $u->name ?? '')) {
                    $crmHits[] = (int) $u->id;
                }
            }
        }

        $crmHits = array_values(array_unique($crmHits));

        return count($crmHits) === 1 ? (int) $crmHits[0] : null;
    }

    /**
     * @return list<int>
     */
    private function candidateUserIdsExact(Medico $medico): array
    {
        $keys = $this->normalizedKeysFromMedico($medico);
        if ($keys === []) {
            return [];
        }

        $hits = [];
        foreach ($this->eligibleUsers() as $u) {
            $nk = $this->normalizePersonName($u->name);
            if ($nk !== '' && in_array($nk, $keys, true)) {
                $hits[] = (int) $u->id;
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function narrowUserIdsViaTokenOverlap(array $userIds, Medico $medico): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $refined = [];
        foreach ($userIds as $uid) {
            $u = User::find($uid);
            if (! $u) {
                continue;
            }
            if ($this->tokenOverlapCountBetweenMedicoAndUser($medico, $u->name) >= 2) {
                $refined[] = $uid;
            }
        }

        return array_values(array_unique($refined));
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function eligibleUsers()
    {
        if ($this->eligibleUsersMemo === null) {
            $this->eligibleUsersMemo = User::query()
                ->whereIn('role', [User::ROLE_MEDICO, User::ROLE_ADMIN])
                ->get();
        }

        return $this->eligibleUsersMemo;
    }

    private function tokenOverlapCountBetweenMedicoAndUser(Medico $medico, ?string $userName): int
    {
        $medTokens = $this->bundleTokenAssocForMedico($medico);
        if ($medTokens === []) {
            return 0;
        }

        $userTokens = [];
        foreach ($this->meaningfulTokens($userName ?? '') as $t) {
            $userTokens[$t] = true;
        }

        $count = 0;
        foreach ($medTokens as $tok => $_on) {
            if (isset($userTokens[$tok])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, bool>
     */
    private function bundleTokenAssocForMedico(Medico $medico): array
    {
        $bag = [];
        foreach ($this->rawPersonTextsForMedico($medico) as $txt) {
            foreach ($this->meaningfulTokens($txt) as $tok) {
                $bag[$tok] = true;
            }
        }

        return $bag;
    }

    /**
     * @return list<string>
     */
    private function meaningfulTokens(?string $text): array
    {
        $text = $this->stripDoctorPrefix((string) $text);
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)), 'UTF-8');
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p, 'UTF-8') >= 4) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    private function meaningfulDigitsFromCrm(string $crm): string
    {
        $digits = preg_replace('/\D+/', '', $crm);

        return strlen($digits) >= 4 ? $digits : '';
    }

    private function userNameContainsDigitRun(string $digitRun, string $name): bool
    {
        $hay = preg_replace('/\D+/', '', $name);

        return $hay !== '' && str_contains($hay, $digitRun);
    }

    private function stripDoctorPrefix(string $text): string
    {
        $text = preg_replace('/^\s*(dra\.|dr\.|doutora\.?|doutor\.?|prof\.?a?\.?)\s+/iu', '', trim($text));

        return trim((string) $text);
    }

    private function normalizePersonName(?string $name): string
    {
        $clean = mb_strtolower($this->stripDoctorPrefix(trim((string) $name)), 'UTF-8');
        $clean = preg_replace('/\s+/u', ' ', $clean);

        return trim((string) $clean);
    }

    private function runDuplicateMerge(): void
    {
        $mergedAway = [];

        $groupsNome = $this->groupMedicoIdsByNormalizedNomeLegado();
        foreach ($groupsNome as $ids) {
            $this->processDuplicateBucket($ids, 'nome_legado', $mergedAway);
        }

        $groupsClin = $this->groupMedicoIdsByApelidoClinic();
        foreach ($groupsClin as $ids) {
            $this->processDuplicateBucket($ids, 'apelido+clínica', $mergedAway);
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, true>  $mergedAway
     */
    private function processDuplicateBucket(array $ids, string $motivoLabel, array &$mergedAway): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (count($ids) < 2) {
            return;
        }

        $rows = Medico::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($mergedAway as $gone => $_flag) {
            $rows->forget($gone);
        }

        $working = [];
        foreach ($rows as $m) {
            $working[] = $m;
        }

        usort($working, fn (Medico $a, Medico $b) => $this->duplicateWeightScore($b) <=> $this->duplicateWeightScore($a));

        /** @var Medico $keeper */
        $keeper = array_shift($working);
        foreach ($working as $other) {
            if (isset($mergedAway[(int) $other->id])) {
                continue;
            }

            $oRec = $this->receitasCountForMedico((int) $other->id);
            $oPac = $this->pacientesCountForMedico((int) $other->id);
            $kWeight = $this->duplicateWeightScore($keeper);

            if ($oRec > 0 || $oPac > 0 || $kWeight === 0) {
                continue;
            }

            $this->info("   merge {$motivoLabel}: medico #{$other->id} → #{$keeper->id}");

            try {
                $this->mergeMedicoIntoKeeper((int) $keeper->id, (int) $other->id);
                $mergedAway[(int) $other->id] = true;

            } catch (\Throwable $e) {
                $this->warn("   falha ao fundir #{$other->id}: {$e->getMessage()}");

            }

        }

    }

    private function duplicateWeightScore(Medico $m): int
    {
        $id = (int) $m->id;

        return $this->receitasCountForMedico($id) * 1_000_000
            + $this->pacientesCountForMedico($id) * 1_000
            + (User::query()->where('medico_id', $id)->count());

    }

    /**
     * @return list<list<int>>
     */
    private function groupMedicoIdsByNormalizedNomeLegado(): array
    {
        $map = [];
        foreach (Medico::query()->cursor() as $m) {
            $nl = trim((string) ($m->nome_legado ?? ''));
            if ($nl === '') {
                continue;
            }
            $nk = mb_strtolower($nl, 'UTF-8');
            $map[$nk][] = (int) $m->id;
        }

        $groups = [];

        foreach ($map as $_k => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) >= 2) {
                $groups[] = $ids;
            }
        }

        return $groups;
    }

    /**
     * @return list<list<int>>
     */
    private function groupMedicoIdsByApelidoClinic(): array
    {
        $map = [];

        foreach (Medico::query()->cursor() as $m) {
            $ap = trim((string) ($m->apelido ?? ''));

            if ($ap === '' || $m->clinica_id === null) {
                continue;

            }

            $k = $this->normalizePersonName($ap).'|'.$m->clinica_id;

            $map[$k][] = (int) $m->id;
        }

        $groups = [];
        foreach ($map as $_label => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) >= 2) {
                $groups[] = $ids;
            }
        }

        return $groups;
    }

    private function mergeMedicoIntoKeeper(int $keeperId, int $emptyId): void
    {
        if ($keeperId === $emptyId) {
            return;
        }

        DB::transaction(function () use ($keeperId, $emptyId): void {
            DB::table('receitas')->where('medico_id', $emptyId)->update(['medico_id' => $keeperId]);
            DB::table('pacientes')->where('medico_id', $emptyId)->update(['medico_id' => $keeperId]);
            DB::table('users')->where('medico_id', $emptyId)->update(['medico_id' => $keeperId]);

            foreach (DB::table('user_medico')->where('medico_id', $emptyId)->get() as $row) {
                $duplicate = DB::table('user_medico')
                    ->where('medico_id', $keeperId)
                    ->where('user_id', $row->user_id)
                    ->exists();

                if ($duplicate) {
                    DB::table('user_medico')->where('id', $row->id)->delete();
                } else {
                    DB::table('user_medico')->where('id', $row->id)->update(['medico_id' => $keeperId]);
                }
            }

            foreach (DB::table('clinica_medico')->where('medico_id', $emptyId)->get() as $crow) {
                $duplicate = DB::table('clinica_medico')
                    ->where('medico_id', $keeperId)
                    ->where('clinica_id', $crow->clinica_id)
                    ->exists();

                if ($duplicate) {
                    DB::table('clinica_medico')->where('id', $crow->id)->delete();
                } else {
                    DB::table('clinica_medico')->where('id', $crow->id)->update(['medico_id' => $keeperId]);
                }
            }

            DB::table('medico_enderecos')->where('medico_id', $emptyId)->update(['medico_id' => $keeperId]);

            if (Schema::hasTable('atendimentos_callcenter')) {
                DB::table('atendimentos_callcenter')->where('medico_id', $emptyId)->update(['medico_id' => $keeperId]);
            }

            DB::table('medicos')->where('id', $emptyId)->delete();
        });
    }
}
