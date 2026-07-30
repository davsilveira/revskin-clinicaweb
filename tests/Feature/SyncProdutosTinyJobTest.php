<?php

namespace Tests\Feature;

use App\Jobs\SyncProdutosTinyJob;
use App\Models\Produto;
use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncProdutosTinyJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('tiny_enabled', true);
        Setting::set('tiny_api_version', 'v2');
        Setting::set('tiny_token', 'test-token-v2');
        Setting::set('tiny_sync_apenas_clinicaweb', false);

        (new TinyApiRateLimiter)->resetForTesting();
    }

    #[Test]
    public function inativa_produtos_com_tiny_id_ausentes_na_listagem(): void
    {
        $keep = Produto::create([
            'codigo' => 'KEEP-1',
            'nome' => 'Keep',
            'tiny_id' => '100',
            'ativo' => true,
            'preco' => 10,
        ]);
        $orphan = Produto::create([
            'codigo' => 'ORPHAN-1',
            'nome' => 'Orphan',
            'tiny_id' => '999',
            'ativo' => true,
            'preco' => 10,
        ]);
        $localOnly = Produto::create([
            'codigo' => 'LOCAL-1',
            'nome' => 'Local sem Tiny',
            'tiny_id' => null,
            'ativo' => true,
            'preco' => 10,
        ]);
        $legado = Produto::create([
            'codigo' => 'LEGADO-1',
            'nome' => 'Legado',
            'tiny_id' => '888',
            'ativo' => true,
            'legado_somente_leitura' => true,
            'preco' => 10,
        ]);

        Http::fake([
            'api.tiny.com.br/api2/produtos.pesquisa.php' => Http::response($this->pesquisaResponse([
                $this->produtoListItem(100, 'KEEP-1', 'Keep Atualizado', 'A'),
            ])),
        ]);

        (new SyncProdutosTinyJob)->handle();

        $this->assertTrue($keep->fresh()->ativo);
        $this->assertSame('Keep Atualizado', $keep->fresh()->nome);
        $this->assertFalse($orphan->fresh()->ativo);
        $this->assertTrue($localOnly->fresh()->ativo);
        $this->assertTrue($legado->fresh()->ativo);
    }

    #[Test]
    public function reativa_e_atualiza_por_sku_quando_volta_no_olist(): void
    {
        $produto = Produto::create([
            'codigo' => 'SKU-X',
            'nome' => 'Antigo',
            'tiny_id' => '111',
            'ativo' => false,
            'preco' => 5,
        ]);

        Http::fake([
            'api.tiny.com.br/api2/produtos.pesquisa.php' => Http::response($this->pesquisaResponse([
                // Mesmo SKU, novo tiny_id (recriado no ERP)
                $this->produtoListItem(222, 'SKU-X', 'Novo Nome', 'A', 19.9),
            ])),
        ]);

        (new SyncProdutosTinyJob)->handle();

        $produto->refresh();
        $this->assertTrue($produto->ativo);
        $this->assertSame('222', (string) $produto->tiny_id);
        $this->assertSame('Novo Nome', $produto->nome);
        $this->assertSame('SKU-X', $produto->codigo);
        $this->assertEquals(19.9, (float) $produto->preco);
    }

    #[Test]
    public function nao_inativa_orfãos_quando_listagem_falha(): void
    {
        $orphan = Produto::create([
            'codigo' => 'ORPHAN-2',
            'nome' => 'Orphan',
            'tiny_id' => '777',
            'ativo' => true,
            'preco' => 10,
        ]);

        Http::fake([
            'api.tiny.com.br/api2/produtos.pesquisa.php' => Http::response([
                'retorno' => [
                    'status' => 'Erro',
                    'codigo_erro' => 1,
                    'erros' => [['erro' => 'Falha simulada']],
                ],
            ]),
        ]);

        (new SyncProdutosTinyJob)->handle();

        $this->assertTrue($orphan->fresh()->ativo);
    }

    /**
     * @param  list<array<string, mixed>>  $produtos
     * @return array<string, mixed>
     */
    private function pesquisaResponse(array $produtos, int $pagina = 1, int $numeroPaginas = 1): array
    {
        return [
            'retorno' => [
                'status' => 'OK',
                'pagina' => $pagina,
                'numero_paginas' => $numeroPaginas,
                'produtos' => array_map(fn (array $p) => ['produto' => $p], $produtos),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function produtoListItem(int|string $id, string $sku, string $nome, string $situacao = 'A', float $preco = 10): array
    {
        return [
            'id' => $id,
            'codigo' => $sku,
            'nome' => $nome,
            'situacao' => $situacao,
            'preco' => $preco,
            'unidade' => 'UN',
        ];
    }
}
