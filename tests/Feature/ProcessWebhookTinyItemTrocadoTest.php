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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pedido faturado, reaberto no oList, com produto trocado e faturado de novo:
 * o item que saiu do pedido não pode continuar vendido nem somando no total.
 * Caso real: receita 18008-0001 / pedido 991291713 (TONALITE 4,5 → TONALITE 2).
 */
class ProcessWebhookTinyItemTrocadoTest extends TestCase
{
    use RefreshDatabase;

    private const PEDIDO = '991291713';

    private string $tinyIdTonaliteNoPedido = '';

    protected function setUp(): void
    {
        parent::setUp();

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
        $medico = Medico::create(['nome' => 'Dra. Daniela']);
        $paciente = Paciente::create(['nome' => 'Paciente Tonalite', 'medico_id' => $medico->id]);

        $hyalu = Produto::create(['codigo' => 'HYALU CREAM', 'nome' => 'HYALU CREAM', 'tiny_id' => '889820511', 'preco' => 165, 'ativo' => true]);
        $tonalite45 = Produto::create(['codigo' => 'TONALITE 4,5 30G', 'nome' => 'TONALITE 4,5', 'tiny_id' => '889822348', 'preco' => 192, 'ativo' => true]);
        Produto::create(['codigo' => 'TONALITE 2 30G', 'nome' => 'TONALITE 2', 'tiny_id' => '889822324', 'preco' => 192, 'ativo' => true]);

        $receita = Receita::create([
            'numero' => '18008-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
            'tiny_pedido_id' => self::PEDIDO,
        ]);

        $itemHyalu = ReceitaItem::create([
            'receita_id' => $receita->id, 'produto_id' => $hyalu->id, 'quantidade' => 1,
            'valor_unitario' => 165, 'valor_total' => 165, 'imprimir' => true, 'grupo' => 'recomendado', 'ordem' => 0,
        ]);
        $itemTonalite45 = ReceitaItem::create([
            'receita_id' => $receita->id, 'produto_id' => $tonalite45->id, 'quantidade' => 1,
            'valor_unitario' => 192, 'valor_total' => 192, 'imprimir' => true, 'grupo' => 'recomendado', 'ordem' => 1,
        ]);
        $receita->calcularTotais();

        return [$receita, $itemHyalu, $itemTonalite45];
    }

    private function fakePedido(string $tinyIdTonalite): void
    {
        // Http::fake não sobrescreve um stub já registrado: o callback lê o estado atual.
        $primeiraVez = $this->tinyIdTonaliteNoPedido === '';
        $this->tinyIdTonaliteNoPedido = $tinyIdTonalite;
        if (! $primeiraVez) {
            return;
        }

        Http::fake([
            'api.tiny.com.br/api2/pedido.obter.php' => fn () => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'pedido' => [
                        'id' => self::PEDIDO,
                        'numero' => '3911',
                        'situacao' => 'faturado',
                        'itens' => [
                            ['item' => ['id_produto' => '889820511', 'codigo' => 'HYALU CREAM', 'quantidade' => '1.00', 'valor_unitario' => '165.00']],
                            ['item' => ['id_produto' => $this->tinyIdTonaliteNoPedido, 'codigo' => 'TONALITE', 'quantidade' => '1.00', 'valor_unitario' => '192.00']],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    private function faturar(): void
    {
        (new ProcessWebhookTinyJob(self::PEDIDO, 'faturado', ['tipo' => 'atualizacao_pedido']))->handle();
    }

    #[Test]
    public function produto_trocado_apos_faturar_sai_do_vendido_e_do_total(): void
    {
        [$receita, , $itemTonalite45] = $this->cenario();

        $this->fakePedido('889822348');
        $this->faturar();
        $this->assertTrue((bool) $itemTonalite45->fresh()->vendido);
        $this->assertSame('357.00', (string) $receita->fresh()->valor_total);

        // Reabriram, trocaram por Tonalite 2 e faturaram de novo.
        $this->fakePedido('889822324');
        $this->faturar();

        $itemTonalite45->refresh();
        $this->assertFalse((bool) $itemTonalite45->vendido);
        $this->assertFalse((bool) $itemTonalite45->imprimir);
        $this->assertSame(0, $itemTonalite45->aquisicoes()->count());

        $vendidos = $receita->itens()->where('vendido', true)->with('produto')->get()->pluck('produto.codigo')->sort()->values()->all();
        $this->assertSame(['HYALU CREAM', 'TONALITE 2 30G'], $vendidos);
        $this->assertSame('357.00', (string) $receita->fresh()->valor_total);
    }

    #[Test]
    public function aquisicao_de_outro_pedido_e_preservada(): void
    {
        [$receita, , $itemTonalite45] = $this->cenario();

        // Comprado antes em outro pedido/atendimento.
        $itemTonalite45->update(['vendido' => true]);
        ReceitaItemAquisicao::create(['receita_item_id' => $itemTonalite45->id, 'data_aquisicao' => now()->subMonth(), 'tiny_pedido_id' => '111']);

        $this->fakePedido('889822348');
        $this->faturar();
        $this->fakePedido('889822324');
        $this->faturar();

        $itemTonalite45->refresh();
        $this->assertTrue((bool) $itemTonalite45->vendido);
        $this->assertTrue((bool) $itemTonalite45->imprimir);
        $this->assertSame(['111'], $itemTonalite45->aquisicoes()->pluck('tiny_pedido_id')->all());
    }

    #[Test]
    public function item_nunca_vendido_fora_do_pedido_nao_e_tocado(): void
    {
        [$receita, , $itemTonalite45] = $this->cenario();

        // Pedido sem o Tonalite 4,5 desde o início: prescrito, não comprado.
        $this->fakePedido('889822324');
        $this->faturar();

        $itemTonalite45->refresh();
        $this->assertFalse((bool) $itemTonalite45->vendido);
        $this->assertTrue((bool) $itemTonalite45->imprimir, 'Prescrito e não comprado continua na prescrição.');
    }
}
