<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Item recomendado mas desmarcado (imprimir=0) que o paciente acrescenta no pedido
 * do oList precisa voltar a contar na receita — senão o valor não entra no total.
 * Caso real: receita 17401-0008 / pedido 983312351 (BIONAISSANCE R$ 39,00).
 */
class ProcessWebhookTinyItemDesmarcadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // PacienteObserver dispara SyncClienteTinyJob; não bater na API real.
        Bus::fake();

        Setting::set('tiny_enabled', true);
        Setting::set('tiny_api_version', 'v2');
        Setting::set('tiny_token', 'test-token-v2');
        (new TinyApiRateLimiter)->resetForTesting();
    }

    /**
     * @return array{0: Receita, 1: ReceitaItem, 2: ReceitaItem}
     */
    private function cenario(): array
    {
        $medico = Medico::create(['nome' => 'Dra. Giovana']);
        $paciente = Paciente::create(['nome' => 'Paciente Bionaissance', 'medico_id' => $medico->id]);

        $neodeline = Produto::create([
            'codigo' => 'R0,015H3 NEODELINE',
            'nome' => 'CREME DA NOITE R0,015H3 NEODELINE',
            'tiny_id' => '889820903',
            'preco' => 51,
            'ativo' => true,
        ]);
        $bionaissance = Produto::create([
            'codigo' => 'BIONAISSANCE',
            'nome' => 'CREME DE LIMPEZA REGENERADOR',
            'tiny_id' => '889820454',
            'preco' => 39,
            'ativo' => true,
        ]);

        $receita = Receita::create([
            'numero' => '17401-0008',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
            'tiny_pedido_id' => '983312351',
        ]);

        $itemComprado = ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $neodeline->id,
            'quantidade' => 1,
            'valor_unitario' => 51,
            'valor_total' => 51,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 0,
            'vendido' => true,
        ]);

        // Recomendado pelo médico, mas desmarcado no fechamento da receita.
        $itemDesmarcado = ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $bionaissance->id,
            'quantidade' => 1,
            'valor_unitario' => 39,
            'valor_total' => 39,
            'imprimir' => false,
            'grupo' => 'recomendado',
            'ordem' => 1,
            'vendido' => false,
        ]);

        $receita->calcularTotais();

        return [$receita, $itemComprado, $itemDesmarcado];
    }

    private function fakePedidoComOsDoisItens(): void
    {
        Http::fake([
            'api.tiny.com.br/api2/pedido.obter.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'pedido' => [
                        'id' => '983312351',
                        'numero' => '3646',
                        'situacao' => 'faturado',
                        'itens' => [
                            ['item' => [
                                'id_produto' => '889820903',
                                'codigo' => 'R0,015H3 NEODELINE',
                                'descricao' => 'CREME DA NOITE R0,015H3 NEODELINE',
                                'quantidade' => '1.00',
                                'valor_unitario' => '51.00',
                            ]],
                            ['item' => [
                                'id_produto' => '889820454',
                                'codigo' => 'BIONAISSANCE',
                                'descricao' => 'CREME DE LIMPEZA REGENERADOR',
                                'quantidade' => '1.00',
                                'valor_unitario' => '39.00',
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    #[Test]
    public function item_desmarcado_incluido_no_pedido_volta_a_contar_no_total(): void
    {
        [$receita, , $itemDesmarcado] = $this->cenario();
        $this->assertSame('51.00', (string) $receita->fresh()->valor_total);

        $this->fakePedidoComOsDoisItens();

        (new ProcessWebhookTinyJob('983312351', 'faturado', ['tipo' => 'atualizacao_pedido']))->handle();

        $itemDesmarcado->refresh();
        $this->assertTrue((bool) $itemDesmarcado->imprimir, 'Item comprado no oList deve ficar marcado.');
        $this->assertTrue((bool) $itemDesmarcado->vendido);
        $this->assertSame('39.00', (string) $itemDesmarcado->valor_total);

        $receita->refresh();
        $this->assertSame('90.00', (string) $receita->subtotal);
        $this->assertSame('90.00', (string) $receita->valor_total);
    }

    #[Test]
    public function item_fora_do_pedido_continua_desmarcado(): void
    {
        [$receita, , $itemDesmarcado] = $this->cenario();

        Http::fake([
            'api.tiny.com.br/api2/pedido.obter.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'pedido' => [
                        'id' => '983312351',
                        'numero' => '3646',
                        'situacao' => 'faturado',
                        'itens' => [
                            ['item' => [
                                'id_produto' => '889820903',
                                'codigo' => 'R0,015H3 NEODELINE',
                                'descricao' => 'CREME DA NOITE R0,015H3 NEODELINE',
                                'quantidade' => '1.00',
                                'valor_unitario' => '51.00',
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        (new ProcessWebhookTinyJob('983312351', 'faturado', ['tipo' => 'atualizacao_pedido']))->handle();

        $itemDesmarcado->refresh();
        $this->assertFalse((bool) $itemDesmarcado->imprimir);
        $this->assertFalse((bool) $itemDesmarcado->vendido);
        $this->assertSame('51.00', (string) $receita->fresh()->valor_total);
    }

    #[Test]
    public function comando_de_correcao_conserta_receita_ja_afetada(): void
    {
        [$receita, , $itemDesmarcado] = $this->cenario();

        // Estado deixado pelo bug: vendido + aquisição do pedido, mas imprimir=0.
        $itemDesmarcado->update(['vendido' => true]);
        $itemDesmarcado->aquisicoes()->create([
            'data_aquisicao' => now(),
            'tiny_pedido_id' => '983312351',
        ]);
        $receita->refresh()->calcularTotais();
        $this->assertSame('51.00', (string) $receita->fresh()->valor_total);

        $this->artisan('tiny:corrigir-itens-vendidos-nao-impressos')
            ->assertExitCode(0);

        $this->assertFalse((bool) $itemDesmarcado->fresh()->imprimir, 'Dry-run não pode gravar.');

        $this->artisan('tiny:corrigir-itens-vendidos-nao-impressos', ['--force' => true])
            ->assertExitCode(0);

        $this->assertTrue((bool) $itemDesmarcado->fresh()->imprimir);
        $this->assertSame('90.00', (string) $receita->fresh()->valor_total);
    }
}
