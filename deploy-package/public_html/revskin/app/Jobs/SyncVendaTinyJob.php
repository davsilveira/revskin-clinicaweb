<?php

namespace App\Jobs;

use App\Models\AtendimentoCallcenter;
use App\Models\Setting;
use App\Services\TinyErpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncVendaTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public AtendimentoCallcenter $atendimento
    ) {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        // Verificar se integração está habilitada
        if (!Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Sincronização de venda desabilitada', [
                'atendimento_id' => $this->atendimento->id,
            ]);
            return;
        }

        $atendimento = $this->atendimento->fresh(['receita.paciente', 'receita.medico', 'receita.itens.produto']);

        if (!$atendimento->receita) {
            Log::warning('Tiny ERP: Atendimento não possui receita', [
                'atendimento_id' => $atendimento->id,
            ]);
            return;
        }

        $receita = $atendimento->receita;
        $paciente = $receita->paciente;

        // Garantir que paciente está sincronizado no Tiny
        if (!$paciente->tiny_id) {
            Log::info('Tiny ERP: Paciente não sincronizado, sincronizando primeiro', [
                'paciente_id' => $paciente->id,
            ]);
            
            $syncClienteJob = new \App\Jobs\SyncClienteTinyJob($paciente);
            $syncClienteJob->handle();
            
            $paciente->refresh();
            
            if (!$paciente->tiny_id) {
                Log::error('Tiny ERP: Não foi possível sincronizar paciente antes de criar pedido', [
                    'paciente_id' => $paciente->id,
                ]);
                throw new \Exception('Paciente não sincronizado no Tiny');
            }
        }

        $client = new TinyErpClient();

        // Preparar dados do pedido
        $pedidoData = $this->prepararDadosPedido($atendimento, $receita, $paciente);

        // Criar pedido no Tiny como rascunho/orçamento
        $result = $client->criarPedido($pedidoData);

        if ($result['status'] === 'success') {
            $data = $result['data'] ?? [];
            $tinyPedidoId = $data['id'] ?? null;

            if ($tinyPedidoId) {
                $atendimento->update([
                    'tiny_pedido_id' => $tinyPedidoId,
                    'tiny_sync_at' => now(),
                    'tiny_situacao' => 'rascunho', // ou situação inicial apropriada
                ]);

                // Também atualizar na receita
                $receita->update([
                    'tiny_pedido_id' => $tinyPedidoId,
                ]);

                Log::info('Tiny ERP: Pedido criado com sucesso', [
                    'atendimento_id' => $atendimento->id,
                    'receita_id' => $receita->id,
                    'tiny_pedido_id' => $tinyPedidoId,
                ]);
            }
        } else {
            Log::error('Tiny ERP: Erro ao criar pedido', [
                'atendimento_id' => $atendimento->id,
                'receita_id' => $receita->id,
                'error' => $result['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception($result['message'] ?? 'Erro ao criar pedido no Tiny');
        }
    }

    protected function prepararDadosPedido(
        AtendimentoCallcenter $atendimento,
        \App\Models\Receita $receita,
        \App\Models\Paciente $paciente
    ): array {
        $itens = [];

        foreach ($receita->itens as $item) {
            $produto = $item->produto;

            // Verificar se produto tem tiny_id
            if (!$produto->tiny_id) {
                Log::warning('Tiny ERP: Produto não possui tiny_id, pulando item', [
                    'produto_id' => $produto->id,
                    'item_id' => $item->id,
                ]);
                continue;
            }

            $itens[] = [
                'produto' => [
                    'id' => (int) $produto->tiny_id,
                ],
                'quantidade' => $item->quantidade,
                'valor_unitario' => (float) $item->valor_unitario,
                'desconto' => 0,
            ];
        }

        if (empty($itens)) {
            throw new \Exception('Pedido não possui itens válidos para sincronização');
        }

        $dados = [
            'cliente' => [
                'id' => (int) $paciente->tiny_id,
            ],
            'data_pedido' => $receita->data_receita->format('Y-m-d'),
            'situacao' => 'rascunho', // Criar como rascunho/orçamento
            'itens' => $itens,
        ];

        // Adicionar observações
        $obs = [];
        if ($receita->numero) {
            $obs[] = "Receita #{$receita->numero}";
        }
        if ($receita->medico) {
            $obs[] = "Médico: {$receita->medico->nome}";
        }
        if ($receita->anotacoes) {
            $obs[] = $receita->anotacoes;
        }
        if (!empty($obs)) {
            $dados['observacoes'] = implode(' | ', $obs);
        }

        // Valores
        if ($receita->valor_frete > 0) {
            $dados['valor_frete'] = (float) $receita->valor_frete;
        }

        if ($receita->desconto_valor > 0) {
            $dados['desconto'] = [
                'tipo' => 'valor',
                'valor' => (float) $receita->desconto_valor,
            ];
        }

        return $dados;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de sincronização de venda falhou', [
            'atendimento_id' => $this->atendimento->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
