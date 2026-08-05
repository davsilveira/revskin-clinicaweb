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

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        if (! Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Sincronização de produtos desabilitada');

            return;
        }

        $client = new TinyErpClient;
        $isV2 = $client->isV2();
        $synced = 0;
        $errors = 0;
        $pagina = 1;
        $offset = 0;
        $limit = 100;
        $seenTinyIds = [];
        $listagemCompleta = true;

        $apenasClinicaweb = Setting::get('tiny_sync_apenas_clinicaweb', true);
        $idTag = $apenasClinicaweb && $isV2 ? $this->obterIdTagClinicaweb($client) : null;

        if ($apenasClinicaweb && $isV2 && ! $idTag) {
            Log::error('Tiny ERP: Sincronização filtrada por tag "clinicaweb" ativa, mas tag não encontrada. Configure a tag ou desative tiny_sync_apenas_clinicaweb.');

            return;
        }

        Log::info('Tiny ERP: Iniciando sincronização de produtos', [
            'api_version' => $isV2 ? 'v2' : 'v3',
            'filtro_tag_clinicaweb' => (bool) $idTag,
        ]);

        do {
            $params = $isV2
                ? ['pesquisa' => '', 'pagina' => $pagina]
                : ['limit' => $limit, 'offset' => $offset];

            if ($idTag) {
                $params['idTag'] = $idTag;
            }

            $result = $client->listarProdutos($params);

            if ($result['status'] !== 'success') {
                Log::error('Tiny ERP: Erro ao listar produtos', [
                    'pagina' => $pagina,
                    'offset' => $offset,
                    'error' => $result['message'] ?? 'Erro desconhecido',
                ]);
                $errors++;
                $listagemCompleta = false;
                break;
            }

            $data = $result['data'] ?? [];
            $itens = $data['itens'] ?? [];

            if (empty($itens)) {
                break;
            }

            foreach ($itens as $produtoData) {
                try {
                    $tinyId = $produtoData['id'] ?? null;
                    if ($tinyId !== null && $tinyId !== '') {
                        $seenTinyIds[(string) $tinyId] = true;
                    }
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

            if ($isV2) {
                $totalPages = $data['paginacao']['numero_paginas'] ?? 1;
                $pagina++;
                $hasMore = $pagina <= $totalPages;
            } else {
                $offset += $limit;
                $hasMore = count($itens) === $limit;
            }
        } while ($hasMore);

        $descontinuados = 0;
        if ($listagemCompleta) {
            $descontinuados = $this->marcarDescontinuadosAusentesNoErp(array_keys($seenTinyIds));
        } else {
            Log::warning('Tiny ERP: Listagem incompleta — órfãos não foram marcados como descontinuados para evitar falsos positivos');
        }

        Setting::set('tiny_produtos_last_sync', now()->toDateTimeString());

        Log::info('Tiny ERP: Sincronização de produtos concluída', [
            'synced' => $synced,
            'errors' => $errors,
            'descontinuados_orfãos' => $descontinuados,
            'listagem_completa' => $listagemCompleta,
        ]);
    }

    protected function obterIdTagClinicaweb(TinyErpClient $client): ?int
    {
        $cached = Setting::get('tiny_clinicaweb_tag_id');
        if ($cached) {
            return (int) $cached;
        }

        $result = $client->pesquisarTags('clinicaweb');
        if ($result['status'] !== 'success') {
            Log::warning('Tiny ERP: Erro ao pesquisar tag clinicaweb', ['message' => $result['message'] ?? '']);

            return null;
        }

        $tags = $result['data']['tags'] ?? [];
        foreach ($tags as $tag) {
            $nome = $tag['nome'] ?? '';
            if (strcasecmp(trim($nome), 'clinicaweb') === 0) {
                $id = (int) ($tag['id'] ?? 0);
                if ($id > 0) {
                    Setting::set('tiny_clinicaweb_tag_id', $id, 'tiny');

                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * Marca como descontinuado (legado_somente_leitura) produtos com tiny_id
     * que não vieram na listagem desta sync — tipicamente excluídos no oList.
     * Não apaga (preserva histórico de receitas). Não altera quem já é descontinuado.
     * Produtos ainda listados com situacao ≠ A continuam só inativos (ativo=false).
     *
     * @param  list<string>  $seenTinyIds
     */
    protected function marcarDescontinuadosAusentesNoErp(array $seenTinyIds): int
    {
        $query = Produto::query()
            ->whereNotNull('tiny_id')
            ->where('legado_somente_leitura', false);

        if ($seenTinyIds === []) {
            $ids = (clone $query)->pluck('id');
        } else {
            $ids = (clone $query)->whereNotIn('tiny_id', $seenTinyIds)->pluck('id');
        }

        if ($ids->isEmpty()) {
            return 0;
        }

        $n = Produto::query()->whereIn('id', $ids)->update([
            'legado_somente_leitura' => true,
            'ativo' => true,
        ]);

        Log::info('Tiny ERP: Produtos ausentes no oList marcados como descontinuados', [
            'count' => $n,
            'produto_ids' => $ids->take(50)->values()->all(),
        ]);

        return $n;
    }

    /**
     * Upsert por tiny_id; se não achar, tenta pelo SKU (codigo).
     * Produto que volta no oList deixa de ser descontinuado e é atualizado
     * (ativo conforme situacao: A → ativo, demais → inativo).
     */
    protected function sincronizarProduto(array $produtoData): void
    {
        $tinyId = $produtoData['id'] ?? null;
        if (! $tinyId) {
            return;
        }

        $sku = $produtoData['sku'] ?? null;
        $descricao = $produtoData['descricao'] ?? 'Sem nome';
        $precos = $produtoData['precos'] ?? [];

        $produto = Produto::where('tiny_id', $tinyId)->first();
        if (! $produto && $sku) {
            $produto = Produto::where('codigo', $sku)->first();
        }

        $dados = [
            'tiny_id' => $tinyId,
            'nome' => $descricao,
            'preco' => $precos['preco'] ?? 0,
            'preco_custo' => $precos['precoCusto'] ?? null,
            'unidade' => $produtoData['unidade'] ?? null,
            'tiny_sync_at' => now(),
            'ativo' => ($produtoData['situacao'] ?? 'A') === 'A',
            'legado_somente_leitura' => false,
        ];

        if ($produto) {
            $produto->update($dados);
        } else {
            $dados['codigo'] = $sku ?: ('TINY-'.$tinyId);
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
