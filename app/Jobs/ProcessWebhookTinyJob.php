<?php

namespace App\Jobs;

use App\Models\AtendimentoCallcenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public string $pedidoId,
        public ?string $situacao,
        public array $payload
    ) {
        $this->onQueue('tiny-webhooks');
    }

    public function handle(): void
    {
        Log::info('Tiny ERP: Processando webhook de pedido finalizado', [
            'pedido_id' => $this->pedidoId,
            'situacao' => $this->situacao,
        ]);

        // Buscar atendimento pelo tiny_pedido_id
        $atendimento = AtendimentoCallcenter::where('tiny_pedido_id', $this->pedidoId)->first();

        if (!$atendimento) {
            Log::warning('Tiny ERP: Atendimento não encontrado para pedido', [
                'tiny_pedido_id' => $this->pedidoId,
            ]);
            return;
        }

        // Verificar se situação indica pedido finalizado
        // Situações possíveis no Tiny: "finalizado", "faturado", "enviado", etc.
        $situacoesFinalizadas = ['finalizado', 'faturado', 'enviado', 'concluido'];

        if ($this->situacao && in_array(strtolower($this->situacao), $situacoesFinalizadas)) {
            // Atualizar atendimento para finalizado
            if ($atendimento->status !== AtendimentoCallcenter::STATUS_FINALIZADO) {
                $atendimento->update([
                    'status' => AtendimentoCallcenter::STATUS_FINALIZADO,
                    'tiny_situacao' => $this->situacao,
                    'tiny_sync_at' => now(),
                    'data_alteracao' => now(),
                ]);

                Log::info('Tiny ERP: Atendimento atualizado para finalizado via webhook', [
                    'atendimento_id' => $atendimento->id,
                    'tiny_pedido_id' => $this->pedidoId,
                    'situacao' => $this->situacao,
                ]);
            }
        } else {
            // Atualizar apenas a situação do Tiny
            $atendimento->update([
                'tiny_situacao' => $this->situacao,
                'tiny_sync_at' => now(),
            ]);

            Log::info('Tiny ERP: Situação do atendimento atualizada via webhook', [
                'atendimento_id' => $atendimento->id,
                'tiny_pedido_id' => $this->pedidoId,
                'situacao' => $this->situacao,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de processamento de webhook falhou', [
            'pedido_id' => $this->pedidoId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
