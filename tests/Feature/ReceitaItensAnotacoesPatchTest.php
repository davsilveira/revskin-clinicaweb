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

class ReceitaItensAnotacoesPatchTest extends TestCase
{
    use RefreshDatabase;

    private function seedProduto(): Produto
    {
        return Produto::create([
            'codigo' => 'T-P001',
            'nome' => 'Produto Teste',
            'preco' => 10,
            'ativo' => true,
        ]);
    }

    private function seedReceitaFinalizadaComItem(): array
    {
        $medico = Medico::create(['nome' => 'Dr. Patch']);
        $produto = $this->seedProduto();
        $paciente = Paciente::create(['nome' => 'Paciente Patch', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '9-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
        ]);
        $item = ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'valor_total' => 10,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 0,
            'anotacoes' => 'Original',
        ]);

        return [$receita, $item, $medico, $paciente];
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Patch',
            'email' => 'admin-patch-anotacoes@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_pode_atualizar_anotacoes_em_receita_finalizada(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();
        $this->actingAs($this->admin());

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [
                ['id' => $item->id, 'anotacoes' => 'Nota interna atualizada'],
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $item->refresh();
        $this->assertSame('Nota interna atualizada', $item->anotacoes);
    }

    public function test_medico_dono_pode_atualizar_anotacoes(): void
    {
        [$receita, $item, $medico] = $this->seedReceitaFinalizadaComItem();
        $user = User::create([
            'name' => 'Med Patch',
            'email' => 'med-patch-anotacoes@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $item->id, 'anotacoes' => 'Médico editou']],
        ])->assertOk();

        $item->refresh();
        $this->assertSame('Médico editou', $item->anotacoes);
    }

    public function test_medico_de_outra_receita_recebe_403(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();
        $outro = Medico::create(['nome' => 'Dr. Outro']);
        $user = User::create([
            'name' => 'Med Outro',
            'email' => 'med-outro-patch@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $outro->id,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $item->id, 'anotacoes' => 'X']],
        ])->assertForbidden();

        $item->refresh();
        $this->assertSame('Original', $item->anotacoes);
    }

    public function test_receita_aberta_retorna_422(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();
        $receita->update(['status' => 'aberta']);

        $this->actingAs($this->admin());

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $item->id, 'anotacoes' => 'X']],
        ])->assertStatus(422);
    }

    public function test_call_center_nao_acessa_rota(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();
        $user = User::create([
            'name' => 'CC',
            'email' => 'cc-patch-anotacoes@example.com',
            'password' => Hash::make('password'),
            'role' => 'callcenter',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $item->id, 'anotacoes' => 'X']],
        ])->assertForbidden();
    }

    public function test_admin_pode_atualizar_anotacoes_internas_da_receita_finalizada(): void
    {
        [$receita] = $this->seedReceitaFinalizadaComItem();
        $receita->update(['anotacoes' => 'Nota antiga']);
        $this->actingAs($this->admin());

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'anotacoes' => 'Ajuste climático — paciente em Campinas',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Ajuste climático — paciente em Campinas', $receita->fresh()->anotacoes);
    }

    public function test_medico_dono_pode_atualizar_anotacoes_internas_e_por_produto(): void
    {
        [$receita, $item, $medico] = $this->seedReceitaFinalizadaComItem();
        $user = User::create([
            'name' => 'Med Patch Internas',
            'email' => 'med-patch-internas@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'anotacoes' => 'Interna do médico',
            'itens' => [['id' => $item->id, 'anotacoes' => 'Linha atualizada']],
        ])->assertOk();

        $this->assertSame('Interna do médico', $receita->fresh()->anotacoes);
        $this->assertSame('Linha atualizada', $item->fresh()->anotacoes);
    }

    public function test_autosave_em_receita_finalizada_retorna_422(): void
    {
        [$receita, , $medico, $paciente] = $this->seedReceitaFinalizadaComItem();
        $this->actingAs($this->admin());

        $this->postJson(route('receitas.autosave'), [
            'id' => $receita->id,
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => $receita->data_receita->format('Y-m-d'),
            'itens' => [],
        ])
            ->assertStatus(422);
    }
}
