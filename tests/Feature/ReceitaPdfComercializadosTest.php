<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceitaPdfComercializadosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin PDF',
            'email' => 'admin-pdf-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function makeReceitaComDoisItens(bool $umVendido): Receita
    {
        $medico = Medico::create(['nome' => 'Dr. PDF']);
        $paciente = Paciente::create(['nome' => 'Paciente PDF', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '9-pdf-01',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
        ]);

        $produtoVendido = Produto::create([
            'codigo' => 'PDF-VEND',
            'nome' => 'Produto Comercializado PDF',
            'preco' => 50,
            'ativo' => true,
        ]);
        $produtoNaoVendido = Produto::create([
            'codigo' => 'PDF-NAO',
            'nome' => 'Produto Nao Comercializado PDF',
            'preco' => 30,
            'ativo' => true,
        ]);

        ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $produtoVendido->id,
            'quantidade' => 1,
            'valor_unitario' => 50,
            'valor_total' => 50,
            'imprimir' => true,
            'vendido' => $umVendido,
            'grupo' => 'recomendado',
            'ordem' => 0,
        ]);
        ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $produtoNaoVendido->id,
            'quantidade' => 1,
            'valor_unitario' => 30,
            'valor_total' => 30,
            'imprimir' => true,
            'vendido' => false,
            'grupo' => 'recomendado',
            'ordem' => 1,
        ]);

        return $receita;
    }

    public function test_sem_comercializacao_pdf_inclui_todos_imprimir(): void
    {
        $receita = $this->makeReceitaComDoisItens(umVendido: false);
        $nomes = $receita->carregarItensParaPdf()->itens->pluck('produto.nome')->all();

        $this->assertEqualsCanonicalizing([
            'Produto Comercializado PDF',
            'Produto Nao Comercializado PDF',
        ], $nomes);
    }

    public function test_com_comercializacao_pdf_inclui_somente_vendidos(): void
    {
        $receita = $this->makeReceitaComDoisItens(umVendido: true);
        $nomes = $receita->carregarItensParaPdf()->itens->pluck('produto.nome')->all();

        $this->assertSame(['Produto Comercializado PDF'], $nomes);
    }

    public function test_rota_pdf_responde_com_application_pdf(): void
    {
        $receita = $this->makeReceitaComDoisItens(umVendido: true);

        $this->actingAs($this->admin())
            ->get(route('receitas.pdf', $receita))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
