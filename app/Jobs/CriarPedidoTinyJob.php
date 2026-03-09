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

class CriarPedidoTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Receita $receita
    ) {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        if (!Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Criação de pedido desabilitada', [
                'receita_id' => $this->receita->id,
            ]);
            return;
        }

        $receita = $this->receita->fresh(['paciente', 'medico', 'itens.produto']);

        if (!$receita->paciente) {
            Log::warning('Tiny ERP: Receita não possui paciente', [
                'receita_id' => $receita->id,
            ]);
            return;
        }

        $client = new TinyErpClient();
        $paciente = $receita->paciente;

        if ($client->isV2()) {
            $this->criarPedidoV2($client, $receita, $paciente);
        } else {
            $this->criarPedidoV3($client, $receita, $paciente);
        }
    }

    protected function criarPedidoV2(TinyErpClient $client, Receita $receita, \App\Models\Paciente $paciente): void
    {
        $itens = $this->buildItensV2($receita);
        if (empty($itens)) {
            throw new \Exception('Pedido não possui itens válidos para sincronização');
        }

        $cpf = preg_replace('/\D/', '', $paciente->cpf ?? '');

        $dados = [
            'observacoes' => $this->buildObservacoes($receita),
            'data' => $receita->data_receita?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'itens' => $itens,
            'cliente' => [
                'nome' => $paciente->nome,
                'tipo_pessoa' => 'F',
                'cpf_cnpj' => $cpf,
                'email' => $paciente->email1 ?? '',
                'fone' => preg_replace('/\D/', '', $paciente->telefone1 ?? $paciente->celular ?? ''),
                'endereco' => $paciente->endereco ?? '',
                'numero' => $paciente->numero ?? '',
                'complemento' => $paciente->complemento ?? '',
                'bairro' => $paciente->bairro ?? '',
                'cidade' => $paciente->cidade ?? '',
                'uf' => $paciente->uf ?? '',
                'cep' => preg_replace('/\D/', '', $paciente->cep ?? ''),
                'atualizar_cliente' => 'S',
            ],
        ];

        if ($receita->valor_frete > 0) {
            $dados['valorFrete'] = (float) $receita->valor_frete;
        }
        if ($receita->desconto_valor > 0) {
            $dados['valorDesconto'] = (float) $receita->desconto_valor;
        }

        $result = $client->criarPedido($dados);

        if ($result['status'] === 'success') {
            $tinyPedidoId = $result['data']['id'] ?? null;
            if ($tinyPedidoId) {
                $receita->update(['tiny_pedido_id' => $tinyPedidoId]);
                Log::info('Tiny ERP V2: Pedido criado com sucesso', [
                    'receita_id' => $receita->id,
                    'tiny_pedido_id' => $tinyPedidoId,
                ]);
            }
        } else {
            Log::error('Tiny ERP V2: Erro ao criar pedido', [
                'receita_id' => $receita->id,
                'error' => $result['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception($result['message'] ?? 'Erro ao criar pedido no Tiny');
        }
    }

    protected function criarPedidoV3(TinyErpClient $client, Receita $receita, \App\Models\Paciente $paciente): void
    {
        if (!$paciente->tiny_id) {
            Log::info('Tiny ERP: Paciente não sincronizado, sincronizando primeiro', [
                'paciente_id' => $paciente->id,
            ]);

            $syncClienteJob = new SyncClienteTinyJob($paciente);
            $syncClienteJob->handle();

            $paciente->refresh();

            if (!$paciente->tiny_id) {
                Log::error('Tiny ERP: Não foi possível sincronizar paciente antes de criar pedido', [
                    'paciente_id' => $paciente->id,
                ]);
                throw new \Exception('Paciente não sincronizado no Tiny');
            }
        }

        $pedidoData = $this->prepararDadosPedidoV3($receita, $paciente);
        $result = $client->criarPedido($pedidoData);

        if ($result['status'] === 'success') {
            $tinyPedidoId = $result['data']['id'] ?? null;
            if ($tinyPedidoId) {
                $receita->update(['tiny_pedido_id' => $tinyPedidoId]);
                Log::info('Tiny ERP: Pedido criado com sucesso', [
                    'receita_id' => $receita->id,
                    'tiny_pedido_id' => $tinyPedidoId,
                ]);
            }
        } else {
            Log::error('Tiny ERP: Erro ao criar pedido', [
                'receita_id' => $receita->id,
                'error' => $result['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception($result['message'] ?? 'Erro ao criar pedido no Tiny');
        }
    }

    protected function buildItensV2(Receita $receita): array
    {
        $itens = [];
        foreach ($receita->itens as $item) {
            $produto = $item->produto;
            $itens[] = [
                'produto' => ['id' => $produto?->tiny_id ? (int) $produto->tiny_id : null],
                'descricao' => $produto?->nome ?? $item->descricao ?? 'Produto',
                'unidade' => $produto?->unidade ?? 'UN',
                'quantidade' => $item->quantidade,
                'valorUnitario' => (float) $item->valor_unitario,
            ];
        }
        return $itens;
    }

    protected function prepararDadosPedidoV3(Receita $receita, \App\Models\Paciente $paciente): array
    {
        $itens = [];
        foreach ($receita->itens as $item) {
            $produto = $item->produto;
            if (!$produto || !$produto->tiny_id) {
                Log::warning('Tiny ERP: Produto sem tiny_id, pulando item', [
                    'produto_id' => $produto?->id,
                    'item_id' => $item->id,
                ]);
                continue;
            }
            $itens[] = [
                'produto' => ['id' => (int) $produto->tiny_id],
                'quantidade' => $item->quantidade,
                'valorUnitario' => (float) $item->valor_unitario,
            ];
        }

        if (empty($itens)) {
            throw new \Exception('Pedido não possui itens válidos para sincronização');
        }

        $dados = [
            'idContato' => (int) $paciente->tiny_id,
            'situacao' => 0,
            'data' => $receita->data_receita->format('Y-m-d'),
            'observacoes' => $this->buildObservacoes($receita),
            'itens' => $itens,
        ];

        if ($receita->valor_frete > 0) {
            $dados['valorFrete'] = (float) $receita->valor_frete;
        }
        if ($receita->desconto_valor > 0) {
            $dados['valorDesconto'] = (float) $receita->desconto_valor;
        }

        return $dados;
    }

    protected function buildObservacoes(Receita $receita): string
    {
        $obs = [];
        $obs[] = 'Vendedor: ClinicaWeb';

        if ($receita->medico) {
            $obs[] = "Médico: {$receita->medico->nome}";
        }

        $isFirstReceita = Receita::where('paciente_id', $receita->paciente_id)
            ->where('status', 'finalizada')
            ->where('id', '!=', $receita->id)
            ->doesntExist();

        $obs[] = $isFirstReceita ? 'Primeira receita do paciente' : 'Receita recorrente';

        if ($receita->numero) {
            $obs[] = "Receita #{$receita->numero}";
        }
        if ($receita->anotacoes) {
            $obs[] = $receita->anotacoes;
        }

        return implode(' | ', $obs);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de criação de pedido falhou', [
            'receita_id' => $this->receita->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
