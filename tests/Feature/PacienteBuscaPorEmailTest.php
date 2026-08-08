<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\User;
use App\Services\PacienteVinculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Busca na listagem /pacientes e no autocomplete deve achar por e-mail (email1 e email2).
 */
class PacienteBuscaPorEmailTest extends TestCase
{
    use RefreshDatabase;

    private function medicoComUser(string $email): array
    {
        $medico = Medico::create(['apelido' => 'Dr '.$email]);
        $user = User::create([
            'name' => 'Dr '.$email,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);

        return [$user, $medico];
    }

    private function pacienteVinculado(Medico $medico, User $user, array $attrs): Paciente
    {
        $paciente = Paciente::create(array_merge([
            'nome' => 'Ana Maria Costa',
            'ativo' => true,
        ], $attrs));

        app(PacienteVinculoService::class)->garantir($paciente, $medico->id, ['ativo' => true], $user->id);

        return $paciente;
    }

    public function test_listagem_encontra_por_email1_parcial_e_completo(): void
    {
        [$user, $medico] = $this->medicoComUser('busca-email1@revskin.com.br');
        $paciente = $this->pacienteVinculado($medico, $user, [
            'email1' => 'ana.costa@clinicaz.com.br',
        ]);

        $this->actingAs($user)
            ->get('/pacientes?ativo=1&search='.urlencode('ana.costa@clinicaz.com.br'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pacientes/Index')
                ->where('pacientes.data', fn ($rows) => collect($rows)->pluck('id')->contains($paciente->id))
            );

        $this->actingAs($user)
            ->get('/pacientes?ativo=1&search='.urlencode('ana.costa'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pacientes.data', fn ($rows) => collect($rows)->pluck('id')->contains($paciente->id))
            );
    }

    public function test_listagem_encontra_por_email2(): void
    {
        [$user, $medico] = $this->medicoComUser('busca-email2@revskin.com.br');
        $paciente = $this->pacienteVinculado($medico, $user, [
            'email1' => null,
            'email2' => 'secundario@exemplo.com',
        ]);

        $this->actingAs($user)
            ->get('/pacientes?ativo=1&search='.urlencode('secundario@exemplo.com'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pacientes.data', fn ($rows) => collect($rows)->pluck('id')->contains($paciente->id))
            );
    }

    public function test_api_search_encontra_por_email(): void
    {
        [$user, $medico] = $this->medicoComUser('busca-api-email@revskin.com.br');
        $paciente = $this->pacienteVinculado($medico, $user, [
            'email1' => 'api.email@exemplo.com',
        ]);

        $resp = $this->actingAs($user)->getJson('/api/pacientes/search?q='.urlencode('api.email@exemplo.com'));
        $resp->assertOk();
        $this->assertTrue(collect($resp->json())->pluck('id')->contains($paciente->id));
    }

    public function test_busca_por_nome_e_cpf_seguem_funcionando(): void
    {
        [$user, $medico] = $this->medicoComUser('busca-regressao@revskin.com.br');
        $paciente = $this->pacienteVinculado($medico, $user, [
            'nome' => 'Bruno Oliveira',
            'cpf' => '11144477735',
            'email1' => 'bruno@exemplo.com',
            'codigo' => 'BR-42',
        ]);

        foreach (['Bruno', '11144477735', 'BR-42'] as $term) {
            $this->actingAs($user)
                ->get('/pacientes?ativo=1&search='.urlencode($term))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('pacientes.data', fn ($rows) => collect($rows)->pluck('id')->contains($paciente->id))
                );
        }
    }
}
