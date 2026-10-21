<?php

namespace Tests\Feature;

use App\Jobs\CriarPedidoTinyJob;
use App\Jobs\ProcessWebhookTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Caso real 17964-0001/0002 (19/10/2026): cortesia não zerava no oList nem no ClinicaWeb, e o
 * "faturado" de um pedido já cancelado marcava como vendidos os itens da receita cancelada.
 */
class ReceitaCortesiaTinyTest extends TestCase
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

    private function receita(array $attrs = []): Receita
    {
        $medico = Medico::create(['nome' => 'Dr. Luiz']);
        $paciente = Paciente::create(['nome' => 'Mara Teste', 'medico_id' => $medico->id]);
        $synchron = Produto::create([
            'codigo' => 'SYNCHRON', 'nome' => 'CREME DO DIA SYNCHRON',
            'tiny_id' => '889820540', 'preco' => 203, 'ativo' => true,
        ]);
        $tonalite = Produto::create([
            'codigo' => 'TONALITE 3,5 30G', 'nome' => 'BB CREAM TONALITE 3,5',
            'tiny_id' => '889822328', 'preco' => 192, 'ativo' => true,
        ]);

        $receita = Receita::create(array_merge([
            'numero' => '17964-0002',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
            'cortesia' => true,
        ], $attrs));

        foreach ([[$synchron, 203], [$tonalite, 192]] as $i => [$produto, $valor]) {
            ReceitaItem::create([
                'receita_id' => $receita->id,
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'valor_unitario' => $valor,
                'valor_total' => $valor,
                'imprimir' => true,
                'grupo' => 'recomendado',
                'ordem' => $i,
            ]);
        }

        $receita->calcularTotais();

        return $receita->fresh();
    }

    #[Test]
    public function cortesia_zera_o_total_da_receita_mantendo_o_frete(): void
    {
        $receita = $this->receita(['valor_frete' => 25]);

        $this->assertSame('395.00', (string) $receita->subtotal);
        $this->assertSame('395.00', (string) $receita->desconto_valor);
        $this->assertSame('25.00', (string) $receita->valor_total);

        $receita->update(['valor_frete' => 0]);
        $receita->calcularTotais();
        $this->assertSame('0.00', (string) $receita->fresh()->valor_total);
    }

    #[Test]
    public function pedido_de_cortesia_vai_ao_olist_com_desconto_de_100_por_cento(): void
    {
        $receita = $this->receita(['valor_frete' => 25]);

        Http::fake([
            'api.tiny.com.br/api2/pedido.incluir.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'registros' => ['registro' => ['sequencia' => '1', 'status' => 'OK', 'id' => 992888301, 'numero' => '3963']],
                ],
            ]),
        ]);

        (new CriarPedidoTinyJob($receita))->handle();

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'pedido.incluir.php')) {
                return false;
            }
            $pedido = json_decode($request['pedido'], true)['pedido'];

            // Preço de tabela continua nos itens; o desconto zera o total.
            $this->assertSame('203.00', $pedido['itens'][0]['item']['valor_unitario']);
            $this->assertSame('395.00', $pedido['valor_desconto']);
            $this->assertSame('25.00', $pedido['valor_frete']);

            return true;
        });

        $this->assertSame('992888301', $receita->fresh()->tiny_pedido_id);
    }

    #[Test]
    public function faturado_no_olist_nao_marca_vendido_em_receita_cancelada(): void
    {
        $receita = $this->receita([
            'numero' => '17964-0001',
            'cortesia' => false,
            'status' => 'cancelada',
            'ativo' => false,
            'tiny_pedido_id' => '992887964',
        ]);

        Http::fake();

        (new ProcessWebhookTinyJob('992887964', 'faturado', ['tipo' => 'atualizacao_pedido']))->handle();

        Http::assertNothingSent();
        $this->assertSame(0, $receita->itens()->where('vendido', true)->count());
        $this->assertSame(0, $receita->itens()->whereHas('aquisicoes')->count());
    }
}
