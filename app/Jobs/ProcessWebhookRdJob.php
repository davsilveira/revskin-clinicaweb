<?php

namespace App\Jobs;

use App\Models\Receita;
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

        if ($this->status !== 'lost') {
            Log::info('RD Station CRM: Webhook ignorado — status não é lost', [
                'deal_id' => $this->dealId,
                'status' => $this->status,
            ]);

            return;
        }

        $receita = Receita::query()->where('rd_deal_id', $this->dealId)->first();

        if (! $receita) {
            Log::info('RD Station CRM: Nenhuma receita vinculada à negociação', [
                'deal_id' => $this->dealId,
            ]);

            return;
        }

        if ($receita->status === 'cancelada') {
            Log::info('RD Station CRM: Receita já cancelada', [
                'receita_id' => $receita->id,
                'deal_id' => $this->dealId,
            ]);

            return;
        }

        ReceitaCancelamentoService::cancelarReceita($receita);
        TinyPedidoSync::agendarCancelamento($receita->fresh());

        Log::info('RD Station CRM: Receita cancelada a partir do webhook', [
            'receita_id' => $receita->id,
            'deal_id' => $this->dealId,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RD Station CRM: Job de processamento de webhook falhou', [
            'deal_id' => $this->dealId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
