<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessWebhookTinyMergeLockTest extends TestCase
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

    #[Test]
    public function middleware_serializa_por_pedido_id(): void
    {
        $job = new ProcessWebhookTinyJob('981442812', 'faturado', ['tipo' => 'atualizacao_pedido']);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    #[Test]
    public function segundo_merge_nao_duplica_linha_nova_ja_inserida(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Merge']);
        $paciente = Paciente::create(['nome' => 'Paciente Merge', 'medico_id' => $medico->id]);
        $neodeline = Produto::create([
            'codigo' => 'R0,005H3 NEODELINE',
            'nome' => 'R0,005H3 NEODELINE',
            'tiny_id' => '889820750',
            'preco' => 204,
            'ativo' => true,
        ]);
        $desinflam = Produto::create([
            'codigo' => 'DESINFLAM CLINDA 30G',
            'nome' => 'DESINFLAM CLINDA 30G',
            'tiny_id' => '964350909',
            'preco' => 201,
            'ativo' => true,
        ]);

        $receita = Receita::create([
            'numero' => '17483-0002',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
            'tiny_pedido_id' => '981442812',
        ]);
        ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $neodeline->id,
            'quantidade' => 1,
            'valor_unitario' => 204,
            'valor_total' => 204,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 0,
            'vendido' => false,
        ]);

        Http::fake([
            'api.tiny.com.br/api2/pedido.obter.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'pedido' => [
                        'id' => '981442812',
                        'numero' => '3588',
                        'situacao' => 'faturado',
                        'itens' => [
                            ['item' => [
                                'id_produto' => '889820750',
                                'codigo' => 'R0,005H3 NEODELINE',
                                'descricao' => 'R0,005H3 NEODELINE',
                                'quantidade' => '1.00',
                                'valor_unitario' => '51.00',
                            ]],
                            ['item' => [
                                'id_produto' => '964350909',
                                'codigo' => 'DESINFLAM CLINDA 30G',
                                'descricao' => 'DESINFLAM CLINDA 30G',
                                'quantidade' => '1.00',
                                'valor_unitario' => '50.25',
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        $payload = ['tipo' => 'atualizacao_pedido'];
        (new ProcessWebhookTinyJob('981442812', 'faturado', $payload))->handle();
        (new ProcessWebhookTinyJob('981442812', 'faturado', $payload))->handle();

        $desinflamCount = ReceitaItem::where('receita_id', $receita->id)
            ->where('produto_id', $desinflam->id)
            ->count();
        $this->assertSame(1, $desinflamCount);

        $item = ReceitaItem::where('receita_id', $receita->id)
            ->where('produto_id', $desinflam->id)
            ->first();
        $this->assertTrue((bool) $item->vendido);
        $this->assertSame('50.25', (string) $item->valor_unitario);
        $this->assertSame(1, ReceitaItemAquisicao::where('receita_item_id', $item->id)
            ->where('tiny_pedido_id', '981442812')
            ->count());
    }
}
