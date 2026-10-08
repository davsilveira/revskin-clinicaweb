<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FixTonaliteTrocadoReceita28242Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function troca_o_produto_e_remove_a_linha_duplicada(): void
    {
        Bus::fake();

        $medico = Medico::create(['nome' => 'Dra. Daniela']);
        $paciente = Paciente::create(['nome' => 'Paciente Tonalite', 'medico_id' => $medico->id]);
        $outro = Produto::create(['codigo' => 'OUTROS', 'nome' => 'OUTROS', 'tiny_id' => '1', 'preco' => 1335, 'ativo' => true]);
        $t45 = Produto::create(['codigo' => 'TONALITE 4,5 30G', 'nome' => 'TONALITE 4,5', 'tiny_id' => '889822348', 'preco' => 192, 'ativo' => true]);
        $t2 = Produto::create(['codigo' => 'TONALITE 2 30G', 'nome' => 'TONALITE 2', 'tiny_id' => '889822324', 'preco' => 192, 'ativo' => true]);

        $receita = new Receita([
            'numero' => '18008-0001', 'data_receita' => now()->toDateString(), 'paciente_id' => $paciente->id,
            'medico_id' => $medico->id, 'status' => 'finalizada', 'ativo' => true, 'tiny_pedido_id' => '991291713',
        ]);
        $receita->id = 28242;
        $receita->save();

        $base = ['receita_id' => 28242, 'quantidade' => 1, 'imprimir' => true, 'vendido' => true, 'grupo' => 'recomendado'];
        ReceitaItem::create($base + ['produto_id' => $outro->id, 'valor_unitario' => 1335, 'valor_total' => 1335, 'ordem' => 0]);
        $errado = new ReceitaItem($base + ['produto_id' => $t45->id, 'valor_unitario' => 192, 'valor_total' => 192, 'ordem' => 3, 'anotacoes' => 'Tom 2']);
        $errado->id = 349612;
        $errado->save();
        $dup = new ReceitaItem($base + ['produto_id' => $t2->id, 'valor_unitario' => 192, 'valor_total' => 192, 'ordem' => 11]);
        $dup->id = 349795;
        $dup->save();
        foreach ([349612, 349795] as $id) {
            ReceitaItemAquisicao::create(['receita_item_id' => $id, 'data_aquisicao' => now(), 'tiny_pedido_id' => '991291713']);
        }
        $receita->calcularTotais();
        $this->assertSame('1719.00', (string) $receita->fresh()->valor_total);

        $this->artisan('tiny:fix-tonalite-trocado-28242')->assertExitCode(0);
        $this->assertNotNull(ReceitaItem::find(349795), 'Dry-run não pode gravar.');

        $this->artisan('tiny:fix-tonalite-trocado-28242', ['--force' => true])->assertExitCode(0);

        $this->assertNull(ReceitaItem::find(349795));
        $this->assertSame(0, ReceitaItemAquisicao::where('receita_item_id', 349795)->count());
        $item = ReceitaItem::find(349612);
        $this->assertSame($t2->id, $item->produto_id);
        $this->assertSame(3, $item->ordem);
        $this->assertSame(1, $item->aquisicoes()->count());
        $this->assertSame('1527.00', (string) $receita->fresh()->valor_total);

        // Rodar de novo não faz nada.
        $this->artisan('tiny:fix-tonalite-trocado-28242', ['--force' => true])->assertExitCode(1);
    }
}
