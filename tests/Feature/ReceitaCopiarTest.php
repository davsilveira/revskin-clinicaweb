<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceitaCopiarTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-copiar-test@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_copiar_sets_receita_origem_id_on_new_receita(): void
    {
        $user = $this->makeAdminUser();
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);

        $a = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'aberta',
        ]);

        $this->actingAs($user);
        $this->from(route('receitas.edit', $a));
        $this->post(route('receitas.copiar', $a))
            ->assertRedirect();

        $b = Receita::query()->where('receita_origem_id', $a->id)->first();
        $this->assertNotNull($b);
        $this->assertSame('aberta', $b->status);
    }

    public function test_copiar_from_open_child_duplicate_fails(): void
    {
        $user = $this->makeAdminUser();
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);

        $a = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'aberta',
        ]);

        $this->actingAs($user);
        $this->from(route('receitas.edit', $a));
        $this->post(route('receitas.copiar', $a))->assertRedirect();

        $b = Receita::query()->where('receita_origem_id', $a->id)->firstOrFail();

        $this->from(route('receitas.edit', $b));
        $this->post(route('receitas.copiar', $b))
            ->assertSessionHasErrors('copiar');
    }

    public function test_copiar_allowed_from_child_after_finalized(): void
    {
        $user = $this->makeAdminUser();
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);

        $a = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'aberta',
        ]);

        $this->actingAs($user);
        $this->from(route('receitas.edit', $a));
        $this->post(route('receitas.copiar', $a))->assertRedirect();

        $b = Receita::query()->where('receita_origem_id', $a->id)->firstOrFail();
        $b->update(['status' => 'finalizada']);

        $this->from(route('receitas.show', $b->fresh()));
        $this->post(route('receitas.copiar', $b->fresh()))->assertRedirect();

        $c = Receita::query()->where('receita_origem_id', $b->id)->first();
        $this->assertNotNull($c);
        $this->assertSame('aberta', $c->status);
    }
}
