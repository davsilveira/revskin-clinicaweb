<?php

namespace App\Jobs;

use App\Services\ReceitaCancelamentoService;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use App\Services\TinyErpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        public string $pedidoId,
        public ?string $situacao,
        public array $payload
    ) {
        $this->onQueue('tiny-webhooks');
    }

    /**
     * Serializa merges do mesmo pedido Tiny (evita webhook duplicado criando linhas em paralelo).
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('tiny-pedido-'.$this->pedidoId))
                ->releaseAfter(15)
                ->expireAfter(180),
        ];
    }

    public function handle(): void
    {
        Log::info('Tiny ERP: Processando webhook de pedido', [
            'pedido_id' => $this->pedidoId,
            'situacao' => $this->situacao,
            'tipo_payload' => $this->payload['tipo'] ?? null,
        ]);

        $receita = Receita::where('tiny_pedido_id', $this->pedidoId)->first();

        if (! $receita) {
            Log::warning('Tiny ERP: Receita não encontrada para pedido', [
                'tiny_pedido_id' => $this->pedidoId,
            ]);

            return;
        }

        Log::info('Tiny ERP: Receita encontrada', [
            'receita_id' => $receita->id,
            'receita_numero' => $receita->numero,
        ]);

        $situacoesSincronizaPrecosStr = ['aprovado', 'preparando_envio'];
        $situacoesCanceladasInt = [2, 3, 4];
        $situacoesCanceladasStr = ['cancelado', 'cancelada', 'devolvido', 'devolvida'];

        $situacaoNorm = is_numeric($this->situacao) ? (int) $this->situacao : strtolower(trim($this->situacao ?? ''));
        $isFinalizada = TinyErpClient::isSituacaoPedidoFaturada($this->situacao);
        $isCancelada = is_int($situacaoNorm)
            ? in_array($situacaoNorm, $situacoesCanceladasInt)
            : in_array($situacaoNorm, $situacoesCanceladasStr);
        $isSincronizaPrecosSemVendido = ! is_int($situacaoNorm)
            && in_array($situacaoNorm, $situacoesSincronizaPrecosStr, true);

        Log::info('Tiny ERP: Classificação da situação', [
            'situacao_raw' => $this->situacao,
            'situacao_norm' => $situacaoNorm,
            'is_finalizada' => $isFinalizada,
            'is_cancelada' => $isCancelada,
            'is_sincroniza_precos' => $isSincronizaPrecosSemVendido,
        ]);

        if ($isFinalizada) {
            Log::info('Tiny ERP: Situação finalizada, chamando marcarItensVendidos');
            $this->marcarItensVendidos($receita);
        }

        if ($isCancelada) {
            Log::info('Tiny ERP: Situação cancelada, chamando processarCancelamento');
            $this->processarCancelamento($receita);
        }

        if ($isSincronizaPrecosSemVendido && ! $isFinalizada && ! $isCancelada) {
            Log::info('Tiny ERP: Situação intermediária (preços), sincronizando itens sem marcar vendido');
            $this->sincronizarPrecosItensDoPedido($receita);
        }

        if (! $isFinalizada && ! $isCancelada && ! $isSincronizaPrecosSemVendido) {
            Log::info('Tiny ERP: Situação não é finalizada nem cancelada nem sincronização de preços, nada a fazer');
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
        $this->aplicarMergeItensDoPedidoTiny($receita, marcarVendido: true);
    }

    protected function sincronizarPrecosItensDoPedido(Receita $receita): void
    {
        Log::info('Tiny ERP: sincronizarPrecosItensDoPedido iniciado', ['receita_id' => $receita->id]);
        $this->aplicarMergeItensDoPedidoTiny($receita, marcarVendido: false);
    }

    /**
     * Obtém o pedido na API Tiny e alinha quantidades/valores (e opcionalmente novas linhas) na receita.
     *
     * @param  bool  $marcarVendido  Se true, marca vendido e registra aquisição para este pedido (situação finalizada).
     */
    protected function aplicarMergeItensDoPedidoTiny(Receita $receita, bool $marcarVendido): void
    {
        $client = new TinyErpClient;
        $result = $client->obterPedido((int) $this->pedidoId);

        if ($result['status'] !== 'success') {
            Log::error('Tiny ERP: Erro ao obter pedido para merge de itens', [
                'tiny_pedido_id' => $this->pedidoId,
                'marcar_vendido' => $marcarVendido,
                'error' => $result['message'] ?? 'Erro desconhecido',
            ]);

            return;
        }

        $pedidoData = $result['data'] ?? [];
        $itensTiny = $pedidoData['itens'] ?? [];
        $parsed = $this->parseItensPedidoTiny($itensTiny);

        Log::info('Tiny ERP: Pedido obtido da API para merge', [
            'tiny_pedido_id' => $this->pedidoId,
            'marcar_vendido' => $marcarVendido,
            'qtd_itens_no_pedido' => count($itensTiny),
            'linhas_normalizadas' => count($parsed),
        ]);

        $dataAquisicao = now();
        $itensMarcados = 0;
        $linhasNovas = 0;
        $itensReimpressos = 0;

        DB::transaction(function () use ($receita, $parsed, $dataAquisicao, $marcarVendido, &$itensMarcados, &$linhasNovas, &$itensReimpressos) {
            // Trava a receita para serializar merges concorrentes do mesmo pedido.
            $receita = Receita::whereKey($receita->id)->lockForUpdate()->first();
            if (! $receita) {
                return;
            }

            $pending = $parsed;
            $receita->load('itens.produto');

            foreach ($receita->itens as $item) {
                if (! $item->produto || ! $item->produto->tiny_id) {
                    continue;
                }

                $tid = (int) $item->produto->tiny_id;
                $matchIndex = null;
                foreach ($pending as $i => $row) {
                    if ((int) $row['tiny_id'] === $tid) {
                        $matchIndex = $i;
                        break;
                    }
                }

                if ($matchIndex === null) {
                    continue;
                }

                $row = $pending[$matchIndex];
                array_splice($pending, $matchIndex, 1);

                $q = $row['quantidade'];
                $vu = $row['valor_unitario'];
                $attrs = [
                    'quantidade' => $q,
                    'valor_unitario' => $vu,
                    'valor_total' => $q * $vu,
                ];
                if ($marcarVendido) {
                    $attrs['vendido'] = true;
                }

                // O produto está no pedido do oList: ainda que tenha sido recomendado
                // sem marcação (imprimir=0), ele foi comprado e precisa entrar no total
                // da receita — calcularTotais() só soma itens com imprimir=1.
                if (! $item->imprimir) {
                    $attrs['imprimir'] = true;
                    $itensReimpressos++;
                    Log::info('Tiny ERP: Item do pedido estava desmarcado na receita, marcando imprimir', [
                        'receita_id' => $receita->id,
                        'receita_item_id' => $item->id,
                        'produto_tiny_id' => $tid,
                        'valor_unitario' => $vu,
                    ]);
                }

                $item->update($attrs);

                if ($marcarVendido) {
                    $jaExiste = ReceitaItemAquisicao::where('receita_item_id', $item->id)
                        ->where('tiny_pedido_id', $this->pedidoId)
                        ->exists();
                    if (! $jaExiste) {
                        ReceitaItemAquisicao::create([
                            'receita_item_id' => $item->id,
                            'data_aquisicao' => $dataAquisicao,
                            'tiny_pedido_id' => $this->pedidoId,
                        ]);
                        $itensMarcados++;
                    }
                }
            }

            $maxOrdem = (int) ($receita->itens()->max('ordem') ?? -1);

            foreach ($pending as $row) {
                $produto = Produto::where('tiny_id', $row['tiny_id'])->first();
                if (! $produto) {
                    Log::warning('Tiny ERP: Linha do pedido sem produto local com mesmo tiny_id', [
                        'tiny_id' => $row['tiny_id'],
                        'receita_id' => $receita->id,
                    ]);

                    continue;
                }

                $maxOrdem++;
                $q = $row['quantidade'];
                $vu = $row['valor_unitario'];
                $novo = ReceitaItem::create([
                    'receita_id' => $receita->id,
                    'produto_id' => $produto->id,
                    'local_uso' => $produto->local_uso,
                    'quantidade' => $q,
                    'valor_unitario' => $vu,
                    'valor_total' => $q * $vu,
                    'imprimir' => true,
                    'grupo' => 'recomendado',
                    'ordem' => $maxOrdem,
                    'vendido' => $marcarVendido,
                ]);

                if ($marcarVendido) {
                    ReceitaItemAquisicao::create([
                        'receita_item_id' => $novo->id,
                        'data_aquisicao' => $dataAquisicao,
                        'tiny_pedido_id' => $this->pedidoId,
                    ]);
                    $linhasNovas++;
                    $itensMarcados++;
                } else {
                    $linhasNovas++;
                }
            }

            $receita->calcularTotais();
        });

        Log::info('Tiny ERP: Merge de itens do pedido concluído', [
            'receita_id' => $receita->id,
            'marcar_vendido' => $marcarVendido,
            'aquisicoes_ou_atualizacoes_contagem' => $itensMarcados,
            'linhas_novas_inseridas' => $linhasNovas,
            'itens_marcados_imprimir' => $itensReimpressos,
        ]);
    }

    /**
     * @return list<array{tiny_id: int, quantidade: int, valor_unitario: float}>
     */
    protected function parseItensPedidoTiny(array $itensTiny): array
    {
        $out = [];
        foreach ($itensTiny as $itemTiny) {
            $tid = $this->extrairTinyProdutoId($itemTiny);
            if ($tid === null) {
                continue;
            }
            $out[] = [
                'tiny_id' => $tid,
                'quantidade' => $this->extrairQuantidadeTiny($itemTiny),
                'valor_unitario' => $this->extrairValorUnitarioTiny($itemTiny),
            ];
        }

        return $out;
    }

    protected function extrairTinyProdutoId(array $itemTiny): ?int
    {
        $produto = $itemTiny['produto'] ?? [];
        if (! is_array($produto)) {
            $produto = [];
        }

        $raw = $produto['id']
            ?? $produto['idProduto']
            ?? $itemTiny['idProduto']
            ?? $itemTiny['produto_id']
            ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    protected function extrairQuantidadeTiny(array $itemTiny): int
    {
        $q = $itemTiny['quantidade'] ?? $itemTiny['quantity'] ?? 1;

        return max(1, (int) round((float) $q));
    }

    protected function extrairValorUnitarioTiny(array $itemTiny): float
    {
        $v = $itemTiny['valorUnitario']
            ?? $itemTiny['valor_unitario']
            ?? $itemTiny['preco']
            ?? $itemTiny['valor']
            ?? 0;

        return round((float) $v, 2);
    }

    protected function processarCancelamento(Receita $receita): void
    {
        ReceitaCancelamentoService::cancelarReceita($receita, (string) $this->pedidoId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de processamento de webhook falhou', [
            'pedido_id' => $this->pedidoId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
