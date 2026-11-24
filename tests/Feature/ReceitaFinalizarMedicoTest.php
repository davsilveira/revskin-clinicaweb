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

class ReceitaFinalizarMedicoTest extends TestCase
{
    use RefreshDatabase;

    private function seedMedicoComReceitaAberta(): array
    {
        $medico = Medico::create(['nome' => 'Dra. Finalizar']);
        $user = User::create([
            'name' => 'Dra. Finalizar',
            'email' => 'medico-finalizar@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);
        $produto = Produto::create([
            'codigo' => 'FIN-001',
            'nome' => 'Produto Finalizar',
            'preco' => 50,
            'ativo' => true,
            'legado_somente_leitura' => false,
        ]);
        $paciente = Paciente::create([
            'nome' => 'Paciente Finalizar',
            'medico_id' => $medico->id,
            'cpf' => '529.982.247-25',
        ]);
        $receita = Receita::create([
            'numero' => '88-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'aberta',
        ]);
        $item = ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 50,
            'valor_total' => 50,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 0,
        ]);

        return [$user, $receita, $item, $produto];
    }

    private function putPayload(Receita $receita, ReceitaItem $item, Produto $produto, ?int $itemIdOverride = null): array
    {
        return [
            'data_receita' => $receita->data_receita->format('Y-m-d'),
            'anotacoes' => null,
            'desconto_percentual' => 0,
            'valor_caixa' => 0,
            'valor_frete' => 0,
            'status' => 'finalizada',
            'itens' => [
                [
                    'id' => $itemIdOverride ?? $item->id,
                    'produto_id' => $produto->id,
                    'local_uso' => null,
                    'anotacoes' => null,
                    'quantidade' => 1,
                    'valor_unitario' => 50,
                    'imprimir' => true,
                    'grupo' => 'recomendado',
                ],
            ],
        ];
    }

    public function test_medico_finaliza_via_put_com_ids_validos(): void
    {
        [$user, $receita, $item, $produto] = $this->seedMedicoComReceitaAberta();

        $this->actingAs($user)
            ->put(route('receitas.update', $receita), $this->putPayload($receita, $item, $produto))
            ->assertRedirect(route('receitas.show', $receita));

        $this->assertSame('finalizada', $receita->fresh()->status);
    }

    public function test_medico_finaliza_via_put_com_ids_stale_apos_autosave(): void
    {
        [$user, $receita, $item, $produto] = $this->seedMedicoComReceitaAberta();

        // Simula payload do front ainda com id antigo (autosave já recriou a linha).
        $this->actingAs($user)
            ->put(
                route('receitas.update', $receita),
                $this->putPayload($receita, $item, $produto, itemIdOverride: 999001)
            )
            ->assertRedirect(route('receitas.show', $receita));

        $this->assertSame('finalizada', $receita->fresh()->status);
        $this->assertDatabaseHas('receita_itens', [
            'receita_id' => $receita->id,
            'produto_id' => $produto->id,
        ]);
        $this->assertDatabaseMissing('receita_itens', [
            'id' => $item->id,
        ]);
    }

    public function test_callcenter_finalizar_endpoint_ainda_funciona(): void
    {
        [, $receita] = $this->seedMedicoComReceitaAberta();
        $cc = User::create([
            'name' => 'CC Finalizar',
            'email' => 'cc-finalizar@example.com',
            'password' => Hash::make('password'),
            'role' => 'callcenter',
            'is_active' => true,
        ]);

        $this->actingAs($cc)
            ->post(route('receitas.finalizar', $receita))
            ->assertRedirect(route('receitas.show', $receita));

        $this->assertSame('finalizada', $receita->fresh()->status);
    }
    /** Linha com o produto apagado (×): antes derrubava o autosave e o Finalizar com 422. */
    private function linhaEmBranco(): array
    {
        return [
            'id' => null,
            'produto_id' => '',
            'local_uso' => '',
            'anotacoes' => '',
            'quantidade' => 1,
            'valor_unitario' => 0,
            'imprimir' => false,
            'grupo' => 'recomendado',
        ];
    }

    public function test_autosave_ignora_linhas_sem_produto_e_salva_as_demais(): void
    {
        [$user, $receita, $item, $produto] = $this->seedMedicoComReceitaAberta();
        $outro = Produto::create([
            'codigo' => 'FIN-002',
            'nome' => 'Creme da noite novo',
            'preco' => 80,
            'ativo' => true,
            'legado_somente_leitura' => false,
        ]);

        $payload = $this->putPayload($receita, $item, $produto);
        $payload['itens'][0]['produto_id'] = $outro->id;
        $payload['itens'][0]['valor_unitario'] = 80;
        $payload['itens'][] = $this->linhaEmBranco();
        $payload['itens'][] = $this->linhaEmBranco();

        $this->actingAs($user)
            ->postJson('/api/receitas/autosave', array_merge($payload, [
                'id' => $receita->id,
                'paciente_id' => $receita->paciente_id,
                'medico_id' => $receita->medico_id,
                'status' => 'aberta',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'itens');

        $itens = $receita->fresh()->itens;
        $this->assertCount(1, $itens);
        $this->assertSame($outro->id, (int) $itens->first()->produto_id);
    }

    public function test_finalizar_via_put_ignora_linhas_sem_produto(): void
    {
        [$user, $receita, $item, $produto] = $this->seedMedicoComReceitaAberta();

        $payload = $this->putPayload($receita, $item, $produto);
        array_unshift($payload['itens'], $this->linhaEmBranco());
        $payload['itens'][] = $this->linhaEmBranco();

        $this->actingAs($user)
            ->put(route('receitas.update', $receita), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('receitas.show', $receita));

        $this->assertSame('finalizada', $receita->fresh()->status);
        $this->assertCount(1, $receita->fresh()->itens);
    }
}
