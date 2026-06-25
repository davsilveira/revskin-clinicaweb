<?php

namespace App\Jobs;

use App\Models\Paciente;
use App\Models\Setting;
use App\Services\TinyContatoMapper;
use App\Services\TinyErpClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PullPacientesTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    private const CHECKPOINT_KEY = 'tiny_contatos_pull_checkpoint';

    public function __construct()
    {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        if (! Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Pull de pacientes — integração desabilitada');

            return;
        }

        $client = new TinyErpClient;
        if (! $client->isV2()) {
            Log::info('Tiny ERP: Pull de pacientes — disponível apenas na API V2');

            return;
        }

        $since = $this->watermarkCarbon();
        $dataMinima = $since->format('d/m/Y H:i:s');
        $checkpoint = $this->loadCheckpoint();

        if ($checkpoint !== null && ($checkpoint['data_minima'] ?? '') === $dataMinima) {
            $runStart = Carbon::parse((string) $checkpoint['run_start']);
            $pagina = max(1, (int) ($checkpoint['pagina'] ?? 1));
        } else {
            $runStart = Carbon::now();
            $pagina = 1;
            $this->clearCheckpoint();
        }

        $importNew = $this->truthySetting('tiny_pull_import_new', false);
        $somenteCliente = $this->truthySetting('tiny_pull_somente_tipo_cliente', true);
        $apiBudget = max(1, (int) Setting::get('tiny_contatos_pull_api_budget', 80));

        $client->resetV2RequestCount()->setV2RequestBudget($apiBudget);

        $numeroPaginas = 1;
        $updated = 0;
        $imported = 0;
        $skipped = 0;

        Log::info('Tiny ERP: Iniciando pull de contatos', [
            'data_minima_atualizacao' => $dataMinima,
            'import_novos' => $importNew,
            'somente_tipo_cliente' => $somenteCliente,
            'pagina_inicial' => $pagina,
            'api_budget' => $apiBudget,
            'retomando_checkpoint' => $checkpoint !== null && ($checkpoint['data_minima'] ?? '') === $dataMinima,
        ]);

        do {
            $result = $client->listarContatos([
                'pesquisa' => '',
                'pagina' => $pagina,
                'dataMinimaAtualizacao' => $dataMinima,
                'situacao' => 'Ativo',
            ]);

            if ($this->shouldPauseRun($result, $client, $pagina, $dataMinima, $runStart)) {
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

                if ($pacienteExistente !== null) {
                    $contato = $item;
                    $fromListOnly = true;
                } elseif (! $importNew) {
                    $skipped++;

                    continue;
                } elseif ($somenteCliente) {
                    $obter = $client->obterContato($tinyId);
                    if ($this->shouldPauseRun($obter, $client, $pagina, $dataMinima, $runStart)) {
                        return;
                    }
                    if ($obter['status'] !== 'success') {
                        Log::warning('Tiny ERP: Pull — falha ao obter contato', [
                            'tiny_id' => $tinyId,
                            'message' => $obter['message'] ?? null,
                        ]);
                        $skipped++;

                        continue;
                    }
                    $contato = $obter['data'] ?? [];
                    if (! is_array($contato)) {
                        $skipped++;

                        continue;
                    }
                } else {
                    $contato = $item;
                    $fromListOnly = true;
                }

                $r = $this->processarContato(
                    $contato,
                    $tinyId,
                    $importNew,
                    $somenteCliente,
                    $fromListOnly,
                    $pacienteExistente
                );
                if ($r === 'updated') {
                    $updated++;
                } elseif ($r === 'imported') {
                    $imported++;
                } else {
                    $skipped++;
                }
            }

            $pagina++;
        } while ($pagina <= $numeroPaginas);

        Setting::set('tiny_contatos_pull_since', $runStart->copy()->subDays(2)->toIso8601String());
        $this->clearCheckpoint();

        Log::info('Tiny ERP: Pull de pacientes concluído', [
            'atualizados' => $updated,
            'importados' => $imported,
            'ignorados' => $skipped,
            'api_calls' => $client->getV2RequestCount(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function shouldPauseRun(
        array $result,
        TinyErpClient $client,
        int $pagina,
        string $dataMinima,
        Carbon $runStart
    ): bool {
        if (TinyErpClient::isBudgetExhaustedError($result) || TinyErpClient::isRateLimitError($result)) {
            $this->saveCheckpoint($pagina, $dataMinima, $runStart);
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
        $raw = Setting::get(self::CHECKPOINT_KEY);
        if (! $raw) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($raw) ? $raw : null;
    }

    private function saveCheckpoint(int $pagina, string $dataMinima, Carbon $runStart): void
    {
        Setting::set(self::CHECKPOINT_KEY, json_encode([
            'pagina' => $pagina,
            'data_minima' => $dataMinima,
            'run_start' => $runStart->toIso8601String(),
        ]));
    }

    private function clearCheckpoint(): void
    {
        Setting::set(self::CHECKPOINT_KEY, null);
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
     * @return 'updated'|'imported'|'skipped'
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
        $tipo = (string) ($contato['tipo_pessoa'] ?? $contato['tipoPessoa'] ?? '');
        $tinyIdStr = (string) $tinyId;

        $paciente ??= Paciente::query()->where('tiny_id', $tinyIdStr)->first();

        $tinyDt = $fromListOnly
            ? Carbon::now()
            : TinyContatoMapper::parseDataAtualizacao(TinyContatoMapper::contatoDataAtualizacaoRaw($contato));

        if ($paciente) {
            if (! $fromListOnly && $tinyDt && $paciente->tiny_updated_at && $tinyDt->lte($paciente->tiny_updated_at)) {
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
            $attrs['tiny_sync_at'] = Carbon::now();
            $attrs['tiny_updated_at'] = $tinyDt ?? Carbon::now();

            Paciente::withoutEvents(function () use ($paciente, $attrs) {
                $paciente->update($attrs);
            });

            return 'updated';
        }

        if (! $importNew) {
            return 'skipped';
        }

        if ($tipo !== 'F') {
            return 'skipped';
        }

        if ($somenteCliente && ! $fromListOnly && ! TinyContatoMapper::contatoTemTipoCliente($contato)) {
            return 'skipped';
        }

        if (strlen($digits) !== 11) {
            Log::debug('Tiny ERP: Pull — ignorado import (sem CPF de 11 dígitos)', ['tiny_id' => $tinyId]);

            return 'skipped';
        }

        $existing = $this->findPacienteByCpfDigits($digits);
        if ($existing) {
            if ($existing->tiny_id !== null && $existing->tiny_id !== '' && (string) $existing->tiny_id !== $tinyIdStr) {
                Log::warning('Tiny ERP: Pull — CPF já vinculado a outro tiny_id', [
                    'paciente_id' => $existing->id,
                    'tiny_id_existente' => $existing->tiny_id,
                    'tiny_id_novo' => $tinyId,
                ]);

                return 'skipped';
            }

            $attrs = TinyContatoMapper::toPacienteAttributes($contato, true);
            $attrs['tiny_id'] = $tinyIdStr;
            $attrs['tiny_sync_at'] = Carbon::now();
            $attrs['tiny_updated_at'] = $tinyDt ?? Carbon::now();

            Paciente::withoutEvents(function () use ($existing, $attrs) {
                $existing->update($attrs);
            });

            return 'imported';
        }

        if (trim((string) ($contato['nome'] ?? '')) === '') {
            return 'skipped';
        }

        $attrs = TinyContatoMapper::toPacienteAttributes($contato, true);
        $attrs['tiny_id'] = $tinyIdStr;
        $attrs['tiny_sync_at'] = Carbon::now();
        $attrs['tiny_updated_at'] = $tinyDt ?? Carbon::now();
        $attrs['ativo'] = true;

        Paciente::withoutEvents(function () use ($attrs) {
            Paciente::query()->create($attrs);
        });

        return 'imported';
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
