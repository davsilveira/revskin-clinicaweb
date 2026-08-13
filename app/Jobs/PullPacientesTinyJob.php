<?php

namespace App\Jobs;

use App\Models\Paciente;
use App\Models\Setting;
use App\Services\TinyContatoMapper;
use App\Services\TinyErpClient;
use App\Support\EmailPlaceholder;
use App\Support\NomePaciente;
use App\Support\TelefonePaciente;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Traz contatos do oList (Tiny) para `pacientes`.
 *
 * Dois modos:
 *  - incremental (padrão, agendado): só contatos alterados desde a última passagem;
 *  - backfill completo (`backfillCompleto: true`, via `php artisan tiny:importar-clientes --full`):
 *    varre a base inteira de contatos ativos. É a carga inicial pedida pelo cliente.
 *
 * Cliente do oList que ainda não existe aqui entra como paciente SEM vínculo com médico —
 * ele aparece na busca por nome do cadastro/receita e o vínculo nasce quando um médico o usa.
 */
class PullPacientesTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    private const CHECKPOINT_KEY = 'tiny_contatos_pull_checkpoint';

    private const BACKFILL_CHECKPOINT_KEY = 'tiny_contatos_backfill_checkpoint';

    /** De quantas em quantas horas a ficha completa de um contato conhecido é relida. */
    private const DETALHE_TTL_HORAS_PADRAO = 6;

    /**
     * Contadores da última execução (lidos pelo comando de import).
     *
     * @var array<string,int|bool>
     */
    public array $stats = [];

    /**
     * Callback de progresso por página. Só é usado pelo comando (execução inline);
     * nunca fica setado num job enfileirado.
     *
     * @var callable|null
     */
    public $onProgress = null;

    /** Regra que reconheceu o paciente na última conciliação (alimenta os contadores). */
    private ?string $motivoConciliacao = null;

    /**
     * Propriedades declaradas com valor default (em vez de promovidas no construtor) de
     * propósito: um job desta classe serializado ANTES deste deploy não tem estas chaves no
     * payload, e propriedade tipada promovida ficaria "uninitialized" ao desserializar —
     * `handle()` morreria com Error na primeira leitura. Há retry automático deste job
     * (IntegrationJobFailureRetryService), então esse caso acontece de verdade.
     */
    public bool $backfillCompleto = false;

    public ?int $apiBudget = null;

    public ?int $maxPaginas = null;

    public bool $dryRun = false;

    public function __construct(
        bool $backfillCompleto = false,
        ?int $apiBudget = null,
        ?int $maxPaginas = null,
        bool $dryRun = false,
    ) {
        $this->backfillCompleto = $backfillCompleto;
        $this->apiBudget = $apiBudget;
        $this->maxPaginas = $maxPaginas;
        $this->dryRun = $dryRun;
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        $this->stats = [
            'atualizados' => 0,
            'importados' => 0,
            'conciliados' => 0,
            'conciliados_por' => [],
            'homonimos' => 0,
            'ignorados' => 0,
            'paginas_lidas' => 0,
            'paginas_total' => 0,
            'api_calls' => 0,
            'pausado' => false,
            'concluido' => false,
        ];

        if (! Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Pull de pacientes — integração desabilitada');

            return;
        }

        $client = new TinyErpClient;
        if (! $client->isV2()) {
            Log::info('Tiny ERP: Pull de pacientes — disponível apenas na API V2');

            return;
        }

        // No backfill não há marca d'água: a varredura é da base inteira.
        $dataMinima = $this->backfillCompleto ? null : $this->watermarkCarbon()->format('d/m/Y H:i:s');
        $marcador = $dataMinima ?? 'backfill-completo';
        $checkpoint = $this->loadCheckpoint();

        if ($checkpoint !== null && ($checkpoint['data_minima'] ?? '') === $marcador) {
            $runStart = Carbon::parse((string) $checkpoint['run_start']);
            $pagina = max(1, (int) ($checkpoint['pagina'] ?? 1));
        } else {
            $runStart = Carbon::now();
            $pagina = 1;
            $this->clearCheckpoint();
        }

        // No backfill o import de novos é o objetivo — não depende do setting.
        $importNew = $this->backfillCompleto || $this->truthySetting('tiny_pull_import_new', true);
        $somenteCliente = $this->truthySetting('tiny_pull_somente_tipo_cliente', true);
        $apiBudget = $this->apiBudget !== null
            ? max(1, $this->apiBudget)
            : max(1, (int) Setting::get('tiny_contatos_pull_api_budget', 80));

        $client->resetV2RequestCount()->setV2RequestBudget($apiBudget);

        $numeroPaginas = 1;
        $paginaInicial = $pagina;

        Log::info('Tiny ERP: Iniciando pull de contatos', [
            'modo' => $this->backfillCompleto ? 'backfill_completo' : 'incremental',
            'data_minima_atualizacao' => $dataMinima,
            'import_novos' => $importNew,
            'somente_tipo_cliente' => $somenteCliente,
            'pagina_inicial' => $pagina,
            'api_budget' => $apiBudget,
            'dry_run' => $this->dryRun,
            'retomando_checkpoint' => $checkpoint !== null && ($checkpoint['data_minima'] ?? '') === $marcador,
        ]);

        do {
            $filtros = [
                'pesquisa' => '',
                'pagina' => $pagina,
                'situacao' => 'Ativo',
            ];
            if ($dataMinima !== null) {
                $filtros['dataMinimaAtualizacao'] = $dataMinima;
            }

            $result = $client->listarContatos($filtros);

            if ($this->shouldPauseRun($result, $client, $pagina, $marcador, $runStart)) {
                return;
            }

            if ($result['status'] !== 'success') {
                Log::error('Tiny ERP: Pull pacientes — erro ao listar contatos', [
                    'pagina' => $pagina,
                    'message' => $result['message'] ?? null,
                ]);
                throw new \RuntimeException($result['message'] ?? 'Erro ao listar contatos Tiny');
            }

            $data = $result['data'] ?? [];
            $itens = $data['itens'] ?? [];
            $numeroPaginas = max(1, (int) ($data['paginacao']['numero_paginas'] ?? 1));
            $this->stats['paginas_total'] = $numeroPaginas;

            $existingByTinyId = $this->preloadPacientesByTinyId($itens);

            foreach ($itens as $item) {
                $tinyId = isset($item['id']) ? (int) $item['id'] : null;
                if (! $tinyId) {
                    continue;
                }

                $tinyIdStr = (string) $tinyId;
                $pacienteExistente = $existingByTinyId->get($tinyIdStr);
                $contato = null;
                $fromListOnly = false;

                if ($pacienteExistente !== null && ! $this->precisaLerFichaCompleta($pacienteExistente)) {
                    // Já conhecido e lido faz pouco tempo: a lista basta (economiza 1 chamada).
                    $contato = $item;
                    $fromListOnly = true;
                } elseif ($pacienteExistente === null && ! $importNew) {
                    $this->stats['ignorados']++;

                    continue;
                } else {
                    // A lista NÃO traz celular, data de nascimento, sexo nem tipos_contato — ela
                    // devolve id, nome, cpf_cnpj, e-mail, fone e endereço, e mais nada. Para
                    // contato novo esses campos são o que separa homônimos; para contato conhecido
                    // são justamente os que a clínica corrige no oList e que nunca chegariam aqui.
                    $obter = $client->obterContato($tinyId);
                    if ($this->shouldPauseRun($obter, $client, $pagina, $marcador, $runStart)) {
                        return;
                    }
                    if ($obter['status'] !== 'success' || ! is_array($obter['data'] ?? null)) {
                        Log::warning('Tiny ERP: Pull — falha ao obter contato', [
                            'tiny_id' => $tinyId,
                            'message' => $obter['message'] ?? null,
                        ]);
                        $this->stats['ignorados']++;

                        continue;
                    }
                    $contato = $obter['data'];
                }

                $r = $this->processarContato(
                    $contato,
                    $tinyId,
                    $importNew,
                    $somenteCliente,
                    $fromListOnly,
                    $pacienteExistente
                );
                $this->stats[match ($r) {
                    'updated' => 'atualizados',
                    'imported' => 'importados',
                    'matched' => 'conciliados',
                    default => 'ignorados',
                }]++;
            }

            $this->stats['paginas_lidas'] = $pagina - $paginaInicial + 1;
            $this->stats['api_calls'] = $client->getV2RequestCount();
            if (is_callable($this->onProgress)) {
                ($this->onProgress)($pagina, $numeroPaginas, $this->stats);
            }

            if ($this->maxPaginas !== null && $this->stats['paginas_lidas'] >= $this->maxPaginas) {
                $this->saveCheckpoint($pagina + 1, $marcador, $runStart);
                $this->stats['pausado'] = true;
                Log::info('Tiny ERP: Pull de pacientes pausado (limite de páginas do comando)', $this->stats);

                return;
            }

            $pagina++;
        } while ($pagina <= $numeroPaginas);

        // Backfill não move a marca d'água do incremental (são varreduras independentes).
        if (! $this->backfillCompleto && ! $this->dryRun) {
            Setting::set('tiny_contatos_pull_since', $runStart->copy()->subDays(2)->toIso8601String());
        }
        $this->clearCheckpoint();
        $this->stats['concluido'] = true;
        $this->stats['api_calls'] = $client->getV2RequestCount();

        Log::info('Tiny ERP: Pull de pacientes concluído', $this->stats);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function shouldPauseRun(
        array $result,
        TinyErpClient $client,
        int $pagina,
        string $marcador,
        Carbon $runStart
    ): bool {
        if (TinyErpClient::isBudgetExhaustedError($result) || TinyErpClient::isRateLimitError($result)) {
            $this->saveCheckpoint($pagina, $marcador, $runStart);
            $this->stats['pausado'] = true;
            $this->stats['api_calls'] = $client->getV2RequestCount();
            Log::info('Tiny ERP: Pull de pacientes pausado (budget ou rate limit)', [
                'pagina' => $pagina,
                'motivo' => $result['message'] ?? null,
                'api_calls' => $client->getV2RequestCount(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return Collection<string, Paciente>
     */
    private function preloadPacientesByTinyId(array $itens): Collection
    {
        $tinyIds = collect($itens)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if ($tinyIds === []) {
            return collect();
        }

        return Paciente::query()
            ->whereIn('tiny_id', $tinyIds)
            ->get()
            ->keyBy(fn (Paciente $p) => (string) $p->tiny_id);
    }

    /**
     * @return array{pagina: int, data_minima: string, run_start: string}|null
     */
    private function loadCheckpoint(): ?array
    {
        $raw = Setting::get($this->checkpointKey());
        if (! $raw) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($raw) ? $raw : null;
    }

    private function checkpointKey(): string
    {
        return $this->backfillCompleto ? self::BACKFILL_CHECKPOINT_KEY : self::CHECKPOINT_KEY;
    }

    /**
     * Em dry-run o checkpoint não é tocado: uma simulação não pode fazer a próxima execução
     * real pular páginas, nem apagar o checkpoint de uma varredura em andamento.
     */
    private function saveCheckpoint(int $pagina, string $marcador, Carbon $runStart): void
    {
        if ($this->dryRun) {
            return;
        }

        Setting::set($this->checkpointKey(), json_encode([
            'pagina' => $pagina,
            'data_minima' => $marcador,
            'run_start' => $runStart->toIso8601String(),
        ]));
    }

    private function clearCheckpoint(): void
    {
        if ($this->dryRun) {
            return;
        }

        Setting::set($this->checkpointKey(), null);
    }

    private function watermarkCarbon(): Carbon
    {
        $raw = Setting::get('tiny_contatos_pull_since');
        if ($raw) {
            try {
                return Carbon::parse((string) $raw);
            } catch (\Throwable) {
                // usar default abaixo
            }
        }

        return Carbon::now()->subDay();
    }

    private function truthySetting(string $key, bool $default): bool
    {
        $v = Setting::get($key, null);
        if ($v === null || $v === '') {
            return $default;
        }
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'yes', 'on', 'sim'], true);
    }

    /**
     * @return 'updated'|'imported'|'matched'|'skipped'
     */
    private function processarContato(
        array $contato,
        int $tinyId,
        bool $importNew,
        bool $somenteCliente,
        bool $fromListOnly,
        ?Paciente $paciente = null
    ): string {
        $digits = TinyContatoMapper::onlyDigitsCpfCnpj($contato);
        $tipo = strtoupper(trim((string) ($contato['tipo_pessoa'] ?? $contato['tipoPessoa'] ?? '')));
        $tinyIdStr = (string) $tinyId;

        $paciente ??= Paciente::query()->where('tiny_id', $tinyIdStr)->first();

        $tinyDt = $fromListOnly
            ? Carbon::now()
            : TinyContatoMapper::parseDataAtualizacao(TinyContatoMapper::contatoDataAtualizacaoRaw($contato));

        if ($paciente) {
            if (! $fromListOnly && $tinyDt && $paciente->tiny_updated_at && $tinyDt->lte($paciente->tiny_updated_at)) {
                // Nada mudou no oList desde a última leitura. Ainda assim registra que a ficha
                // completa foi lida agora, senão a próxima rodada gastaria a chamada de novo.
                $this->salvar(fn () => $paciente->forceFill(['tiny_detalhe_sync_at' => Carbon::now()])->saveQuietly());

                return 'skipped';
            }

            $includeCpf = strlen($digits) === 11;
            if (! $includeCpf) {
                Log::info('Tiny ERP: Pull — contato sem CPF PF válido; mantém CPF local ao atualizar', [
                    'paciente_id' => $paciente->id,
                    'tiny_id' => $tinyId,
                ]);
            }

            $attrs = TinyContatoMapper::toPacienteAttributes($contato, $includeCpf);
            $this->resolverEmailImportado($attrs, $contato, $paciente);
            $attrs['tiny_sync_at'] = Carbon::now();
            $attrs['tiny_updated_at'] = $tinyDt ?? Carbon::now();
            if (! $fromListOnly) {
                $attrs['tiny_detalhe_sync_at'] = Carbon::now();
            }

            $this->salvar(fn () => $paciente->update($attrs));

            return 'updated';
        }

        if (! $importNew) {
            return 'skipped';
        }

        // Pessoa jurídica não é paciente. `E` (estrangeiro) e tipo vazio entram.
        if ($tipo === 'J' || strlen($digits) === 14) {
            return 'skipped';
        }

        if ($somenteCliente && ! $fromListOnly && ! TinyContatoMapper::contatoTemTipoCliente($contato)) {
            return 'skipped';
        }

        $nome = trim((string) ($contato['nome'] ?? ''));
        if ($nome === '') {
            return 'skipped';
        }

        // Cliente sem CPF ENTRA (é 1 em cada 4 no oList) — antes era descartado aqui.
        $existente = $this->localizarPacienteEquivalente($contato, $digits);

        $attrs = TinyContatoMapper::toPacienteAttributes($contato, strlen($digits) === 11);
        $this->resolverEmailImportado($attrs, $contato, $existente);
        $attrs['tiny_sync_at'] = Carbon::now();
        $attrs['tiny_updated_at'] = $tinyDt ?? Carbon::now();

        if ($existente) {
            if ($existente->tiny_id !== null && $existente->tiny_id !== '' && (string) $existente->tiny_id !== $tinyIdStr) {
                Log::warning('Tiny ERP: Pull — paciente equivalente já vinculado a outro tiny_id', [
                    'paciente_id' => $existente->id,
                    'tiny_id_existente' => $existente->tiny_id,
                    'tiny_id_novo' => $tinyId,
                ]);

                return 'skipped';
            }

            $attrs['tiny_id'] = $tinyIdStr;
            // Não sobrescreve o nome de um cadastro que já existe aqui: o nome local é o
            // que o médico reconhece na busca.
            unset($attrs['nome']);

            // CPF local nunca é sobrescrito. Se chegou aqui com CPF, foi por regra fraca
            // (celular/nascimento) — justamente porque os CPFs não batem —, e gravar o do
            // oList colaria o CPF de outra pessoa no cadastro.
            if (filled($existente->cpf)) {
                unset($attrs['cpf']);
            }

            // Qual regra reconheceu o paciente — dá para ver, na saída do comando, o quanto
            // cada critério está segurando de cadastro repetido.
            $motivo = $this->motivoConciliacao ?? 'desconhecido';
            $this->stats['conciliados_por'][$motivo] = ($this->stats['conciliados_por'][$motivo] ?? 0) + 1;

            $this->salvar(fn () => $existente->update($attrs));

            return 'matched';
        }

        $attrs['nome'] = mb_substr($nome, 0, 255);
        $attrs['tiny_id'] = $tinyIdStr;
        $attrs['ativo'] = true;

        // Homônimo que a conciliação não conseguiu confirmar (sem CPF, e-mail, celular nem
        // data em comum): pode ser a mesma pessoa ou outro "João da Silva". Fica registrado —
        // quem resolve é o médico, na busca por nome, que mostra os dois lado a lado.
        $homonimo = Paciente::query()
            ->whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower(trim($nome))])
            ->first();
        if ($homonimo) {
            $this->stats['homonimos'] = ($this->stats['homonimos'] ?? 0) + 1;
            Log::info('Tiny ERP: Pull — importado com homônimo já cadastrado', [
                'tiny_id' => $tinyId,
                'paciente_id_homonimo' => $homonimo->id,
            ]);
        }

        $this->salvar(fn () => Paciente::query()->create($attrs));

        return 'imported';
    }

    /**
     * E-mail do contato importado do oList.
     *
     * Decisão do cliente (job b6a8f395): o e-mail de marcação vale AQUI, na importação —
     * o cadastro chega com `<celular>@cadastraremail.rsk` e as atendentes enxergam de cara
     * quem ainda precisa de e-mail de verdade. Em cadastro novo feito daqui não se gera
     * nada: o campo ficou opcional justamente para não sujar a base.
     *
     * Duas travas:
     *   - e-mail de verdade que já está aqui nunca é trocado por marcação vinda do oList;
     *   - sem telefone não há placeholder — o cadastro entra sem e-mail, que agora é válido.
     */
    private function resolverEmailImportado(array &$attrs, array $contato, ?Paciente $local): void
    {
        $novo = $attrs['email1'] ?? null;

        if ($local !== null
            && $novo !== null
            && EmailPlaceholder::ehPlaceholder($novo)
            && filled($local->email1)
            && ! EmailPlaceholder::ehPlaceholder($local->email1)) {
            unset($attrs['email1']);

            return;
        }

        if ($novo !== null) {
            return;
        }

        // Cadastro que já existe aqui e não tem e-mail: também ganha a marcação, senão a
        // conciliação deixaria justamente os antigos de fora da lista de revisão.
        if ($local !== null && filled($local->email1)) {
            return;
        }

        $placeholder = EmailPlaceholder::gerar(
            $contato['celular'] ?? null,
            $contato['fone'] ?? $contato['telefone'] ?? null,
        );

        if ($placeholder !== null) {
            $attrs['email1'] = $placeholder;
        }
    }

    /**
     * Escrita sem eventos: o PacienteObserver empurraria a alteração de volta para o oList.
     */
    private function salvar(callable $fn): void
    {
        if ($this->dryRun) {
            return;
        }

        Paciente::withoutEvents($fn);
    }

    /**
     * Identidade do contato do oList dentro do nosso banco, do mais forte para o mais fraco:
     *   1. CPF;
     *   2. e-mail + data de nascimento;
     *   3. e-mail que pertence a UM único paciente, com nome compatível;
     *   4. mesmo celular + nome compatível;
     *   5. mesma data de nascimento + nome compatível.
     *
     * Os passos 3–5 existem porque a base daqui tem 94% dos pacientes sem CPF e a maioria sem
     * e-mail: medido na 1ª página do oList, sem eles 63 dos 85 "novos" eram gente que já
     * estava cadastrada. Duplicar seria pior do que não importar — é exatamente a confusão que
     * a busca por nome tenta evitar.
     *
     * Em todos os passos fracos o nome tem de ser compatível (mesmo primeiro e último nome),
     * o que impede fundir mãe e filha que dividem e-mail ou celular.
     */
    private function localizarPacienteEquivalente(array $contato, string $digits): ?Paciente
    {
        $this->motivoConciliacao = null;

        if (strlen($digits) === 11) {
            $porCpf = $this->findPacienteByCpfDigits($digits);
            if ($porCpf) {
                $this->motivoConciliacao = 'cpf';

                return $porCpf;
            }
        }

        $nome = (string) ($contato['nome'] ?? '');
        $email = mb_strtolower(trim((string) ($contato['email'] ?? '')));
        // E-mail de marcação não identifica ninguém: ele é derivado do telefone e mais da
        // metade dos contatos do oList tem um. Casar por ele seria casar por telefone, mas
        // SEM a conferência de nome que a regra de celular faz — fundiria mãe e filha.
        $temEmail = $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! EmailPlaceholder::ehPlaceholder($email);
        $nascimento = TinyContatoMapper::parseDataNascimento(
            $contato['data_nascimento'] ?? $contato['dataNascimento'] ?? null
        );

        if ($temEmail && $nascimento !== null) {
            $porEmailNascimento = Paciente::query()
                ->whereRaw('LOWER(TRIM(COALESCE(email1, ""))) = ?', [$email])
                ->whereDate('data_nascimento', $nascimento)
                ->first();
            if ($porEmailNascimento) {
                $this->motivoConciliacao = 'email+nascimento';

                return $porEmailNascimento;
            }
        }

        if ($temEmail) {
            $porEmail = Paciente::query()
                ->whereRaw('LOWER(TRIM(COALESCE(email1, ""))) = ?', [$email])
                ->limit(2)
                ->get();

            if ($porEmail->count() === 1
                && NomePaciente::compativeis($nome, (string) $porEmail->first()->nome)) {
                $this->motivoConciliacao = 'email+nome';

                return $porEmail->first();
            }
        }

        // Busca pelos 8 dígitos finais e confere DDD + nome em memória: é o que faz `(48) 9907-2096`
        // do oList reencontrar o `(48) 99907-2096` daqui sem colar duas pessoas de estados
        // diferentes. Ver App\Support\TelefonePaciente.
        $celular = (string) ($contato['celular'] ?? $contato['fone'] ?? '');
        $chaveFone = TelefonePaciente::chave($celular);
        if ($chaveFone !== null) {
            $ultimos8 = TelefonePaciente::ultimos8($celular);
            $porCelular = Paciente::query()
                ->where(function ($q) use ($ultimos8) {
                    foreach (['celular', 'telefone1'] as $coluna) {
                        $limpo = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$coluna}, ''), '(', ''), ')', ''), '-', ''), ' ', '')";
                        // SUBSTR com posição negativa pega os últimos 8 no MySQL e no SQLite dos testes.
                        $q->orWhereRaw("(SUBSTR({$limpo}, -8) = ? AND LENGTH({$limpo}) >= 10)", [$ultimos8]);
                    }
                })
                ->limit(30)
                ->get()
                ->first(fn (Paciente $p) => NomePaciente::compativeis($nome, (string) $p->nome)
                    && (TelefonePaciente::iguais($celular, $p->celular)
                        || TelefonePaciente::iguais($celular, $p->telefone1)));

            if ($porCelular) {
                $this->motivoConciliacao = 'celular+nome';

                return $porCelular;
            }
        }

        if ($nascimento !== null) {
            $porNascimento = Paciente::query()
                ->whereDate('data_nascimento', $nascimento)
                ->limit(50)
                ->get()
                ->first(fn (Paciente $p) => NomePaciente::compativeis($nome, (string) $p->nome));

            if ($porNascimento) {
                $this->motivoConciliacao = 'nascimento+nome';

                return $porNascimento;
            }
        }

        return null;
    }

    /**
     * Vale gastar uma chamada de `contato.obter` num paciente que já conhecemos?
     *
     * A lista do oList devolve nome, e-mail, fone e endereço — **não** devolve data de nascimento,
     * celular nem sexo. Enquanto o pull se contentou com a lista, correção feita no oList nesses
     * três campos nunca chegava aqui: em 13/08/2026 a clínica corrigiu 69 datas de nascimento, o
     * nome de cada uma sincronizou na mesma rodada e a data continuou errada na ClinicaWeb.
     *
     * Ler a ficha completa toda rodada não dá: o filtro do oList é por DIA, então um contato
     * alterado hoje volta na lista a cada 10 minutos por dois dias. Daí a validade — cada contato
     * conhecido custa no máximo uma chamada por período, e a correção aparece aqui em algumas horas.
     */
    private function precisaLerFichaCompleta(Paciente $paciente): bool
    {
        $horas = (int) Setting::get('tiny_contato_detalhe_ttl_horas', self::DETALHE_TTL_HORAS_PADRAO);

        if ($horas <= 0) {
            return true;
        }

        return $paciente->tiny_detalhe_sync_at === null
            || $paciente->tiny_detalhe_sync_at->lt(Carbon::now()->subHours($horas));
    }

    /**
     * @deprecated Use App\Support\NomePaciente::compativeis(); mantido pelos testes existentes.
     */
    public static function nomesCompativeis(string $a, string $b): bool
    {
        return NomePaciente::compativeis($a, $b);
    }

    private function findPacienteByCpfDigits(string $digits): ?Paciente
    {
        $br = TinyContatoMapper::formatCpfBr11($digits);

        return Paciente::query()
            ->where(function ($q) use ($digits, $br) {
                $q->where('cpf', $br)
                    ->orWhere('cpf', $digits)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', ''), ' ', '') = ?", [$digits]);
            })
            ->first();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Pull de pacientes falhou', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
