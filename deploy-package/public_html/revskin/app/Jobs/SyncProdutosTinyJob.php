<?php

namespace App\Jobs;

use App\Models\Produto;
use App\Models\Setting;
use App\Services\TinyErpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProdutosTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        // Verificar se integração está habilitada
        if (!Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Sincronização de produtos desabilitada');
            return;
        }

        $client = new TinyErpClient();
        $synced = 0;
        $errors = 0;
        $page = 1;
        $perPage = 100;

        Log::info('Tiny ERP: Iniciando sincronização de produtos');

        do {
            $result = $client->listarProdutos([
                'pagina' => $page,
                'registrosPorPagina' => $perPage,
            ]);

            if ($result['status'] !== 'success') {
                Log::error('Tiny ERP: Erro ao listar produtos', [
                    'page' => $page,
                    'error' => $result['message'] ?? 'Erro desconhecido',
                ]);
                $errors++;
                break;
            }

            $data = $result['data'] ?? [];
            $produtos = $data['produtos'] ?? [];

            if (empty($produtos)) {
                break;
            }

            foreach ($produtos as $produtoData) {
                try {
                    $this->sincronizarProduto($produtoData);
                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Tiny ERP: Erro ao sincronizar produto', [
                        'produto_id' => $produtoData['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $page++;
        } while (count($produtos) === $perPage);

        // Atualizar data da última sincronização
        Setting::set('tiny_produtos_last_sync', now()->toDateTimeString());

        Log::info('Tiny ERP: Sincronização de produtos concluída', [
            'synced' => $synced,
            'errors' => $errors,
        ]);
    }

    protected function sincronizarProduto(array $produtoData): void
    {
        $tinyId = $produtoData['id'] ?? null;
        if (!$tinyId) {
            return;
        }

        $codigo = $produtoData['codigo'] ?? null;
        $nome = $produtoData['nome'] ?? 'Sem nome';

        // Buscar produto existente por tiny_id ou código
        $produto = Produto::where('tiny_id', $tinyId)
            ->orWhere('codigo', $codigo)
            ->first();

        $dados = [
            'tiny_id' => $tinyId,
            'nome' => $nome,
            'descricao' => $produtoData['descricao'] ?? null,
            'preco' => $produtoData['preco'] ?? 0,
            'preco_custo' => $produtoData['preco_custo'] ?? null,
            'categoria' => $produtoData['categoria'] ?? null,
            'unidade' => $produtoData['unidade'] ?? null,
            'estoque_minimo' => $produtoData['estoque_minimo'] ?? null,
            'modo_uso' => $produtoData['modo_uso'] ?? null,
            'tiny_sync_at' => now(),
            'ativo' => ($produtoData['situacao'] ?? 'A') === 'A', // A = Ativo
        ];

        if ($codigo && !$produto) {
            // Se não existe e tem código, criar novo
            $dados['codigo'] = $codigo;
            Produto::create($dados);
        } elseif ($produto) {
            // Se existe, atualizar
            $produto->update($dados);
        } else {
            // Se não tem código e não existe, criar com código gerado
            $dados['codigo'] = 'TINY-' . $tinyId;
            Produto::create($dados);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de sincronização de produtos falhou', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
