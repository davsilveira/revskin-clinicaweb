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
use Illuminate\Support\Facades\Log;
use Throwable;

class PullPacientesTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

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

        $runStart = Carbon::now();
        $since = $this->watermarkCarbon();
        $dataMinima = $since->format('d/m/Y H:i:s');

        $importNew = $this->truthySetting('tiny_pull_import_new', false);
        $somenteCliente = $this->truthySetting('tiny_pull_somente_tipo_cliente', true);

        $pagina = 1;
        $numeroPaginas = 1;
        $updated = 0;
        $imported = 0;
        $skipped = 0;

        Log::info('Tiny ERP: Iniciando pull de contatos', [
            'data_minima_atualizacao' => $dataMinima,
            'import_novos' => $importNew,
            'somente_tipo_cliente' => $somenteCliente,
        ]);

        do {
            $result = $client->listarContatos([
                'pesquisa' => '',
                'pagina' => $pagina,
                'dataMinimaAtualizacao' => $dataMinima,
                'situacao' => 'Ativo',
            ]);

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

            foreach ($itens as $item) {
                $tinyId = isset($item['id']) ? (int) $item['id'] : null;
                if (! $tinyId) {
                    continue;
                }

                $obter = $client->obterContato($tinyId);
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

                $r = $this->processarContato($contato, $tinyId, $importNew, $somenteCliente);
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

        Log::info('Tiny ERP: Pull de pacientes concluído', [
            'atualizados' => $updated,
            'importados' => $imported,
            'ignorados' => $skipped,
        ]);
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

        // Primeira execução: só último dia (job diário; evita pico de chamadas na API)
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
    private function processarContato(array $contato, int $tinyId, bool $importNew, bool $somenteCliente): string
    {
        $digits = TinyContatoMapper::onlyDigitsCpfCnpj($contato);
        $tipo = (string) ($contato['tipo_pessoa'] ?? $contato['tipoPessoa'] ?? '');
        $tinyIdStr = (string) $tinyId;

        $paciente = Paciente::query()->where('tiny_id', $tinyIdStr)->first();

        $tinyDt = TinyContatoMapper::parseDataAtualizacao(TinyContatoMapper::contatoDataAtualizacaoRaw($contato));

        if ($paciente) {
            if ($tinyDt && $paciente->tiny_updated_at && $tinyDt->lte($paciente->tiny_updated_at)) {
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

        if ($somenteCliente && ! TinyContatoMapper::contatoTemTipoCliente($contato)) {
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
