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
            'tipo_payload' => $this->payload['tipo'] ?? null,
        ]);

        $receita = Receita::where('tiny_pedido_id', $this->pedidoId)->first();

        if (!$receita) {
            Log::warning('Tiny ERP: Receita não encontrada para pedido', [
                'tiny_pedido_id' => $this->pedidoId,
            ]);
            return;
        }

        Log::info('Tiny ERP: Receita encontrada', [
            'receita_id' => $receita->id,
            'receita_numero' => $receita->numero,
        ]);

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

        Log::info('Tiny ERP: Classificação da situação', [
            'situacao_raw' => $this->situacao,
            'situacao_norm' => $situacaoNorm,
            'is_finalizada' => $isFinalizada,
            'is_cancelada' => $isCancelada,
        ]);

        if ($isFinalizada) {
            Log::info('Tiny ERP: Situação finalizada, chamando marcarItensVendidos');
            $this->marcarItensVendidos($receita);
        }

        if ($isCancelada) {
            Log::info('Tiny ERP: Situação cancelada, chamando processarCancelamento');
            $this->processarCancelamento($receita);
        }

        if (!$isFinalizada && !$isCancelada) {
            Log::info('Tiny ERP: Situação não é finalizada nem cancelada, nada a fazer');
        }

        Log::info('Tiny ERP: Webhook processado', [
            'receita_id' => $receita->id,
            'tiny_pedido_id' => $this->pedidoId,
            'situacao' => $this->situacao,
        ]);
    }

    protected function marcarItensVendidos(Receita $receita): void
    {
        Log::info('Tiny ERP: marcarItensVendidos iniciado', ['receita_id' => $receita->id]);

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

        Log::info('Tiny ERP: Pedido obtido da API', [
            'tiny_pedido_id' => $this->pedidoId,
            'qtd_itens_no_pedido' => count($itensTiny),
            'tiny_product_ids_extraidos' => $tinyProductIds,
        ]);

        $receita->load('itens.produto');
        $dataAquisicao = now();
        $itensMarcados = 0;

        foreach ($receita->itens as $item) {
            if (!$item->produto) {
                Log::info('Tiny ERP: Item sem produto, pulando', ['receita_item_id' => $item->id]);
                continue;
            }
            if (!$item->produto->tiny_id) {
                Log::info('Tiny ERP: Item sem tiny_id no produto, pulando', [
                    'receita_item_id' => $item->id,
                    'produto_id' => $item->produto->id,
                ]);
                continue;
            }

            if (in_array((int) $item->produto->tiny_id, $tinyProductIds)) {
                $item->update(['vendido' => true]);
                $jaExiste = ReceitaItemAquisicao::where('receita_item_id', $item->id)
                    ->where('tiny_pedido_id', $this->pedidoId)
                    ->exists();
                if (!$jaExiste) {
                    ReceitaItemAquisicao::create([
                        'receita_item_id' => $item->id,
                        'data_aquisicao' => $dataAquisicao,
                        'tiny_pedido_id' => $this->pedidoId,
                    ]);
                    $itensMarcados++;
                }
                Log::info('Tiny ERP: Item marcado como vendido', [
                    'receita_item_id' => $item->id,
                    'produto_tiny_id' => $item->produto->tiny_id,
                ]);
            } else {
                Log::info('Tiny ERP: Produto não está no pedido Tiny', [
                    'receita_item_id' => $item->id,
                    'produto_tiny_id' => $item->produto->tiny_id,
                ]);
            }
        }

        Log::info('Tiny ERP: marcarItensVendidos concluído', [
            'receita_id' => $receita->id,
            'tiny_product_ids_pedido' => $tinyProductIds,
            'total_itens_receita' => $receita->itens->count(),
            'itens_marcados' => $itensMarcados,
        ]);
    }

    protected function processarCancelamento(Receita $receita): void
    {
        $receita->load('itens');

        foreach ($receita->itens as $item) {
            $item->update(['vendido' => false]);
        }

        $itemIds = $receita->itens->pluck('id')->toArray();
        ReceitaItemAquisicao::whereIn('receita_item_id', $itemIds)
            ->where(function ($query) {
                $query->where('tiny_pedido_id', $this->pedidoId)
                    ->orWhereNull('tiny_pedido_id');
            })
            ->delete();

        $receita->update(['status' => 'cancelada', 'ativo' => false]);

        $atendimento = $receita->atendimentoCallcenter;
        if ($atendimento) {
            $atendimento->update(['status' => AtendimentoCallcenter::STATUS_CANCELADO]);
        }

        Log::info('Tiny ERP: Receita e atendimento marcados como cancelados', [
            'receita_id' => $receita->id,
            'itens_revertidos' => count($itemIds),
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
