<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\Setting;
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
    public function test_copiar_receita_cancelada_fails(): void
    {
        $user = $this->makeAdminUser();
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);

        $a = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'cancelada',
        ]);

        $this->actingAs($user);
        $this->from(route('receitas.edit', $a));
        $this->post(route('receitas.copiar', $a))
            ->assertSessionHasErrors('copiar');

        $this->assertFalse(Receita::query()->where('receita_origem_id', $a->id)->exists());
    }

    public function test_index_oculta_canceladas_com_a_setting_ligada(): void
    {
        Setting::set('receitas_ocultar_canceladas', true);
        $user = $this->makeAdminUser();
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);
        foreach (['aberta', 'finalizada', 'cancelada'] as $i => $status) {
            Receita::create([
                'numero' => '1-000'.($i + 1),
                'data_receita' => now()->toDateString(),
                'paciente_id' => $paciente->id,
                'medico_id' => $medico->id,
                'status' => $status,
            ]);
        }

        $this->actingAs($user);
        $statuses = fn ($res) => collect($res->viewData('page')['props']['receitas']['data'])->pluck('status')->sort()->values()->all();

        $padrao = $this->get('/receitas');
        $this->assertSame(['aberta', 'finalizada'], $statuses($padrao));
        $this->assertSame(1, $padrao->viewData('page')['props']['canceladasOcultas']);

        $this->assertSame(['cancelada'], $statuses($this->get('/receitas?status=cancelada')));
        $this->assertSame(['aberta', 'cancelada', 'finalizada'], $statuses($this->get('/receitas?status=todas')));
        $this->assertSame(['aberta', 'finalizada'], $statuses($this->get('/receitas?paciente_id='.$paciente->id)));
    }

    public function test_index_mostra_canceladas_com_a_setting_desligada(): void
    {
        $user = $this->makeAdminUser();
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente T', 'medico_id' => $medico->id]);
        foreach (['aberta', 'finalizada', 'cancelada'] as $i => $status) {
            Receita::create([
                'numero' => '1-000'.($i + 1),
                'data_receita' => now()->toDateString(),
                'paciente_id' => $paciente->id,
                'medico_id' => $medico->id,
                'status' => $status,
            ]);
        }

        $this->actingAs($user);
        $statuses = fn ($res) => collect($res->viewData('page')['props']['receitas']['data'])->pluck('status')->sort()->values()->all();

        $padrao = $this->get('/receitas');
        $this->assertSame(['aberta', 'cancelada', 'finalizada'], $statuses($padrao));
        $this->assertSame(0, $padrao->viewData('page')['props']['canceladasOcultas']);
        $this->assertFalse($padrao->viewData('page')['props']['ocultarCanceladas']);
        $this->assertSame(['aberta', 'cancelada', 'finalizada'], $statuses($this->get('/receitas?paciente_id='.$paciente->id)));
    }

    public function test_admin_liga_a_setting_nas_configuracoes(): void
    {
        $this->actingAs($this->makeAdminUser())
            ->put(route('settings.receitas.update'), ['ocultar_canceladas' => true])
            ->assertRedirect();
        $this->assertTrue(Receita::ocultarCanceladas());

        $this->put(route('settings.receitas.update'), ['ocultar_canceladas' => false])->assertRedirect();
        $this->assertFalse(Receita::ocultarCanceladas());
    }
}
