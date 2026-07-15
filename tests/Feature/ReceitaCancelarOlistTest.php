<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\Setting;
use App\Models\User;
use App\Services\TinyErpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ReceitaCancelarOlistTest extends TestCase
{
    use RefreshDatabase;

    private function makeMedicoUser(?Medico $medico = null): array
    {
        $medico ??= Medico::create(['nome' => 'Dr. Cancel Test']);
        $user = User::create([
            'name' => 'Médico Cancel',
            'email' => 'medico-cancel-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);

        return [$user, $medico];
    }

    private function makeReceita(Medico $medico, array $attrs = []): Receita
    {
        $paciente = Paciente::create([
            'nome' => 'Paciente Cancel',
            'medico_id' => $medico->id,
        ]);

        return Receita::create(array_merge([
            'numero' => '9-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
        ], $attrs));
    }

    private function bindTinyClientReturning(mixed $situacao, string $status = 'success'): void
    {
        $client = Mockery::mock(TinyErpClient::class);
        $client->shouldReceive('obterPedido')->andReturn([
            'status' => $status,
            'data' => ['situacao' => $situacao],
            'message' => $status === 'success' ? null : 'erro',
        ]);
        $this->app->instance(TinyErpClient::class, $client);
    }

    public function test_medico_pode_cancelar_receita_finalizada_sem_pedido_olist(): void
    {
        Bus::fake();
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico);

        $this->actingAs($user)
            ->delete(route('receitas.destroy', $receita))
            ->assertRedirect(route('receitas.index'));

        $receita->refresh();
        $this->assertSame('cancelada', $receita->status);
        $this->assertFalse((bool) $receita->ativo);
    }

    public function test_pode_cancelar_permite_sem_pedido(): void
    {
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico);

        $this->actingAs($user)
            ->getJson(route('receitas.pode-cancelar', $receita))
            ->assertOk()
            ->assertJson([
                'allowed' => true,
                'checked_olist' => false,
            ]);
    }

    public function test_pode_cancelar_bloqueia_quando_olist_faturado(): void
    {
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico, ['tiny_pedido_id' => '12345']);
        $this->bindTinyClientReturning('faturado');

        $this->actingAs($user)
            ->getJson(route('receitas.pode-cancelar', $receita))
            ->assertOk()
            ->assertJson([
                'allowed' => false,
                'checked_olist' => true,
                'situacao_label' => 'Faturado',
            ]);
    }

    public function test_pode_cancelar_permite_quando_olist_aberto(): void
    {
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico, ['tiny_pedido_id' => '12345']);
        $this->bindTinyClientReturning('aberto');

        $this->actingAs($user)
            ->getJson(route('receitas.pode-cancelar', $receita))
            ->assertOk()
            ->assertJson([
                'allowed' => true,
                'checked_olist' => true,
            ]);
    }

    public function test_destroy_bloqueia_quando_olist_entregue(): void
    {
        Bus::fake();
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico, ['tiny_pedido_id' => '999']);
        $this->bindTinyClientReturning('entregue');

        $this->actingAs($user)
            ->from(route('receitas.show', $receita))
            ->delete(route('receitas.destroy', $receita))
            ->assertRedirect(route('receitas.show', $receita))
            ->assertSessionHas('error');

        $receita->refresh();
        $this->assertSame('finalizada', $receita->status);
        $this->assertTrue((bool) $receita->ativo);
    }

    public function test_destroy_permite_quando_olist_aberto(): void
    {
        Bus::fake();
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico, ['tiny_pedido_id' => '888']);
        $this->bindTinyClientReturning('aberto');

        $this->actingAs($user)
            ->delete(route('receitas.destroy', $receita))
            ->assertRedirect(route('receitas.index'));

        $receita->refresh();
        $this->assertSame('cancelada', $receita->status);
    }

    public function test_cancelar_e_duplicar_cria_copia_e_cancela_origem(): void
    {
        Bus::fake();
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico);

        $this->actingAs($user)
            ->post(route('receitas.cancelar-e-duplicar', $receita))
            ->assertRedirect();

        $receita->refresh();
        $this->assertSame('cancelada', $receita->status);
        $this->assertFalse((bool) $receita->ativo);

        $nova = Receita::query()->where('receita_origem_id', $receita->id)->first();
        $this->assertNotNull($nova);
        $this->assertSame('aberta', $nova->status);
        $this->assertTrue((bool) $nova->ativo);
    }

    public function test_cancelar_e_duplicar_bloqueia_quando_olist_faturado(): void
    {
        Bus::fake();
        Setting::set('tiny_enabled', true);
        [$user, $medico] = $this->makeMedicoUser();
        $receita = $this->makeReceita($medico, ['tiny_pedido_id' => '777']);
        $this->bindTinyClientReturning('faturado');

        $this->actingAs($user)
            ->from(route('receitas.show', $receita))
            ->post(route('receitas.cancelar-e-duplicar', $receita))
            ->assertRedirect(route('receitas.show', $receita))
            ->assertSessionHas('error');

        $receita->refresh();
        $this->assertSame('finalizada', $receita->status);
        $this->assertNull(Receita::query()->where('receita_origem_id', $receita->id)->first());
    }
}
