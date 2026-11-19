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

    public function test_receita_aberta_em_visualizacao_tambem_salva_anotacoes(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();
        $receita->update(['status' => 'aberta']);

        $this->actingAs($this->admin());

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $item->id, 'anotacoes' => 'Anotei sem entrar em Editar']],
        ])->assertOk();

        $this->assertSame('Anotei sem entrar em Editar', $item->fresh()->anotacoes);
    }

    /** Clínica (19/11/2026): o campo de anotações tem de ser sempre editável, inclusive em cancelada. */
    public function test_receita_cancelada_tambem_salva_anotacoes(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();
        $receita->update(['status' => 'cancelada']);

        $this->actingAs($this->admin());

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $item->id, 'anotacoes' => 'Anotei na cancelada']],
        ])->assertOk();

        $this->assertSame('Anotei na cancelada', $item->fresh()->anotacoes);
    }

    /**
     * Receita 28608 (Valéria, 17/11/2026): Finalizar recria as linhas e a tela continuou com os
     * ids antigos; o PATCH respondia "itens.0.id inválido" e a anotação do Demerane se perdia.
     */
    public function test_ids_antigos_apos_finalizar_sao_remapeados_pela_posicao_e_produto(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();
        $idAntigo = $item->id;
        $receita->itens()->delete();
        $novo = $receita->itens()->create([
            'produto_id' => $item->produto_id,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'valor_total' => 10,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 0,
        ]);

        $this->actingAs($this->admin());

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $idAntigo, 'produto_id' => $item->produto_id, 'anotacoes' => 'Pode não fazer agora']],
        ])->assertOk();

        $this->assertSame('Pode não fazer agora', $novo->fresh()->anotacoes);
    }

    public function test_id_antigo_sem_linha_correspondente_retorna_409_sem_gravar(): void
    {
        [$receita, $item] = $this->seedReceitaFinalizadaComItem();

        $this->actingAs($this->admin());

        $this->patchJson(route('receitas.itens-anotacoes.patch', $receita), [
            'itens' => [['id' => $item->id + 999, 'produto_id' => $item->produto_id + 999, 'anotacoes' => 'X']],
        ])->assertStatus(409);

        $this->assertSame('Original', $item->fresh()->anotacoes);
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
