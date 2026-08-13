<?php

namespace App\Services\Migration;

use App\Models\Medico;
use App\Models\MedicoPaciente;
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

    /** @var array<string, int> CPF só com dígitos => paciente id */
    private array $cpfIndex = [];

    private bool $cpfIndexCarregado = false;

    /**
     * Âncora de importações anteriores: id do paciente no CLW2 => id no CLW3, deduzido das
     * receitas que já entraram (`receitas.legado_id`). É o único vínculo determinístico que
     * existe para paciente — sem ele, cadastro sem CPF, sem código, sem nascimento e sem
     * celular é recriado a cada rodada (ver job 946a5c52).
     *
     * @var array<int, int>
     */
    private array $pacienteIdPorLegadoPaciente = [];

    /** Como o último findPaciente() casou — só para o rótulo do report. */
    private string $ultimoMatchPaciente = 'cpf_ou_codigo';

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

        $this->cpfIndex = [];
        $this->cpfIndexCarregado = false;

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
            // Erro de verdade: não pode virar "dry-run concluído" com report vazio.
            throw new \RuntimeException(
                'Nenhum dos médicos selecionados casou com o dump CLW2 (confira CPF/CRM em "1. Confirmar mapeamento").'
            );
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

        $this->carregarAncoraDeImportacaoAnterior($receitasFiltradas);

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
            'receitas_sem_vinculo' => 0,
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
                if (! empty($result['needs_review'])) {
                    $stats['pacientes_needs_review']++;
                }
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
                $legadoMedicoId = isset($item['legado_medico_id']) ? (int) $item['legado_medico_id'] : null;
                $medicoId = $legadoMedicoId ? ($clw3ByLegadoMedico[$legadoMedicoId] ?? null) : null;

                if (! $pacienteId || ! $medicoId) {
                    // Não é "sem mudança": a receita foi descartada por falta de vínculo.
                    $stats['receitas_sem_vinculo']++;
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
            'medico_ids' => array_values(array_unique(array_map(
                fn ($row) => (int) $row['medico']->id,
                $resolved['resolved']
            ))),
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

        $this->ultimoMatchPaciente = 'cpf_ou_codigo';
        $existente = $this->findPaciente($item, $medicoId);
        $signal = null;
        $matchBy = $this->ultimoMatchPaciente;
        $needsReview = false;

        if ($existente === null) {
            $porNome = $this->findPacientePorNomeDetalhado($item);
            if ($porNome !== null && $porNome['paciente'] instanceof Paciente) {
                $existente = $porNome['paciente'];
                $matchBy = $porNome['match_by'];
                if ($porNome['ambiguo']) {
                    $needsReview = true;
                    $signal = [
                        'tipo' => 'paciente_homonimo_ambiguo',
                        'legado_id' => $item['legado_id'] ?? null,
                        'nome' => $item['nome'] ?? null,
                        'paciente_id' => $existente->id,
                        'candidatos' => $porNome['homonimos'],
                        'acao' => 'mesclado_no_mais_antigo',
                    ];
                } elseif (empty($item['cpf'])) {
                    $signal = [
                        'tipo' => 'paciente_merge_sem_cpf',
                        'legado_id' => $item['legado_id'] ?? null,
                        'nome' => $item['nome'] ?? null,
                        'paciente_id' => $existente->id,
                        'motivo' => $matchBy,
                    ];
                }
            } elseif ($porNome !== null && $porNome['homonimos'] > 0) {
                // Existe alguém com o mesmo nome, mas nem nascimento nem telefone bateram:
                // pode ser homônimo de verdade ou cadastro divergente. Vai criar — sinalizado.
                $needsReview = true;
                $signal = [
                    'tipo' => 'paciente_novo_com_homonimo',
                    'legado_id' => $item['legado_id'] ?? null,
                    'nome' => $item['nome'] ?? null,
                    'homonimos_no_clw3' => $porNome['homonimos'],
                ];
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

            // Ficha ativa no CLW2 caindo numa ficha arquivada aqui: `ativo` não entra no merge
            // (arquivar é decisão do CLW3), então o registro fica invisível na busca do médico
            // carregando as receitas da ficha boa. Fica no report para dar rastro.
            if ($signal === null && ($item['ativo'] ?? null) && ! $existente->ativo) {
                $signal = [
                    'tipo' => 'paciente_ativo_no_legado_arquivado_no_clw3',
                    'legado_id' => $item['legado_id'] ?? null,
                    'nome_legado' => $item['nome'] ?? null,
                    'paciente_id' => $existente->id,
                    'nome_clw3' => $existente->nome,
                    'acao' => 'mantido_arquivado',
                ];
            }

            $before = $this->pacienteSnapshot($existente);

            // O vínculo é o objetivo da liberação por médico: garante ANTES de decidir o skip,
            // senão paciente já completo no CLW3 nunca aparece para o médico liberado.
            $vinculoNovo = $medicoId
                ? $this->garantirVinculoDoImport($existente, $medicoId, $item)
                : false;

            $diff = $this->mergePaciente($existente, $item, $skipCpf);

            // Já importado, já vinculado e sem campos a preencher/alterar → não inflar stats/lista.
            if ($diff === [] && ! $vinculoNovo) {
                return [
                    'stat' => 'pacientes_skip',
                    'paciente_id' => $existente->id,
                    // Mantém só alertas de risco (CPF divergente, ficha arquivada); omite ruído de match.
                    'signal' => in_array($signal['tipo'] ?? null, [
                        'cpf_divergente',
                        'paciente_ativo_no_legado_arquivado_no_clw3',
                    ], true) ? $signal : null,
                    'needs_review' => $needsReview,
                    'change' => null,
                ];
            }

            if ($vinculoNovo) {
                $diff[] = [
                    'field' => 'vinculo_medico',
                    'label' => 'Vínculo com o médico',
                    'from' => null,
                    'to' => 'criado',
                    'op' => 'add',
                ];
            }

            $after = $this->pacienteSnapshot($existente->fresh() ?? $existente);

            return [
                'stat' => 'pacientes_merge',
                'paciente_id' => $existente->id,
                'signal' => $signal,
                'needs_review' => $needsReview,
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
        $this->indexarCpf($paciente);

        foreach ($item['telefones_adicionais'] ?? [] as $tel) {
            PacienteTelefone::create([
                'paciente_id' => $paciente->id,
                'numero' => $tel['numero'] ?? '',
                'tipo' => $tel['tipo'] ?? 'Residencial',
                'principal' => false,
            ]);
        }

        if ($medicoId) {
            $this->garantirVinculoDoImport($paciente, $medicoId, $item);
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
            'signal' => $signal,
            'needs_review' => $needsReview,
            'change' => [
                'id' => 'p-'.($item['legado_id'] ?? $paciente->id),
                'entity' => 'paciente',
                'action' => 'novo',
                'action_label' => 'Novo',
                'legado_id' => $item['legado_id'] ?? null,
                'clw3_id' => $paciente->id,
                'label' => (string) ($item['nome'] ?? 'Paciente'),
                'subtitle' => $needsReview
                    ? 'Será criado no CLW3 — já existe alguém com este nome, confira antes de aplicar'
                    : 'Será criado no CLW3',
                'match_by' => null,
                'before' => [],
                'after' => $after,
                'diff' => $diff,
                'warning' => $signal['tipo'] ?? null,
            ],
        ];
    }

    /**
     * Garante o vínculo médico↔paciente sem pisar no que o médico escreveu no CLW3.
     *
     * Os campos do pivot (código, indicado por, anotações) são privados de cada médico: num
     * vínculo que já existe o legado só preenche o que está vazio.
     *
     * @param  array<string, mixed>  $item
     * @return bool se o vínculo foi criado agora
     */
    private function garantirVinculoDoImport(Paciente $paciente, int $medicoId, array $item): bool
    {
        $privados = [
            'codigo' => $item['codigo'] ?? null,
            'indicado_por' => $item['indicado_por'] ?? null,
            'anotacoes' => $item['anotacoes'] ?? null,
            'ativo' => (bool) ($item['ativo'] ?? true),
        ];

        $atual = MedicoPaciente::where('medico_id', $medicoId)
            ->where('paciente_id', $paciente->id)
            ->first();

        if ($atual) {
            foreach (['codigo', 'indicado_por', 'anotacoes'] as $campo) {
                if (trim((string) $atual->{$campo}) !== '') {
                    unset($privados[$campo]);
                }
            }
            // `ativo` no CLW3 é decisão do médico, não do dump.
            unset($privados['ativo']);
        }

        return $this->vinculoService
            ->garantir($paciente, $medicoId, $privados, null, 'import')
            ->wasRecentlyCreated;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findPaciente(array $item, ?int $medicoId): ?Paciente
    {
        // Importação anterior do mesmo paciente CLW2 vence tudo: é determinística e evita que a
        // rodada seguinte recrie quem a heurística de nome/nascimento/telefone não reencontra.
        $legadoId = (int) ($item['legado_id'] ?? 0);
        if ($legadoId > 0 && isset($this->pacienteIdPorLegadoPaciente[$legadoId])) {
            $hit = Paciente::find($this->pacienteIdPorLegadoPaciente[$legadoId]);
            if ($hit) {
                $this->ultimoMatchPaciente = 'import_anterior';

                return $hit;
            }
        }

        $cpf = $this->apenasDigitos($item['cpf'] ?? null);
        if (strlen($cpf) === 11) {
            $id = $this->cpfIndex()[$cpf] ?? null;
            if ($id) {
                $hit = Paciente::find($id);
                if ($hit) {
                    return $hit;
                }
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
     * Monta `legado_paciente_id => paciente_id` a partir das receitas que já entraram no CLW3.
     *
     * O paciente não guarda o id do CLW2 em lugar nenhum, mas a receita guarda (`legado_id`), e
     * todo paciente importado tem pelo menos uma receita — o filtro exige isso. Então a receita
     * já importada é a prova de qual cadastro do CLW3 corresponde ao paciente do dump.
     *
     * Em produção depende de `receitas.legado_id` estar populado: rodar antes
     * `migration:backfill-receita-legado-id --force` (a carga inicial só deixou a tag
     * `[legado:ID|num:N]` nas anotações).
     *
     * @param  list<array<string, mixed>>  $receitasFiltradas
     */
    private function carregarAncoraDeImportacaoAnterior(array $receitasFiltradas): void
    {
        $this->pacienteIdPorLegadoPaciente = [];

        $pacientePorReceitaLegado = [];
        foreach ($receitasFiltradas as $r) {
            $receitaLegadoId = (int) ($r['legado_id'] ?? 0);
            $pacienteLegadoId = (int) ($r['legado_paciente_id'] ?? 0);
            if ($receitaLegadoId > 0 && $pacienteLegadoId > 0) {
                $pacientePorReceitaLegado[$receitaLegadoId] = $pacienteLegadoId;
            }
        }

        if ($pacientePorReceitaLegado === []) {
            return;
        }

        foreach (array_chunk(array_keys($pacientePorReceitaLegado), 1000) as $chunk) {
            $rows = Receita::query()
                ->whereIn('legado_id', $chunk)
                ->whereNotNull('paciente_id')
                ->get(['legado_id', 'paciente_id']);

            foreach ($rows as $row) {
                $pacienteLegadoId = $pacientePorReceitaLegado[(int) $row->legado_id] ?? null;
                if ($pacienteLegadoId === null) {
                    continue;
                }
                $pacienteId = (int) $row->paciente_id;
                $atual = $this->pacienteIdPorLegadoPaciente[$pacienteLegadoId] ?? null;
                // Receitas do mesmo paciente CLW2 podem ter caído em cadastros diferentes no CLW3
                // (duplicatas antigas): fica com o mais antigo, igual ao merge por homônimo.
                if ($atual === null || $pacienteId < $atual) {
                    $this->pacienteIdPorLegadoPaciente[$pacienteLegadoId] = $pacienteId;
                }
            }
        }
    }

    /**
     * Índice CPF→id carregado uma vez por execução (era um `Paciente::all()` por paciente do dump).
     *
     * @return array<string, int>
     */
    private function cpfIndex(): array
    {
        if (! $this->cpfIndexCarregado) {
            $this->cpfIndex = [];
            foreach (Paciente::query()->whereNotNull('cpf')->pluck('cpf', 'id') as $id => $cpf) {
                $digits = $this->apenasDigitos($cpf);
                if (strlen($digits) === 11 && ! isset($this->cpfIndex[$digits])) {
                    $this->cpfIndex[$digits] = (int) $id;
                }
            }
            $this->cpfIndexCarregado = true;
        }

        return $this->cpfIndex;
    }

    private function indexarCpf(Paciente $paciente): void
    {
        $digits = $this->apenasDigitos($paciente->cpf);
        if (strlen($digits) === 11) {
            $this->cpfIndex();
            $this->cpfIndex[$digits] ??= (int) $paciente->id;
        }
    }

    private function apenasDigitos(mixed $valor): string
    {
        return preg_replace('/\D+/', '', (string) ($valor ?? '')) ?? '';
    }

    /**
     * Conciliação sem CPF. Não filtra por médico de propósito: na Opção 2 o paciente é único
     * e costuma já existir vinculado a OUTRO médico (ou sem nenhum, vindo do oList).
     *
     * @param  array<string, mixed>  $item
     * @return array{paciente:?Paciente,match_by:?string,ambiguo:bool,homonimos:int}|null
     */
    private function findPacientePorNomeDetalhado(array $item): ?array
    {
        $nome = trim((string) ($item['nome'] ?? ''));
        if ($nome === '') {
            return null;
        }

        $homonimos = Paciente::where('nome', $nome)->orderBy('id')->get();
        if ($homonimos->isEmpty()) {
            return null;
        }

        $dn = $this->sanitizarData($item['data_nascimento'] ?? null);
        if ($dn !== null) {
            $porDn = $homonimos->filter(
                fn (Paciente $p) => $this->dataComparavel($p->data_nascimento) === $dn
            );
            if ($porDn->isNotEmpty()) {
                return [
                    'paciente' => $porDn->first(),
                    'match_by' => 'nome_dn',
                    'ambiguo' => $porDn->count() > 1,
                    'homonimos' => $homonimos->count(),
                ];
            }
        }

        // Nascimento ausente/divergente (o legado tem data lixo e digitação trocada):
        // telefone + nome exato é sinal forte o bastante e não funde mãe/filha (nomes diferentes).
        $tel = $this->apenasDigitos($item['celular'] ?? null);
        if (strlen($tel) >= 10) {
            $porTel = $homonimos->filter(function (Paciente $p) use ($tel) {
                foreach ([$p->celular, $p->telefone1] as $numero) {
                    if ($this->apenasDigitos($numero) === $tel) {
                        return true;
                    }
                }

                return false;
            });
            if ($porTel->isNotEmpty()) {
                return [
                    'paciente' => $porTel->first(),
                    'match_by' => 'nome_celular',
                    'ambiguo' => $porTel->count() > 1,
                    'homonimos' => $homonimos->count(),
                ];
            }
        }

        return [
            'paciente' => null,
            'match_by' => null,
            'ambiguo' => false,
            'homonimos' => $homonimos->count(),
        ];
    }

    private function dataComparavel(mixed $valor): ?string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        $str = trim((string) ($valor ?? ''));

        return $str === '' ? null : substr($str, 0, 10);
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
            'itens_count' => (string) ($receita->exists ? $receita->itens()->count() : 0),
            'itens_resumo' => $this->itensResumo($receita),
            'medico_id' => (string) $receita->medico_id,
            'paciente_id' => (string) $receita->paciente_id,
        ];
    }

    /**
     * Conteúdo dos itens em texto — sem isso o diff só via `itens_count` e uma troca de
     * produto/posologia passava como "sem mudança".
     */
    private function itensResumo(Receita $receita): ?string
    {
        if (! $receita->exists) {
            return null;
        }

        $itens = ReceitaItem::with('produto:id,codigo')
            ->where('receita_id', $receita->id)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        if ($itens->isEmpty()) {
            return null;
        }

        return $itens->map(function (ReceitaItem $i) {
            $codigo = $i->produto?->codigo ?: ('#'.$i->produto_id);
            $local = trim((string) $i->local_uso);
            $obs = trim((string) $i->anotacoes);

            return $codigo
                .' x'.(int) $i->quantidade
                .' R$'.number_format((float) $i->valor_unitario, 2, ',', '')
                .($local !== '' ? ' ['.$local.']' : '')
                .($obs !== '' ? ' ('.$obs.')' : '')
                .($i->imprimir ? '' : ' [não imprime]');
        })->implode(' · ');
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
            'itens_resumo' => 'Itens',
            'vinculo_medico' => 'Vínculo com o médico',
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

        // A receita pode estar entrando numa ficha que o médico não acha na busca (ver job
        // f8b5e9c5: no CLW2 a clínica desativava a ficha repetida e criava outra, e o merge por
        // CPF fica com a desativada). Sem este aviso a importação passa muda e o médico só
        // descobre na hora de atender.
        $avisoFicha = $this->avisoFichaInvisivel($pacienteId, $medicoId);

        if ($existente) {
            if ($existente->legado_id === null) {
                // Backfill de metadado: query builder cru (o Eloquent carimbaria `updated_at` e
                // faria a receita parecer "editada no CLW3" nas próximas rodadas).
                DB::table('receitas')->where('id', $existente->id)->update([
                    'legado_id' => $legadoId,
                    'numero_origem' => $existente->numero_origem ?: ($item['numero_legado'] ?? null),
                    'origem' => $existente->origem ?: 'clw2_importada',
                ]);
                $existente->refresh();
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
                'signal' => $avisoFicha
                    ? $this->sinalFichaInvisivel($legadoId, $pacienteId, $medicoId, $avisoFicha)
                    : [
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
                    $tocadaNoClw3 ? 'Legado mais recente' : 'Dump legado mais novo que o CLW3',
                    $avisoFicha
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
            'signal' => $avisoFicha
                ? $this->sinalFichaInvisivel($legadoId, $pacienteId, $medicoId, $avisoFicha)
                : null,
            'itens_sem_produto' => $semProduto,
            'change' => $this->receitaChange(
                'nova',
                'Nova',
                $label,
                $legadoId,
                $receita->id,
                [],
                $after,
                'Será criada no CLW3',
                $avisoFicha
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
        ?string $subtitle = null,
        ?string $warning = null
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
            'warning' => $action === 'conflito' ? 'conflito' : $warning,
        ];
    }

    /**
     * A ficha de destino é invisível para o médico que prescreveu? São os dois filtros da busca:
     * `pacientes.ativo` (esconde de todos) e `medico_paciente.ativo` (esconde dele).
     */
    private function avisoFichaInvisivel(int $pacienteId, int $medicoId): ?string
    {
        $ativo = DB::table('pacientes')->where('id', $pacienteId)->value('ativo');
        if ($ativo !== null && ! $ativo) {
            return 'paciente_arquivado';
        }

        $vinculo = DB::table('medico_paciente')
            ->where('paciente_id', $pacienteId)
            ->where('medico_id', $medicoId)
            ->value('ativo');

        // Vínculo inexistente não é aviso: o import cria um ativo logo em seguida.
        return ($vinculo !== null && ! $vinculo) ? 'vinculo_arquivado' : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function sinalFichaInvisivel(int $legadoReceitaId, int $pacienteId, int $medicoId, string $motivo): array
    {
        return [
            'tipo' => 'receita_em_ficha_invisivel',
            'legado_receita_id' => $legadoReceitaId,
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'motivo' => $motivo,
            'acao' => 'importada_mesmo_assim',
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

        $tag = "[legado:{$legadoId}|num:".($item['numero_legado'] ?? '').']';
        $obs = trim((string) ($item['anotacoes'] ?? ''));

        if ($isNew) {
            $receita->numero = Receita::gerarNumero($pacienteId);
            $receita->anotacoes = trim($obs."\n".$tag);
        } else {
            // As anotações da receita são as notas internas do médico no CLW3: não podem ser
            // substituídas pelo dump. Preserva o texto e só acrescenta o que ainda não está lá.
            $base = trim(preg_replace(
                '/\n?\[legado:\d+\|num:[^\]]*\]/',
                '',
                (string) ($receita->anotacoes ?? '')
            ) ?? '');

            $partes = array_filter([
                $base,
                ($obs !== '' && ! str_contains($base, $obs)) ? $obs : '',
            ], fn ($p) => $p !== '');

            $receita->anotacoes = trim(implode("\n", $partes)."\n".$tag);
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

        // Só grava se algo mudou: senão o refresh carimbava `updated_at` de receitas idênticas.
        if ($isNew || $receita->isDirty()) {
            $receita->saveQuietly();
        }

        [$linhas, $semProduto] = $this->resolverLinhasItens($item, $mapeamentoCodigos, $produtoCache);
        $this->sincronizarItens($receita, $linhas, $isNew);

        return $semProduto;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $mapeamentoCodigos
     * @param  \Illuminate\Support\Collection  $produtoCache
     * @return array{0: list<array<string,mixed>>, 1: int}
     */
    private function resolverLinhasItens(array $item, array $mapeamentoCodigos, $produtoCache): array
    {
        $linhas = [];
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
            $quantidade = (int) ($ri['quantidade'] ?? 1);
            $valorUnitario = (float) ($ri['valor_unitario'] ?? 0);

            $linhas[] = [
                'produto_id' => (int) $produto->id,
                'local_uso' => $ri['local_uso'] ?? null,
                'anotacoes' => $ri['anotacoes'] ?? null,
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $quantidade * $valorUnitario,
                'data_aquisicao' => $ri['data_aquisicao'] ?? null,
                'imprimir' => (bool) ($ri['imprimir'] ?? true),
                'ordem' => $ordem,
            ];
        }

        return [$linhas, $semProduto];
    }

    /**
     * Reconcilia os itens no lugar em vez de apagar e recriar.
     *
     * O DELETE cascateia em `receita_item_aquisicoes` (histórico de compra vindo do oList) e
     * zerava `vendido`/`data_aquisicao` a cada refresh — inclusive quando nada tinha mudado.
     *
     * @param  list<array<string,mixed>>  $linhas
     */
    private function sincronizarItens(Receita $receita, array $linhas, bool $isNew): void
    {
        $existentes = $isNew
            ? collect()
            : ReceitaItem::where('receita_id', $receita->id)->orderBy('ordem')->orderBy('id')->get();

        /** @var array<int, list<ReceitaItem>> $porProduto */
        $porProduto = [];
        foreach ($existentes as $atual) {
            $porProduto[(int) $atual->produto_id][] = $atual;
        }

        $mantidos = [];
        foreach ($linhas as $linha) {
            $produtoId = $linha['produto_id'];
            $alvo = ! empty($porProduto[$produtoId]) ? array_shift($porProduto[$produtoId]) : null;

            if ($alvo === null) {
                $alvo = new ReceitaItem;
                $alvo->receita_id = $receita->id;
                $alvo->produto_id = $produtoId;
                $alvo->grupo = 'recomendado';
            }

            $alvo->local_uso = $linha['local_uso'];
            $alvo->anotacoes = $linha['anotacoes'];
            $alvo->quantidade = $linha['quantidade'];
            $alvo->valor_unitario = $linha['valor_unitario'];
            $alvo->valor_total = $linha['valor_total'];
            $alvo->imprimir = $linha['imprimir'];
            $alvo->ordem = $linha['ordem'];
            $alvo->grupo = $alvo->grupo ?: 'recomendado';

            // Nunca apagar aquisição registrada no CLW3 com um nulo do dump; `vendido` é do oList.
            if (! empty($linha['data_aquisicao'])) {
                $alvo->data_aquisicao = $linha['data_aquisicao'];
            }

            if (! $alvo->exists || $alvo->isDirty()) {
                $alvo->saveQuietly();
            }
            $mantidos[] = (int) $alvo->id;
        }

        foreach ($porProduto as $sobras) {
            foreach ($sobras as $sobra) {
                if (! in_array((int) $sobra->id, $mantidos, true)) {
                    // Quieto: os totais vêm do dump, não do recálculo do observer.
                    $sobra->deleteQuietly();
                }
            }
        }
    }

    /**
     * Só renumera quando há número repetido no mesmo paciente — renumerar por origem mexeria no
     * `numero` de receitas já impressas sem necessidade.
     */
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

    /**
     * O dump tem data lixo (ex.: `9862-99-11`). Sem barrar aqui, o cast do Eloquent "rola" o mês
     * inválido e grava outra data (`9870-03-11`) — que depois nunca casa com o próprio dump.
     */
    private function sanitizarData(mixed $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }
        $data = trim((string) $data);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m)) {
            return null;
        }

        [$ano, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($ano < 1900 || $ano > (int) date('Y')) {
            return null;
        }
        if (! checkdate($mes, $dia, $ano)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
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
