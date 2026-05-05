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

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Receita $receita
    ) {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        Log::info('Tiny ERP: CriarPedidoTinyJob iniciado', [
            'receita_id' => $this->receita->id,
            'receita_numero' => $this->receita->numero,
        ]);

        if (! Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Criação de pedido desabilitada', [
                'receita_id' => $this->receita->id,
            ]);

            return;
        }

        $receita = $this->receita->fresh(['paciente', 'medico.linkedUser', 'itens.produto']);

        if (! $receita->paciente) {
            Log::warning('Tiny ERP: Receita não possui paciente', [
                'receita_id' => $receita->id,
            ]);

            return;
        }

        $client = new TinyErpClient;
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
            'marcadores' => ['ClinicaWeb'],
            'numero_pedido_ecommerce' => $receita->numero ?? 'REC-'.$receita->id,
            'ecommerce' => 'ClinicaWeb',
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
            if ($tinyPedidoId !== null && $tinyPedidoId !== '') {
                $tinyPedidoId = (string) $tinyPedidoId;
                $receita->update(['tiny_pedido_id' => $tinyPedidoId]);
                Log::info('Tiny ERP V2: Pedido criado e tiny_pedido_id salvo', [
                    'receita_id' => $receita->id,
                    'tiny_pedido_id' => $tinyPedidoId,
                ]);
            } else {
                Log::warning('Tiny ERP V2: Pedido criado mas ID não retornado pela API', [
                    'receita_id' => $receita->id,
                    'result_data' => $result['data'] ?? null,
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
        if (! $paciente->tiny_id) {
            Log::info('Tiny ERP: Paciente não sincronizado, sincronizando primeiro', [
                'paciente_id' => $paciente->id,
            ]);

            $syncClienteJob = new SyncClienteTinyJob($paciente);
            $syncClienteJob->handle();

            $paciente->refresh();

            if (! $paciente->tiny_id) {
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
            if ($tinyPedidoId !== null && $tinyPedidoId !== '') {
                $tinyPedidoId = (string) $tinyPedidoId;
                $receita->update(['tiny_pedido_id' => $tinyPedidoId]);
                Log::info('Tiny ERP V3: Pedido criado e tiny_pedido_id salvo', [
                    'receita_id' => $receita->id,
                    'tiny_pedido_id' => $tinyPedidoId,
                ]);
            } else {
                Log::warning('Tiny ERP V3: Pedido criado mas ID não retornado pela API', [
                    'receita_id' => $receita->id,
                    'result_data' => $result['data'] ?? null,
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
        foreach ($receita->itens->where('imprimir', true) as $item) {
            $produto = $item->produto;
            $preco = $produto
                ? (float) ($produto->preco_venda ?? $produto->preco ?? 0)
                : (float) $item->valor_unitario;
            $itens[] = [
                'produto' => ['id' => $produto?->tiny_id ? (int) $produto->tiny_id : null],
                'descricao' => $produto?->nome ?? 'Produto',
                'unidade' => $produto?->unidade ?? 'UN',
                'quantidade' => $item->quantidade,
                'valorUnitario' => $preco,
            ];
        }

        return $itens;
    }

    protected function prepararDadosPedidoV3(Receita $receita, \App\Models\Paciente $paciente): array
    {
        $itens = [];
        foreach ($receita->itens->where('imprimir', true) as $item) {
            $produto = $item->produto;
            if (! $produto || ! $produto->tiny_id) {
                Log::warning('Tiny ERP: Produto sem tiny_id, pulando item', [
                    'produto_id' => $produto?->id,
                    'item_id' => $item->id,
                ]);

                continue;
            }
            $preco = (float) ($produto->preco_venda ?? $produto->preco ?? $item->valor_unitario ?? 0);
            $itens[] = [
                'produto' => ['id' => (int) $produto->tiny_id],
                'quantidade' => $item->quantidade,
                'valorUnitario' => $preco,
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
            'marcadores' => ['ClinicaWeb'],
            'numero_pedido_ecommerce' => $receita->numero ?? 'REC-'.$receita->id,
            'ecommerce' => 'ClinicaWeb',
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

    /**
     * Texto enviado ao campo Observações do pedido no Olist/Tiny.
     * Obs. internas do ERP ficam vazias (não enviamos obs_internas).
     *
     * Formato: ClinicaWeb | Receita #{numero} | {nome médico} [| Anotações do médico: ...]
     * O último trecho só entra se o campo "Anotações internas" da receita ({@see Receita::$anotacoes}) estiver preenchido.
     */
    protected function buildObservacoes(Receita $receita): string
    {
        $numero = $receita->numero ?? ('REC-'.$receita->id);
        $nomeMedico = trim((string) ($receita->medico?->nome ?? ''));
        if ($nomeMedico === '') {
            $nomeMedico = 'Não informado';
        }

        $line = 'ClinicaWeb | Receita #'.$numero.' | '.$nomeMedico;

        $internas = trim((string) ($receita->anotacoes ?? ''));
        if ($internas !== '') {
            $line .= ' | Anotações do médico: '.$internas;
        }

        return $line;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de criação de pedido falhou', [
            'receita_id' => $this->receita->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
