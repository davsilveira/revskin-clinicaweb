<?php

namespace App\Jobs;

use App\Models\Receita;
use App\Models\Setting;
use App\Services\ReceitaCancelamentoService;
use App\Services\TinyPedidoSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookRdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $dealId,
        public string $status,
        public string $transactionUuid,
        public array $payload = []
    ) {
        $this->onQueue('rd-webhooks');
    }

    public function handle(): void
    {
        $cacheKey = 'rd_webhook_tx:'.$this->transactionUuid;
        if (Cache::has($cacheKey)) {
            Log::info('RD Station CRM: Webhook duplicado ignorado', [
                'transaction_uuid' => $this->transactionUuid,
            ]);

            return;
        }

        Cache::put($cacheKey, true, now()->addDay());

        $motivo = self::motivoCancelamento($this->status, $this->payload);
        if ($motivo === null) {
            Log::info('RD Station CRM: Webhook ignorado — sem gatilho de cancelamento', [
                'deal_id' => $this->dealId,
                'status' => $this->status,
                'custom_fields' => self::resumirCustomFields($this->payload),
            ]);

            return;
        }

        $receita = Receita::query()->where('rd_deal_id', $this->dealId)->first();

        if (! $receita) {
            Log::info('RD Station CRM: Nenhuma receita vinculada à negociação', [
                'deal_id' => $this->dealId,
                'motivo' => $motivo,
            ]);

            return;
        }

        if ($receita->status === 'cancelada') {
            Log::info('RD Station CRM: Receita já cancelada', [
                'receita_id' => $receita->id,
                'deal_id' => $this->dealId,
                'motivo' => $motivo,
            ]);

            return;
        }

        ReceitaCancelamentoService::cancelarReceita($receita);
        TinyPedidoSync::agendarCancelamento($receita->fresh());

        Log::info('RD Station CRM: Receita cancelada a partir do webhook', [
            'receita_id' => $receita->id,
            'deal_id' => $this->dealId,
            'motivo' => $motivo,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RD Station CRM: Job de processamento de webhook falhou', [
            'deal_id' => $this->dealId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function motivoCancelamento(string $status, array $payload): ?string
    {
        if ($status === 'lost') {
            return 'lost';
        }

        if (self::campoCancelamentoBate($payload)) {
            return 'custom_field';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function campoCancelamentoBate(array $payload): bool
    {
        $fieldId = trim((string) Setting::get('rd_cancelamento_field_id', ''));
        $fieldValue = trim((string) Setting::get('rd_cancelamento_field_value', ''));

        if ($fieldId === '' || $fieldValue === '') {
            return false;
        }

        $document = $payload['document'] ?? [];
        $fields = $document['deal_custom_fields'] ?? [];

        if (! is_array($fields)) {
            return false;
        }

        foreach ($fields as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $customField = $entry['custom_field'] ?? [];
            $id = is_array($customField) ? (string) ($customField['id'] ?? '') : '';

            if ($id !== $fieldId) {
                continue;
            }

            $value = $entry['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if ((string) $value === $fieldValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function resumirCustomFields(array $payload): array
    {
        $document = $payload['document'] ?? [];
        $fields = $document['deal_custom_fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        $resumo = [];

        foreach ($fields as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $customField = $entry['custom_field'] ?? [];
            $label = is_array($customField)
                ? (string) ($customField['label'] ?? $customField['id'] ?? 'campo')
                : 'campo';

            $resumo[$label] = $entry['value'] ?? null;
        }

        return $resumo;
    }
}
