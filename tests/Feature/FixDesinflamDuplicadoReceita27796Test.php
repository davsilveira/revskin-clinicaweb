<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FixDesinflamDuplicadoReceita27796Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    private function seedCenarioDuplicado(): array
    {
        $medico = Medico::create(['nome' => 'Dr. Fix']);
        $paciente = Paciente::create(['nome' => 'Camila', 'medico_id' => $medico->id]);
        $produto = Produto::create([
            'codigo' => 'DESINFLAM CLINDA 30G',
            'nome' => 'DESINFLAM CLINDA 30G',
            'tiny_id' => '964350909',
            'preco' => 50.25,
            'ativo' => true,
        ]);

        // IDs fixos exigidos pelo comando pontual.
        $receita = new Receita([
            'numero' => '17483-0002',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
            'tiny_pedido_id' => '981442812',
        ]);
        $receita->id = 27796;
        $receita->save();

        $keep = ReceitaItem::create([
            'receita_id' => 27796,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 50.25,
            'valor_total' => 50.25,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 11,
            'vendido' => true,
        ]);
        $drop = ReceitaItem::create([
            'receita_id' => 27796,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 50.25,
            'valor_total' => 50.25,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 12,
            'vendido' => true,
        ]);

        ReceitaItemAquisicao::create([
            'receita_item_id' => $keep->id,
            'data_aquisicao' => '2026-07-31',
            'tiny_pedido_id' => '981442812',
        ]);
        ReceitaItemAquisicao::create([
            'receita_item_id' => $drop->id,
            'data_aquisicao' => '2026-07-31',
            'tiny_pedido_id' => '981442812',
        ]);

        return [$keep, $drop];
    }

    #[Test]
    public function dry_run_nao_remove_nada(): void
    {
        [$keep, $drop] = $this->seedCenarioDuplicado();

        $exit = Artisan::call('tiny:fix-desinflam-duplicado-27796');
        $this->assertSame(0, $exit);
        $this->assertNotNull(ReceitaItem::find($keep->id));
        $this->assertNotNull(ReceitaItem::find($drop->id));
    }

    #[Test]
    public function force_remove_apenas_a_linha_de_maior_id(): void
    {
        [$keep, $drop] = $this->seedCenarioDuplicado();

        $exit = Artisan::call('tiny:fix-desinflam-duplicado-27796', ['--force' => true]);
        $this->assertSame(0, $exit);
        $this->assertNotNull(ReceitaItem::find($keep->id));
        $this->assertNull(ReceitaItem::find($drop->id));
        $this->assertSame(0, ReceitaItemAquisicao::where('receita_item_id', $drop->id)->count());
        $this->assertSame(1, ReceitaItem::where('receita_id', 27796)->where('produto_id', $keep->produto_id)->count());
    }

    #[Test]
    public function aborta_se_nao_houver_duplicata(): void
    {
        [$keep] = $this->seedCenarioDuplicado();
        ReceitaItem::where('id', '!=', $keep->id)->where('receita_id', 27796)->delete();

        $exit = Artisan::call('tiny:fix-desinflam-duplicado-27796', ['--force' => true]);
        $this->assertSame(1, $exit);
        $this->assertNotNull(ReceitaItem::find($keep->id));
    }
}
