<?php

namespace App\Jobs;

use App\Models\Receita;
use App\Models\Setting;
use App\Services\TinyErpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelarPedidoTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public Receita $receita
    ) {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        if (! Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Cancelamento de pedido desabilitado', [
                'receita_id' => $this->receita->id,
            ]);

            return;
        }

        $receita = $this->receita->fresh(['atendimentoCallcenter']);
        $tinyPedidoId = trim((string) ($receita->tiny_pedido_id ?? ''));
        if ($tinyPedidoId === '') {
            $tinyPedidoId = trim((string) ($receita->atendimentoCallcenter?->tiny_pedido_id ?? ''));
        }

        if ($tinyPedidoId === '') {
            Log::info('Tiny ERP: Receita sem tiny_pedido_id, nada a cancelar', [
                'receita_id' => $receita->id,
            ]);

            return;
        }

        Log::info('Tiny ERP: CancelarPedidoTinyJob iniciado', [
            'receita_id' => $receita->id,
            'receita_numero' => $receita->numero,
            'tiny_pedido_id' => $tinyPedidoId,
        ]);

        $client = new TinyErpClient;
        $pedidoAtual = $client->obterPedido((int) $tinyPedidoId);

        if ($pedidoAtual['status'] === 'success') {
            $situacao = $pedidoAtual['data']['situacao'] ?? null;
            if (TinyErpClient::isSituacaoPedidoCancelada($situacao)) {
                Log::info('Tiny ERP: Pedido já cancelado no Tiny, ignorando', [
                    'receita_id' => $receita->id,
                    'tiny_pedido_id' => $tinyPedidoId,
                    'situacao' => $situacao,
                ]);
                $this->atualizarSituacaoLocal($receita, $situacao);

                return;
            }
        }

        $result = $client->cancelarPedido((int) $tinyPedidoId);

        if ($result['status'] !== 'success') {
            Log::error('Tiny ERP: Erro ao cancelar pedido', [
                'receita_id' => $receita->id,
                'tiny_pedido_id' => $tinyPedidoId,
                'error' => $result['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception($result['message'] ?? 'Erro ao cancelar pedido no Tiny');
        }

        $this->atualizarSituacaoLocal($receita, 'cancelado');

        Log::info('Tiny ERP: Pedido cancelado com sucesso', [
            'receita_id' => $receita->id,
            'tiny_pedido_id' => $tinyPedidoId,
        ]);
    }

    protected function atualizarSituacaoLocal(Receita $receita, mixed $situacao): void
    {
        $atendimento = $receita->atendimentoCallcenter;
        if ($atendimento) {
            $atendimento->update([
                'tiny_situacao' => is_scalar($situacao) ? (string) $situacao : 'cancelado',
                'tiny_sync_at' => now(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de cancelamento de pedido falhou', [
            'receita_id' => $this->receita->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
