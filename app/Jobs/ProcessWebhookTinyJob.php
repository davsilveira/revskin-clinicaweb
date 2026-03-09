<?php

namespace App\Jobs;

use App\Models\AtendimentoCallcenter;
use App\Models\Receita;
use App\Models\ReceitaItemAquisicao;
use App\Services\TinyErpClient;
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
        Log::info('Tiny ERP: Processando webhook de pedido', [
            'pedido_id' => $this->pedidoId,
            'situacao' => $this->situacao,
        ]);

        $receita = Receita::where('tiny_pedido_id', $this->pedidoId)->first();

        if (!$receita) {
            Log::warning('Tiny ERP: Receita não encontrada para pedido', [
                'tiny_pedido_id' => $this->pedidoId,
            ]);
            return;
        }

        $situacoesFinalizadasInt = [1, 5, 6, 7];
        $situacoesFinalizadasStr = ['faturado', 'enviado', 'entregue', 'pronto_envio', 'atendido'];
        $situacoesCanceladasInt = [2, 3, 4];
        $situacoesCanceladasStr = ['cancelado', 'cancelada', 'devolvido', 'devolvida'];

        $situacaoNorm = is_numeric($this->situacao) ? (int) $this->situacao : strtolower(trim($this->situacao ?? ''));
        $isFinalizada = is_int($situacaoNorm)
            ? in_array($situacaoNorm, $situacoesFinalizadasInt)
            : in_array($situacaoNorm, $situacoesFinalizadasStr);
        $isCancelada = is_int($situacaoNorm)
            ? in_array($situacaoNorm, $situacoesCanceladasInt)
            : in_array($situacaoNorm, $situacoesCanceladasStr);

        if ($isFinalizada) {
            $this->marcarItensVendidos($receita);
        }

        if ($isCancelada) {
            $this->processarCancelamento($receita);
        }

        Log::info('Tiny ERP: Webhook processado', [
            'receita_id' => $receita->id,
            'tiny_pedido_id' => $this->pedidoId,
            'situacao' => $this->situacao,
        ]);
    }

    protected function marcarItensVendidos(Receita $receita): void
    {
        $client = new TinyErpClient();
        $result = $client->obterPedido((int) $this->pedidoId);

        if ($result['status'] !== 'success') {
            Log::error('Tiny ERP: Erro ao obter pedido para marcar itens vendidos', [
                'tiny_pedido_id' => $this->pedidoId,
                'error' => $result['message'] ?? 'Erro desconhecido',
            ]);
            return;
        }

        $pedidoData = $result['data'] ?? [];
        $itensTiny = $pedidoData['itens'] ?? [];

        $tinyProductIds = [];
        foreach ($itensTiny as $itemTiny) {
            $produtoId = $itemTiny['produto']['id'] ?? null;
            if ($produtoId) {
                $tinyProductIds[] = (int) $produtoId;
            }
        }

        $receita->load('itens.produto');
        $dataAquisicao = now();

        foreach ($receita->itens as $item) {
            if (!$item->produto || !$item->produto->tiny_id) {
                continue;
            }

            if (in_array((int) $item->produto->tiny_id, $tinyProductIds)) {
                $item->update(['vendido' => true]);

                ReceitaItemAquisicao::create([
                    'receita_item_id' => $item->id,
                    'data_aquisicao' => $dataAquisicao,
                ]);
            }
        }

        Log::info('Tiny ERP: Itens vendidos marcados', [
            'receita_id' => $receita->id,
            'tiny_product_ids' => $tinyProductIds,
            'total_itens_receita' => $receita->itens->count(),
        ]);
    }

    protected function processarCancelamento(Receita $receita): void
    {
        // Sempre atualiza a Receita - fluxo principal quando Tiny está ativo
        $receita->update(['status' => 'cancelada', 'ativo' => false]);

        // Se houver atendimento de call center, marca como cancelado
        $atendimento = $receita->atendimentoCallcenter;
        if ($atendimento) {
            $atendimento->update(['status' => AtendimentoCallcenter::STATUS_CANCELADO]);
        }

        Log::info('Tiny ERP: Receita e atendimento marcados como cancelados', [
            'receita_id' => $receita->id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de processamento de webhook falhou', [
            'pedido_id' => $this->pedidoId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
