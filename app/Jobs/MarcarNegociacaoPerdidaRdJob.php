<?php

namespace App\Jobs;

use App\Models\Receita;
use App\Models\Setting;
use App\Services\RdStationCrmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarcarNegociacaoPerdidaRdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public Receita $receita
    ) {
        $this->onQueue('rd-sync');
    }

    public function handle(): void
    {
        if (! Setting::get('rd_enabled', false)) {
            Log::info('RD Station CRM: Integração desabilitada', [
                'receita_id' => $this->receita->id,
            ]);

            return;
        }

        $receita = $this->receita->fresh();

        if (! filled($receita?->rd_deal_id)) {
            Log::info('RD Station CRM: Receita sem rd_deal_id, ignorando marcação de perdida', [
                'receita_id' => $receita?->id,
            ]);

            return;
        }

        $fieldId = trim((string) Setting::get('rd_cancelamento_field_id', ''));
        $fieldValue = trim((string) Setting::get('rd_cancelamento_field_value', ''));

        if ($fieldId === '' || $fieldValue === '') {
            Log::warning('RD Station CRM: Campo de cancelamento não configurado', [
                'receita_id' => $receita->id,
            ]);

            return;
        }

        $client = new RdStationCrmClient;
        $fieldKey = $client->resolverChaveCustomField($fieldId);

        $result = $client->atualizarNegociacao((string) $receita->rd_deal_id, [
            'custom_fields' => [
                $fieldKey => $fieldValue,
            ],
        ]);

        if ($result['status'] !== 'success') {
            Log::error('RD Station CRM: Falha ao marcar negociação para cancelamento', [
                'receita_id' => $receita->id,
                'rd_deal_id' => $receita->rd_deal_id,
                'error' => $result['message'] ?? null,
                'status_code' => $result['status_code'] ?? null,
            ]);
            throw new \Exception('Erro ao atualizar negociação no RD Station: '.($result['message'] ?? ''));
        }

        Log::info('RD Station CRM: Campo de cancelamento atualizado na negociação', [
            'receita_id' => $receita->id,
            'rd_deal_id' => $receita->rd_deal_id,
            'field_key' => $fieldKey,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RD Station CRM: Job de marcação de negociação perdida falhou', [
            'receita_id' => $this->receita->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
