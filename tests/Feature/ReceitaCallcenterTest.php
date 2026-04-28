<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceitaCallcenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCallcenterUser(): User
    {
        return User::create([
            'name' => 'Call Center',
            'email' => 'cc-receita-test@example.com',
            'password' => Hash::make('password'),
            'role' => 'callcenter',
            'is_active' => true,
        ]);
    }

    private function seedReceitaAberta(): array
    {
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'aberta',
        ]);

        return [$receita, $medico, $paciente];
    }

    public function test_callcenter_copiar_from_open_child_fails_validation(): void
    {
        $user = $this->makeCallcenterUser();
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);
        $orig = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'aberta',
        ]);
        $child = Receita::create([
            'numero' => '1-0002',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'receita_origem_id' => $orig->id,
            'status' => 'aberta',
        ]);

        $this->actingAs($user);
        $this->from(route('receitas.show', $child));
        $this->post(route('receitas.copiar', $child))
            ->assertSessionHasErrors('copiar');
    }

    public function test_callcenter_copiar_success_redirects_to_show_with_same_medico(): void
    {
        [$receita, $medico] = $this->seedReceitaAberta();
        $user = $this->makeCallcenterUser();
        $this->actingAs($user);
        $this->from(route('receitas.show', $receita));

        $this->post(route('receitas.copiar', $receita))
            ->assertStatus(302);

        $nova = Receita::query()->where('receita_origem_id', $receita->id)->first();
        $this->assertNotNull($nova);
        $this->assertSame((int) $medico->id, (int) $nova->medico_id);
        $this->get(route('receitas.show', $nova->fresh()))->assertOk();
    }

    public function test_callcenter_cannot_access_receita_edit(): void
    {
        [$receita] = $this->seedReceitaAberta();
        $this->actingAs($this->makeCallcenterUser());

        $this->get(route('receitas.edit', $receita))
            ->assertStatus(403);
    }

    public function test_callcenter_cannot_update_receita_via_put(): void
    {
        [$receita] = $this->seedReceitaAberta();
        $this->actingAs($this->makeCallcenterUser());

        $this->put(route('receitas.update', $receita), [
            'data_receita' => $receita->data_receita->format('Y-m-d'),
            'itens' => [],
        ])->assertStatus(403);
    }

    public function test_callcenter_can_finalizar_receita_aberta(): void
    {
        [$receita] = $this->seedReceitaAberta();
        $this->actingAs($this->makeCallcenterUser());

        $this->post(route('receitas.finalizar', $receita))
            ->assertRedirect(route('receitas.show', $receita));

        $receita->refresh();
        $this->assertSame('finalizada', $receita->status);
    }

    public function test_callcenter_cannot_destroy_receita(): void
    {
        [$receita] = $this->seedReceitaAberta();
        $this->actingAs($this->makeCallcenterUser());

        $this->delete(route('receitas.destroy', $receita))
            ->assertStatus(403);

        $receita->refresh();
        $this->assertSame('aberta', $receita->status);
    }

    public function test_medico_cannot_view_receita_of_inaccessible_paciente(): void
    {
        $m1 = Medico::create(['nome' => 'Dr. Um']);
        $m2 = Medico::create(['nome' => 'Dr. Dois']);
        $paciente = Paciente::create(['nome' => 'P', 'medico_id' => $m1->id]);
        $receita = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $m1->id,
            'status' => 'aberta',
        ]);
        $user = User::create([
            'name' => 'M2',
            'email' => 'm2@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $m2->id,
            'is_active' => true,
        ]);
        $this->actingAs($user);
        $this->get(route('receitas.show', $receita))->assertStatus(403);
    }
}
