<?php

namespace App\Services\Migration;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\PacienteTelefone;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Services\PacienteVinculoService;
use App\Support\LegadoCodigoProdutoMapeamento;
use App\Support\LegadoEmail;
use App\Support\LegadoProdutoResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegadoIncrementalImporter
{
    /** @var list<string> */
    private const PACIENTE_SHARED_FIELDS = [
        'nome', 'data_nascimento', 'sexo', 'fototipo', 'rg',
        'celular', 'telefone1', 'email1', 'email2',
        'tipo_endereco', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
    ];

    public function __construct(
        private readonly LegadoMedicoResolver $medicoResolver,
        private readonly PacienteVinculoService $vinculoService,
    ) {}

    public function sqlDirectory(): string
    {
        return (string) config('legado.sql_path', base_path('docs/clinicaweb/database'));
    }

    /**
     * @return list<array{path:string,name:string,size:int,mtime:int}>
     */
    public function listSqlDumps(): array
    {
        $dir = $this->sqlDirectory();
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (File::files($dir) as $file) {
            if (strtolower($file->getExtension()) !== 'sql') {
                continue;
            }
            $out[] = [
                'path' => $file->getPathname(),
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'mtime' => $file->getMTime(),
            ];
        }

        usort($out, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $out;
    }

    /**
     * @param  list<int|string>  $medicoTokens  ids CLW3, CPF, CRM ou e-mail
     * @return array<string, mixed>
     */
    public function run(string $sqlPath, array $medicoTokens, bool $dryRun = true, ?string $workDir = null): array
    {
        @ini_set('memory_limit', '512M');

        if (! is_file($sqlPath)) {
            throw new \InvalidArgumentException("SQL não encontrado: {$sqlPath}");
        }

        $resolved = $this->medicoResolver->resolveMany($medicoTokens);
        if ($resolved['unresolved'] !== []) {
            throw new \InvalidArgumentException(
                'Médicos não encontrados no CLW3: '.implode(', ', $resolved['unresolved'])
            );
        }

        $workDir = $workDir ?: $this->prepareWorkDir($sqlPath);
        $extractDir = $workDir.'/extract';
        $this->ensureExtracted($sqlPath, $extractDir);

        $legadoMedicos = $this->loadJson($extractDir.'/medicos.json');
        $legadoPacientes = $this->loadJson($extractDir.'/pacientes.json');
        $legadoReceitas = $this->loadJson($extractDir.'/receitas.json');

        $mappings = [];
        $legadoMedicoIds = [];
        foreach ($resolved['resolved'] as $row) {
            /** @var Medico $medico */
            $medico = $row['medico'];
            $match = $this->medicoResolver->matchLegado($medico, $legadoMedicos);
            if ($match === null) {
                $mappings[] = [
                    'clw3_id' => $medico->id,
                    'clw3_nome' => $medico->nome_legado ?: $medico->apelido ?: $medico->nome,
                    'clw3_crm' => $medico->crm,
                    'clw3_cpf' => $medico->cpf,
                    'token' => $row['token'],
                    'token_match' => $row['match_by'],
                    'legado' => null,
                    'ok' => false,
                    'erro' => 'Sem correspondente no dump CLW2',
                ];

                continue;
            }
            $legadoId = (int) $match['legado']['legado_id'];
            $legadoMedicoIds[] = $legadoId;
            $mappings[] = [
                'clw3_id' => $medico->id,
                'clw3_nome' => $medico->nome_legado ?: $medico->apelido ?: $medico->nome,
                'clw3_crm' => $medico->crm,
                'clw3_cpf' => $medico->cpf,
                'token' => $row['token'],
                'token_match' => $row['match_by'],
                'legado_id' => $legadoId,
                'legado_nome' => $match['legado']['nome_legado'] ?? $match['legado']['apelido'] ?? null,
                'legado_crm' => $match['legado']['crm'] ?? null,
                'legado_cpf' => $match['legado']['cpf'] ?? null,
                'dump_match' => $match['match_by'],
                'ok' => true,
            ];
        }

        $legadoMedicoIds = array_values(array_unique($legadoMedicoIds));
        if ($legadoMedicoIds === []) {
            return $this->finishReport($workDir, $dryRun, $sqlPath, $mappings, [
                'erro' => 'Nenhum médico casou com o dump CLW2',
            ], []);
        }

        $clw3ByLegadoMedico = [];
        foreach ($mappings as $m) {
            if (! empty($m['ok']) && isset($m['legado_id'])) {
                $clw3ByLegadoMedico[(int) $m['legado_id']] = (int) $m['clw3_id'];
            }
        }

        $pacienteIdsComReceita = [];
        foreach ($legadoReceitas as $r) {
            $mid = isset($r['legado_medico_id']) ? (int) $r['legado_medico_id'] : null;
            if ($mid !== null && in_array($mid, $legadoMedicoIds, true)) {
                if ($r['legado_paciente_id'] !== null) {
                    $pacienteIdsComReceita[(int) $r['legado_paciente_id']] = true;
                }
            }
        }

        $pacientesFiltrados = array_values(array_filter($legadoPacientes, function ($p) use ($legadoMedicoIds, $pacienteIdsComReceita) {
            $mid = isset($p['legado_medico_id']) ? (int) $p['legado_medico_id'] : null;
            $pid = (int) ($p['legado_id'] ?? 0);

            return ($mid !== null && in_array($mid, $legadoMedicoIds, true))
                || isset($pacienteIdsComReceita[$pid]);
        }));

        $receitasFiltradas = array_values(array_filter($legadoReceitas, function ($r) use ($legadoMedicoIds) {
            $mid = isset($r['legado_medico_id']) ? (int) $r['legado_medico_id'] : null;

            return $mid !== null && in_array($mid, $legadoMedicoIds, true);
        }));

        $mapeamentoCodigos = LegadoCodigoProdutoMapeamento::fromMarkdownFile(
            LegadoCodigoProdutoMapeamento::defaultFilePath()
        );
        $produtoCache = Produto::all()->keyBy('codigo');

        $stats = [
            'pacientes_novos' => 0,
            'pacientes_merge' => 0,
            'pacientes_skip' => 0,
            'pacientes_needs_review' => 0,
            'receitas_novas' => 0,
            'receitas_atualizadas' => 0,
            'receitas_skip' => 0,
            'receitas_conflito' => 0,
            'receitas_canceladas_espelhadas' => 0,
            'itens_sem_produto' => 0,
            'renumeracoes' => 0,
        ];
        $signals = [];
        $pacienteIdMap = []; // legado_id => clw3 id
        $pacientesTocados = [];
        $changesPacientes = [];
        $changesReceitas = [];

        $runner = function () use (
            $pacientesFiltrados,
            $receitasFiltradas,
            $clw3ByLegadoMedico,
            $mapeamentoCodigos,
            $produtoCache,
            &$stats,
            &$signals,
            &$pacienteIdMap,
            &$pacientesTocados,
            &$changesPacientes,
            &$changesReceitas,
            $dryRun
        ) {
            foreach ($pacientesFiltrados as $item) {
                $result = $this->upsertPaciente($item, $clw3ByLegadoMedico);
                $stats[$result['stat']]++;
                if (! empty($result['signal'])) {
                    $signals[] = $result['signal'];
                }
                if (! empty($result['change'])) {
                    $changesPacientes[] = $result['change'];
                }
                if (! empty($result['paciente_id'])) {
                    $pacienteIdMap[(int) $item['legado_id']] = (int) $result['paciente_id'];
                    if (($result['stat'] ?? '') !== 'pacientes_skip') {
                        $pacientesTocados[(int) $result['paciente_id']] = true;
                    }
                }
            }

            foreach ($receitasFiltradas as $item) {
                $legadoPacienteId = isset($item['legado_paciente_id']) ? (int) $item['legado_paciente_id'] : null;
                $pacienteId = $legadoPacienteId ? ($pacienteIdMap[$legadoPacienteId] ?? null) : null;
                if (! $pacienteId) {
                    if ($legadoPacienteId) {
                        $pacienteId = $this->findPacienteIdFromPriorImport($legadoPacienteId, $item);
                    }
                }
                $legadoMedicoId = isset($item['legado_medico_id']) ? (int) $item['legado_medico_id'] : null;
                $medicoId = $legadoMedicoId ? ($clw3ByLegadoMedico[$legadoMedicoId] ?? null) : null;

                if (! $pacienteId || ! $medicoId) {
                    $stats['receitas_skip']++;
                    $signals[] = [
                        'tipo' => 'receita_sem_paciente_ou_medico',
                        'legado_receita_id' => $item['legado_id'] ?? null,
                        'legado_paciente_id' => $legadoPacienteId,
                        'legado_medico_id' => $legadoMedicoId,
                    ];

                    continue;
                }

                $pacienteIdMap[$legadoPacienteId] = $pacienteId;

                $result = $this->upsertReceita(
                    $item,
                    $pacienteId,
                    $medicoId,
                    $mapeamentoCodigos,
                    $produtoCache
                );
                $stats[$result['stat']]++;
                if (! empty($result['itens_sem_produto'])) {
                    $stats['itens_sem_produto'] += $result['itens_sem_produto'];
                }
                if (! empty($result['signal'])) {
                    $signals[] = $result['signal'];
                }
                if (! empty($result['change'])) {
                    $changesReceitas[] = $result['change'];
                }
                if (($result['stat'] ?? '') !== 'receitas_skip') {
                    $pacientesTocados[$pacienteId] = true;
                }
            }

            if (! $dryRun) {
                foreach (array_keys($pacientesTocados) as $pid) {
                    if ($this->pacientePrecisaRenumerar((int) $pid)) {
                        Receita::renumerarPorData((int) $pid);
                        $stats['renumeracoes']++;
                    }
                }
            } else {
                foreach (array_keys($pacientesTocados) as $pid) {
                    if ($this->pacientePrecisaRenumerar((int) $pid)) {
                        $stats['renumeracoes']++;
                        $signals[] = [
                            'tipo' => 'renumeracao_prevista',
                            'paciente_id' => $pid,
                        ];
                    }
                }
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $runner();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction(function () use ($runner) {
                $runner();
            });
        }

        return $this->finishReport($workDir, $dryRun, $sqlPath, $mappings, $stats, $signals, [
            'pacientes_filtrados' => count($pacientesFiltrados),
            'receitas_filtradas' => count($receitasFiltradas),
            'legado_medico_ids' => $legadoMedicoIds,
            'report_hash' => basename($workDir),
            'changes' => [
                'pacientes' => $changesPacientes,
                'receitas' => $changesReceitas,
            ],
        ]);
    }

    /**
     * Prévia de mapeamento sem importar (parse extract + match).
     *
     * @param  list<int>  $clw3MedicoIds
     * @return array<string, mixed>
     */
    public function previewMapping(string $sqlPath, array $clw3MedicoIds): array
    {
        $workDir = $this->prepareWorkDir($sqlPath);
        $extractDir = $workDir.'/extract';
        $this->ensureExtracted($sqlPath, $extractDir);
        $legadoMedicos = $this->loadJson($extractDir.'/medicos.json');

        $mappings = [];
        foreach ($clw3MedicoIds as $id) {
            $medico = Medico::find((int) $id);
            if (! $medico) {
                $mappings[] = ['clw3_id' => (int) $id, 'ok' => false, 'erro' => 'Médico CLW3 inexistente'];

                continue;
            }
            $match = $this->medicoResolver->matchLegado($medico, $legadoMedicos);
            if ($match === null) {
                $mappings[] = [
                    'clw3_id' => $medico->id,
                    'clw3_nome' => $medico->nome_legado ?: $medico->apelido,
                    'clw3_crm' => $medico->crm,
                    'clw3_cpf' => $medico->cpf,
                    'ok' => false,
                    'erro' => 'Sem correspondente no dump',
                ];

                continue;
            }
            $mappings[] = [
                'clw3_id' => $medico->id,
                'clw3_nome' => $medico->nome_legado ?: $medico->apelido,
                'clw3_crm' => $medico->crm,
                'clw3_cpf' => $medico->cpf,
                'legado_id' => $match['legado']['legado_id'],
                'legado_nome' => $match['legado']['nome_legado'] ?? null,
                'legado_crm' => $match['legado']['crm'] ?? null,
                'legado_cpf' => $match['legado']['cpf'] ?? null,
                'dump_match' => $match['match_by'],
                'ok' => true,
            ];
        }

        return [
            'work_dir' => $workDir,
            'sql' => $sqlPath,
            'mappings' => $mappings,
        ];
    }

    private function prepareWorkDir(string $sqlPath): string
    {
        $hash = md5_file($sqlPath) ?: sha1($sqlPath);
        $dir = storage_path('app/imports/'.$hash);
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    private function ensureExtracted(string $sqlPath, string $extractDir): void
    {
        File::ensureDirectoryExists($extractDir);
        $marker = $extractDir.'/receitas.json';
        $meta = $extractDir.'/.sql-source';
        $sourceOk = is_file($meta) && trim((string) file_get_contents($meta)) === $sqlPath;

        if (is_file($marker) && $sourceOk) {
            // Reextrair se JSON antigo não tem dta_ult_alteracao (best-effort)
            $sample = json_decode((string) file_get_contents($extractDir.'/pacientes.json'), true);
            if (is_array($sample) && $sample !== [] && array_key_exists('dta_ult_alteracao', $sample[0])) {
                return;
            }
        }

        $relSql = $this->pathRelativeToBase($sqlPath);
        $relOut = $this->pathRelativeToBase($extractDir);

        $exit = Artisan::call('migration:extrair-legado', [
            '--sql' => $relSql,
            '--output' => $relOut,
            '--sem-csv' => true,
            '--incluir-receitas-canceladas' => true,
        ]);

        if ($exit !== 0 || ! is_file($marker)) {
            throw new \RuntimeException('Falha ao extrair dump CLW2 (exit='.$exit.'): '.Artisan::output());
        }

        file_put_contents($meta, $sqlPath);
    }

    private function pathRelativeToBase(string $absolute): string
    {
        $base = rtrim(base_path(), '/').'/';
        if (str_starts_with($absolute, $base)) {
            return substr($absolute, strlen($base));
        }

        return $absolute;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadJson(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("JSON ausente: {$path}");
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            throw new \RuntimeException("JSON inválido: {$path}");
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, int>  $clw3ByLegadoMedico
     * @return array{stat:string,paciente_id:?int,signal:?array,change:?array}
     */
    private function upsertPaciente(array $item, array $clw3ByLegadoMedico): array
    {
        $legadoMedicoId = isset($item['legado_medico_id']) ? (int) $item['legado_medico_id'] : null;
        $medicoId = $legadoMedicoId ? ($clw3ByLegadoMedico[$legadoMedicoId] ?? null) : null;

        $existente = $this->findPaciente($item, $medicoId);
        $signal = null;
        $matchBy = 'cpf_ou_codigo';

        if ($existente === null) {
            $porNome = $this->findPacientePorNome($item, $medicoId);
            if ($porNome) {
                $existente = $porNome;
                $matchBy = 'nome_medico'.(! empty($item['data_nascimento']) ? '_dn' : '');
                if (empty($item['cpf'])) {
                    $signal = [
                        'tipo' => 'paciente_merge_sem_cpf',
                        'legado_id' => $item['legado_id'] ?? null,
                        'nome' => $item['nome'] ?? null,
                        'paciente_id' => $porNome->id,
                        'motivo' => $matchBy,
                    ];
                }
            }
        }

        if ($existente) {
            $cpfLegado = preg_replace('/\D+/', '', (string) ($item['cpf'] ?? '')) ?? '';
            $cpfAtual = preg_replace('/\D+/', '', (string) $existente->cpf) ?? '';
            $skipCpf = $cpfLegado !== '' && $cpfAtual !== '' && $cpfLegado !== $cpfAtual;
            if ($skipCpf) {
                $signal = [
                    'tipo' => 'cpf_divergente',
                    'paciente_id' => $existente->id,
                    'cpf_clw3' => $existente->cpf,
                    'cpf_legado' => $item['cpf'],
                    'acao' => 'mantido_cpf_clw3',
                ];
            }

            $before = $this->pacienteSnapshot($existente);
            $diff = $this->mergePaciente($existente, $item, $skipCpf);

            // Já importado e sem campos a preencher/alterar → não inflar stats/lista.
            if ($diff === []) {
                return [
                    'stat' => 'pacientes_skip',
                    'paciente_id' => $existente->id,
                    // Mantém só alertas de risco (ex.: CPF divergente); omite ruído de match.
                    'signal' => (($signal['tipo'] ?? null) === 'cpf_divergente') ? $signal : null,
                    'change' => null,
                ];
            }

            if ($medicoId) {
                $this->vinculoService->garantir($existente, $medicoId, [
                    'codigo' => $item['codigo'] ?? null,
                    'indicado_por' => $item['indicado_por'] ?? null,
                    'anotacoes' => $item['anotacoes'] ?? null,
                    'ativo' => (bool) ($item['ativo'] ?? true),
                ], null, 'import');
            }
            $after = $this->pacienteSnapshot($existente->fresh() ?? $existente);

            return [
                'stat' => 'pacientes_merge',
                'paciente_id' => $existente->id,
                'signal' => $signal,
                'change' => [
                    'id' => 'p-'.($item['legado_id'] ?? $existente->id),
                    'entity' => 'paciente',
                    'action' => 'merge',
                    'action_label' => 'Mesclar',
                    'legado_id' => $item['legado_id'] ?? null,
                    'clw3_id' => $existente->id,
                    'label' => (string) ($item['nome'] ?? $existente->nome ?? 'Paciente'),
                    'subtitle' => 'CLW3 #'.$existente->id.' · match: '.$matchBy,
                    'match_by' => $matchBy,
                    'before' => $before,
                    'after' => $after,
                    'diff' => $diff,
                    'warning' => $signal['tipo'] ?? null,
                ],
            ];
        }

        $attrs = [
            'codigo' => $item['codigo'] ?? null,
            'indicado_por' => $item['indicado_por'] ?? null,
            'nome' => $item['nome'],
            'data_nascimento' => $this->sanitizarData($item['data_nascimento'] ?? null),
            'sexo' => $item['sexo'] ?? null,
            'fototipo' => $item['fototipo'] ?? null,
            'cpf' => $item['cpf'] ?? null,
            'rg' => $item['rg'] ?? null,
            'celular' => $item['celular'] ?? null,
            'telefone1' => $item['telefone1'] ?? null,
            'email1' => LegadoEmail::usable($item['email1'] ?? null),
            'email2' => LegadoEmail::usable($item['email2'] ?? null),
            'tipo_endereco' => $item['tipo_endereco'] ?? null,
            'endereco' => $item['endereco'] ?? null,
            'numero' => $item['numero'] ?? null,
            'complemento' => $item['complemento'] ?? null,
            'bairro' => $item['bairro'] ?? null,
            'cidade' => $item['cidade'] ?? null,
            'uf' => $item['uf'] ?? null,
            'cep' => $item['cep'] ?? null,
            'medico_id' => $medicoId,
            'anotacoes' => $item['anotacoes'] ?? null,
            'ativo' => (bool) ($item['ativo'] ?? true),
        ];

        $paciente = Paciente::create($attrs);

        foreach ($item['telefones_adicionais'] ?? [] as $tel) {
            PacienteTelefone::create([
                'paciente_id' => $paciente->id,
                'numero' => $tel['numero'] ?? '',
                'tipo' => $tel['tipo'] ?? 'Residencial',
                'principal' => false,
            ]);
        }

        if ($medicoId) {
            $this->vinculoService->garantir($paciente, $medicoId, [
                'codigo' => $item['codigo'] ?? null,
                'indicado_por' => $item['indicado_por'] ?? null,
                'anotacoes' => $item['anotacoes'] ?? null,
                'ativo' => (bool) ($item['ativo'] ?? true),
            ], null, 'import');
        }

        $after = $this->pacienteSnapshot($paciente);
        $diff = [];
        foreach ($after as $field => $to) {
            if ($to === null || $to === '') {
                continue;
            }
            $diff[] = [
                'field' => $field,
                'label' => self::fieldLabel($field),
                'from' => null,
                'to' => $to,
                'op' => 'add',
            ];
        }

        return [
            'stat' => 'pacientes_novos',
            'paciente_id' => $paciente->id,
            'signal' => null,
            'change' => [
                'id' => 'p-'.($item['legado_id'] ?? $paciente->id),
                'entity' => 'paciente',
                'action' => 'novo',
                'action_label' => 'Novo',
                'legado_id' => $item['legado_id'] ?? null,
                'clw3_id' => $paciente->id,
                'label' => (string) ($item['nome'] ?? 'Paciente'),
                'subtitle' => 'Será criado no CLW3',
                'match_by' => null,
                'before' => [],
                'after' => $after,
                'diff' => $diff,
                'warning' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findPaciente(array $item, ?int $medicoId): ?Paciente
    {
        $cpf = preg_replace('/\D+/', '', (string) ($item['cpf'] ?? '')) ?? '';
        if (strlen($cpf) === 11) {
            $hit = Paciente::all(['id', 'cpf'])->first(
                fn (Paciente $p) => preg_replace('/\D+/', '', (string) $p->cpf) === $cpf
            );
            if ($hit) {
                return Paciente::find($hit->id);
            }
        }

        $codigo = trim((string) ($item['codigo'] ?? ''));
        if ($codigo !== '' && $medicoId) {
            $pivot = DB::table('medico_paciente')
                ->where('medico_id', $medicoId)
                ->where('codigo', $codigo)
                ->first();
            if ($pivot) {
                return Paciente::find($pivot->paciente_id);
            }
            $byCodigo = Paciente::where('codigo', $codigo)->where('medico_id', $medicoId)->first();
            if ($byCodigo) {
                return $byCodigo;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findPacientePorNome(array $item, ?int $medicoId): ?Paciente
    {
        $nome = trim((string) ($item['nome'] ?? ''));
        if ($nome === '') {
            return null;
        }
        $q = Paciente::where('nome', $nome);
        $dn = $this->sanitizarData($item['data_nascimento'] ?? null);
        if ($dn) {
            $q->whereDate('data_nascimento', $dn);
        }
        if ($medicoId) {
            $q->where(function ($w) use ($medicoId) {
                $w->where('medico_id', $medicoId)
                    ->orWhereHas('medicos', fn ($m) => $m->where('medicos.id', $medicoId));
            });
        }

        return $q->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{field:string,label:string,from:mixed,to:mixed,op:string}>
     */
    private function mergePaciente(Paciente $paciente, array $item, bool $skipCpf): array
    {
        $legadoTs = $this->parseTs($item['dta_ult_alteracao'] ?? $item['dta_inclusao'] ?? null);
        $clw3Ts = $paciente->updated_at ? Carbon::parse($paciente->updated_at) : null;
        $legadoMaisNovo = $legadoTs && $clw3Ts ? $legadoTs->gt($clw3Ts) : (bool) $legadoTs;
        $diff = [];

        $fields = self::PACIENTE_SHARED_FIELDS;
        if (! $skipCpf) {
            $fields[] = 'cpf';
        }

        foreach ($fields as $field) {
            if ($field === 'email1' || $field === 'email2') {
                $val = LegadoEmail::usable($item[$field] ?? null);
            } elseif ($field === 'data_nascimento') {
                $val = $this->sanitizarData($item[$field] ?? null);
            } elseif ($field === 'cpf') {
                $val = trim((string) ($item['cpf'] ?? ''));
                $val = $val === '' ? null : $val;
            } else {
                $val = $item[$field] ?? null;
                if (is_string($val)) {
                    $val = trim($val);
                    $val = $val === '' ? null : $val;
                }
            }

            $atual = $paciente->{$field};
            if ($atual instanceof \DateTimeInterface) {
                $atual = $atual->format('Y-m-d');
            }
            $atualStr = $atual === null || $atual === '' ? null : (string) $atual;
            $valStr = $val === null || $val === '' ? null : (string) $val;
            $atualVazio = $atualStr === null;

            if ($valStr === null) {
                continue;
            }

            $willSet = false;
            if ($field === 'cpf') {
                $willSet = $atualVazio;
            } elseif ($legadoMaisNovo) {
                $willSet = $atualStr !== $valStr;
            } elseif ($atualVazio) {
                $willSet = true;
            }

            if (! $willSet) {
                continue;
            }

            $diff[] = [
                'field' => $field,
                'label' => self::fieldLabel($field),
                'from' => $atualStr,
                'to' => $valStr,
                'op' => $atualVazio ? 'add' : 'change',
            ];
            $paciente->{$field} = $val;
        }

        if ($diff !== []) {
            $paciente->saveQuietly();
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function pacienteSnapshot(Paciente $paciente): array
    {
        $out = [];
        foreach ([...self::PACIENTE_SHARED_FIELDS, 'cpf', 'codigo', 'indicado_por', 'anotacoes', 'ativo'] as $field) {
            $v = $paciente->{$field} ?? null;
            if ($v instanceof \DateTimeInterface) {
                $v = $v->format('Y-m-d');
            }
            if (is_bool($v)) {
                $v = $v ? 'sim' : 'não';
            }
            $out[$field] = $v === null || $v === '' ? null : (string) $v;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function receitaSnapshot(Receita $receita): array
    {
        return [
            'numero' => $receita->numero,
            'numero_origem' => $receita->numero_origem,
            'data_receita' => $receita->data_receita
                ? (string) ($receita->data_receita instanceof \DateTimeInterface
                    ? $receita->data_receita->format('Y-m-d')
                    : $receita->data_receita)
                : null,
            'status' => $receita->status,
            'subtotal' => (string) ($receita->subtotal ?? '0'),
            'desconto_percentual' => (string) ($receita->desconto_percentual ?? '0'),
            'desconto_valor' => (string) ($receita->desconto_valor ?? '0'),
            'valor_frete' => (string) ($receita->valor_frete ?? '0'),
            'valor_total' => (string) ($receita->valor_total ?? '0'),
            'anotacoes' => $receita->anotacoes,
            'itens_count' => (string) $receita->itens()->count(),
            'medico_id' => (string) $receita->medico_id,
            'paciente_id' => (string) $receita->paciente_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{field:string,label:string,from:mixed,to:mixed,op:string}>
     */
    private function snapshotDiff(array $before, array $after): array
    {
        $diff = [];
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        foreach ($keys as $field) {
            $from = $before[$field] ?? null;
            $to = $after[$field] ?? null;
            $from = $from === '' ? null : $from;
            $to = $to === '' ? null : $to;
            if ((string) $from === (string) $to) {
                continue;
            }
            $diff[] = [
                'field' => $field,
                'label' => self::fieldLabel($field),
                'from' => $from,
                'to' => $to,
                'op' => $from === null ? 'add' : ($to === null ? 'remove' : 'change'),
            ];
        }

        return $diff;
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'nome' => 'Nome',
            'data_nascimento' => 'Nascimento',
            'sexo' => 'Sexo',
            'fototipo' => 'Fototipo',
            'cpf' => 'CPF',
            'rg' => 'RG',
            'celular' => 'Celular',
            'telefone1' => 'Telefone',
            'email1' => 'E-mail',
            'email2' => 'E-mail 2',
            'tipo_endereco' => 'Tipo endereço',
            'endereco' => 'Endereço',
            'numero' => 'Número',
            'complemento' => 'Complemento',
            'bairro' => 'Bairro',
            'cidade' => 'Cidade',
            'uf' => 'UF',
            'cep' => 'CEP',
            'codigo' => 'Nº registro',
            'indicado_por' => 'Indicado por',
            'anotacoes' => 'Anotações',
            'ativo' => 'Ativo',
            'numero_origem' => 'Nº origem CLW2',
            'data_receita' => 'Data receita',
            'status' => 'Status',
            'subtotal' => 'Subtotal',
            'desconto_percentual' => 'Desconto %',
            'desconto_valor' => 'Desconto R$',
            'valor_frete' => 'Frete',
            'valor_total' => 'Total',
            'itens_count' => 'Qtd. itens',
            'medico_id' => 'Médico ID',
            'paciente_id' => 'Paciente ID',
            default => $field,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $mapeamentoCodigos
     * @param  \Illuminate\Support\Collection  $produtoCache
     * @return array{stat:string,signal:?array,itens_sem_produto?:int,change?:?array}
     */
    private function upsertReceita(
        array $item,
        int $pacienteId,
        int $medicoId,
        array $mapeamentoCodigos,
        $produtoCache
    ): array {
        $legadoId = (int) $item['legado_id'];
        $existente = Receita::where('legado_id', $legadoId)->first()
            ?: Receita::where('anotacoes', 'like', "%[legado:{$legadoId}|%")->first();

        $status = (string) ($item['status'] ?? 'aberta');
        $legadoTs = $this->parseTs($item['dta_ult_alteracao'] ?? $item['dta_inclusao'] ?? $item['data_receita'] ?? null);
        $label = 'Receita legado #'.$legadoId
            .(! empty($item['numero_legado']) ? ' (nº '.$item['numero_legado'].')' : '')
            .' · '.($item['data_receita'] ?? 'sem data');

        if ($existente) {
            if ($existente->legado_id === null) {
                $existente->legado_id = $legadoId;
                $existente->numero_origem = $existente->numero_origem ?: ($item['numero_legado'] ?? null);
                $existente->origem = $existente->origem ?: 'clw2_importada';
                $existente->saveQuietly();
            }

            $clw3Ts = $existente->updated_at ? Carbon::parse($existente->updated_at) : null;
            $createdTs = $existente->created_at ? Carbon::parse($existente->created_at) : null;
            $tocadaNoClw3 = $clw3Ts && $createdTs && $clw3Ts->gt($createdTs->copy()->addMinutes(5));
            $legadoMaisNovo = $legadoTs && $clw3Ts ? $legadoTs->gt($clw3Ts) : (bool) $legadoTs;

            if ($tocadaNoClw3 && ! $legadoMaisNovo) {
                $before = $this->receitaSnapshot($existente);
                if ($status === 'cancelada' && $existente->status !== 'cancelada') {
                    $existente->status = 'cancelada';
                    $existente->ativo = false;
                    $existente->saveQuietly();
                    $after = $this->receitaSnapshot($existente);

                    return [
                        'stat' => 'receitas_canceladas_espelhadas',
                        'signal' => [
                            'tipo' => 'receita_clw3_mais_recente_mas_cancelada_no_legado',
                            'receita_id' => $existente->id,
                            'legado_id' => $legadoId,
                        ],
                        'change' => $this->receitaChange(
                            'cancelada',
                            'Cancelar',
                            $label,
                            $legadoId,
                            $existente->id,
                            $before,
                            $after,
                            'CLW3 tinha edição local; espelhou cancelamento do legado'
                        ),
                    ];
                }

                return [
                    'stat' => 'receitas_conflito',
                    'signal' => [
                        'tipo' => 'receita_clw3_mais_recente',
                        'receita_id' => $existente->id,
                        'legado_id' => $legadoId,
                        'acao' => 'mantido_clw3',
                    ],
                    'change' => $this->receitaChange(
                        'conflito',
                        'Conflito (mantém CLW3)',
                        $label,
                        $legadoId,
                        $existente->id,
                        $before,
                        $before,
                        'Receita editada no CLW3 após import; dump legado ignorado'
                    ),
                ];
            }

            // Dump ≤ CLW3 e sem edição local: não há motivo para refresh cego.
            // Exceção: espelhar cancelamento do legado.
            if (! $legadoMaisNovo && ! $tocadaNoClw3) {
                if ($status === 'cancelada' && $existente->status !== 'cancelada') {
                    $before = $this->receitaSnapshot($existente);
                    $existente->status = 'cancelada';
                    $existente->ativo = false;
                    $existente->saveQuietly();
                    $after = $this->receitaSnapshot($existente);

                    return [
                        'stat' => 'receitas_canceladas_espelhadas',
                        'signal' => [
                            'tipo' => 'receita_cancelada_espelhada_dump_nao_mais_novo',
                            'receita_id' => $existente->id,
                            'legado_id' => $legadoId,
                        ],
                        'change' => $this->receitaChange(
                            'cancelada',
                            'Cancelar',
                            $label,
                            $legadoId,
                            $existente->id,
                            $before,
                            $after,
                            'Espelhou cancelamento do legado (dump ≤ CLW3)'
                        ),
                    ];
                }

                return [
                    'stat' => 'receitas_skip',
                    'signal' => null,
                    'change' => null,
                ];
            }

            $before = $this->receitaSnapshot($existente);
            $semProduto = $this->aplicarCabecalhoEItens(
                $existente,
                $item,
                $pacienteId,
                $medicoId,
                $mapeamentoCodigos,
                $produtoCache,
                false
            );
            $existente->refresh();
            $after = $this->receitaSnapshot($existente);
            $diff = $this->snapshotDiff($before, $after);

            // Legado mais novo, mas após aplicar nada mudou → não listar como atualização.
            if ($diff === [] && $status !== 'cancelada') {
                return [
                    'stat' => 'receitas_skip',
                    'signal' => null,
                    'itens_sem_produto' => $semProduto,
                    'change' => null,
                ];
            }

            $action = $status === 'cancelada' ? 'cancelada' : 'atualizada';

            return [
                'stat' => $status === 'cancelada' ? 'receitas_canceladas_espelhadas' : 'receitas_atualizadas',
                'signal' => [
                    'tipo' => 'receita_atualizada_do_legado',
                    'receita_id' => $existente->id,
                    'legado_id' => $legadoId,
                    'motivo' => $tocadaNoClw3 ? 'legado_mais_novo' : 'clw3_somente_import',
                ],
                'itens_sem_produto' => $semProduto,
                'change' => $this->receitaChange(
                    $action,
                    $action === 'cancelada' ? 'Cancelar' : 'Atualizar',
                    $label,
                    $legadoId,
                    $existente->id,
                    $before,
                    $after,
                    $tocadaNoClw3 ? 'Legado mais recente' : 'Dump legado mais novo que o CLW3'
                ),
            ];
        }

        $receita = new Receita;
        $semProduto = $this->aplicarCabecalhoEItens(
            $receita,
            $item,
            $pacienteId,
            $medicoId,
            $mapeamentoCodigos,
            $produtoCache,
            true
        );

        $this->vinculoService->garantir(Paciente::findOrFail($pacienteId), $medicoId, [], null, 'import');
        $after = $this->receitaSnapshot($receita);

        return [
            'stat' => 'receitas_novas',
            'signal' => null,
            'itens_sem_produto' => $semProduto,
            'change' => $this->receitaChange(
                'nova',
                'Nova',
                $label,
                $legadoId,
                $receita->id,
                [],
                $after,
                'Será criada no CLW3'
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    private function receitaChange(
        string $action,
        string $actionLabel,
        string $label,
        int $legadoId,
        ?int $clw3Id,
        array $before,
        array $after,
        ?string $subtitle = null
    ): array {
        return [
            'id' => 'r-'.$legadoId,
            'entity' => 'receita',
            'action' => $action,
            'action_label' => $actionLabel,
            'legado_id' => $legadoId,
            'clw3_id' => $clw3Id,
            'label' => $label,
            'subtitle' => $subtitle,
            'before' => $before,
            'after' => $after,
            'diff' => $this->snapshotDiff($before, $after),
            'warning' => $action === 'conflito' ? 'conflito' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $mapeamentoCodigos
     * @param  \Illuminate\Support\Collection  $produtoCache
     */
    private function aplicarCabecalhoEItens(
        Receita $receita,
        array $item,
        int $pacienteId,
        int $medicoId,
        array $mapeamentoCodigos,
        $produtoCache,
        bool $isNew
    ): int {
        $legadoId = (int) $item['legado_id'];
        $status = (string) ($item['status'] ?? 'aberta');

        if ($isNew) {
            $receita->numero = Receita::gerarNumero($pacienteId);
            $anotacoes = trim((string) ($item['anotacoes'] ?? ''));
            $anotacoes = trim($anotacoes."\n[legado:{$legadoId}|num:".($item['numero_legado'] ?? '').']');
            $receita->anotacoes = $anotacoes;
        } else {
            // Atualiza observações do legado sem duplicar a tag
            $base = (string) ($receita->anotacoes ?? '');
            $base = preg_replace('/\n?\[legado:\d+\|num:[^\]]*\]/', '', $base) ?? $base;
            $obs = trim((string) ($item['anotacoes'] ?? ''));
            $receita->anotacoes = trim($obs."\n[legado:{$legadoId}|num:".($item['numero_legado'] ?? '').']');
            unset($base);
        }

        $receita->legado_id = $legadoId;
        $receita->numero_origem = $item['numero_legado'] ?? $receita->numero_origem;
        $receita->origem = 'clw2_importada';
        $receita->data_receita = $item['data_receita'] ?? now()->toDateString();
        $receita->paciente_id = $pacienteId;
        $receita->medico_id = $medicoId;
        $receita->subtotal = $item['subtotal'] ?? 0;
        $receita->desconto_percentual = $item['desconto_percentual'] ?? 0;
        $receita->desconto_valor = $item['desconto_valor'] ?? 0;
        $receita->desconto_motivo = $item['desconto_motivo'] ?? null;
        $receita->valor_frete = $item['valor_frete'] ?? 0;
        $receita->valor_total = $item['valor_total'] ?? 0;
        $receita->status = $status === 'cancelada' ? 'cancelada' : $status;
        $receita->ativo = $status !== 'cancelada';
        $receita->saveQuietly();

        if (! $isNew) {
            ReceitaItem::where('receita_id', $receita->id)->delete();
        }

        $semProduto = 0;
        $ordem = 0;
        foreach ($item['itens'] ?? [] as $ri) {
            $produto = LegadoProdutoResolver::findPorItemLegado(
                $ri,
                $produtoCache,
                $mapeamentoCodigos,
                $item['fototipo_paciente'] ?? null
            );
            if (! $produto) {
                $produto = LegadoProdutoResolver::criarStubSeNecessario(
                    $ri,
                    $produtoCache,
                    $mapeamentoCodigos,
                    true
                );
            }
            if (! $produto) {
                $semProduto++;

                continue;
            }
            $ordem++;
            $line = new ReceitaItem;
            $line->receita_id = $receita->id;
            $line->produto_id = $produto->id;
            $line->local_uso = $ri['local_uso'] ?? null;
            $line->anotacoes = $ri['anotacoes'] ?? null;
            $line->quantidade = $ri['quantidade'] ?? 1;
            $line->valor_unitario = $ri['valor_unitario'] ?? 0;
            $line->valor_total = ($ri['quantidade'] ?? 1) * ($ri['valor_unitario'] ?? 0);
            $line->data_aquisicao = $ri['data_aquisicao'] ?? null;
            $line->imprimir = (bool) ($ri['imprimir'] ?? true);
            $line->ordem = $ordem;
            $line->grupo = 'recomendado';
            $line->saveQuietly();
        }

        return $semProduto;
    }

    /**
     * @param  array<string, mixed>  $receitaItem
     */
    private function findPacienteIdFromPriorImport(int $legadoPacienteId, array $receitaItem): ?int
    {
        // Via receita já importada do mesmo paciente legado
        $rec = Receita::where('anotacoes', 'like', '%[legado:%')
            ->whereHas('paciente')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        // Fallback: CPF do paciente no item não vem — tenta pelo mapping de receitas do mesmo paciente no dump já no banco
        unset($rec);

        $cpf = null;
        // last resort: look for any receita with same legado patient through... we don't store paciente legado_id
        return null;
    }

    private function pacientePrecisaRenumerar(int $pacienteId): bool
    {
        $numeros = Receita::where('paciente_id', $pacienteId)->pluck('numero');
        $seen = [];
        foreach ($numeros as $n) {
            $key = (string) $n;
            if (isset($seen[$key])) {
                return true;
            }
            $seen[$key] = true;
        }

        // mistura origem
        $origens = Receita::where('paciente_id', $pacienteId)->pluck('origem')->unique()->filter();
        if ($origens->count() > 1) {
            return true;
        }

        return false;
    }

    private function parseTs(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sanitizarData(mixed $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }
        $data = (string) $data;
        if (str_starts_with($data, '-') || str_starts_with($data, '0000-')) {
            return null;
        }

        return $data;
    }

    /**
     * @param  list<array<string,mixed>>  $mappings
     * @param  array<string,mixed>  $stats
     * @param  list<array<string,mixed>>  $signals
     * @param  array<string,mixed>  $extra
     * @return array<string, mixed>
     */
    private function finishReport(
        string $workDir,
        bool $dryRun,
        string $sqlPath,
        array $mappings,
        array $stats,
        array $signals,
        array $extra = []
    ): array {
        $report = array_merge([
            'dry_run' => $dryRun,
            'sql' => $sqlPath,
            'work_dir' => $workDir,
            'generated_at' => now()->toIso8601String(),
            'mappings' => $mappings,
            'stats' => $stats,
            'signals' => $signals,
        ], $extra);

        $name = ($dryRun ? 'report-dry-run' : 'report-apply').'-'.now()->format('Ymd-His').'.json';
        File::ensureDirectoryExists($workDir);
        file_put_contents(
            $workDir.'/'.$name,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        file_put_contents($workDir.'/report-latest.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $report;
    }
}
