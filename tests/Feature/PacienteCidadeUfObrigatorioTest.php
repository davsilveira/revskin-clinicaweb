<?php

namespace Tests\Feature;

use App\Http\Controllers\PacienteController;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PacienteCidadeUfObrigatorioTest extends TestCase
{
    use RefreshDatabase;

    private const CPF_VALIDO = '111.444.777-35';

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

    private function payloadNovo(array $override = []): array
    {
        return array_merge([
            'nome' => 'Ana Costa',
            'data_nascimento' => '1991-06-15',
            'celular' => '(19) 98888-7777',
            'cpf' => self::CPF_VALIDO,
            'email1' => 'ana.costa@example.com',
            'pais' => 'Brasil',
            'cidade' => 'Campinas',
            'uf' => 'SP',
        ], $override);
    }

    public function test_cadastro_novo_sem_cidade_e_barrado(): void
    {
        [$user] = $this->medicoComUser('cid1@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payloadNovo([
                'cidade' => '',
                'uf' => 'SP',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cidade');
    }

    public function test_cadastro_novo_sem_uf_e_barrado(): void
    {
        [$user] = $this->medicoComUser('cid2@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payloadNovo([
                'cidade' => 'Campinas',
                'uf' => '',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('uf');
    }

    public function test_cadastro_novo_com_cidade_e_uf_salva(): void
    {
        [$user] = $this->medicoComUser('cid3@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payloadNovo())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('pacientes', [
            'email1' => 'ana.costa@example.com',
            'cidade' => 'Campinas',
            'uf' => 'SP',
        ]);
    }

    public function test_cadastro_legado_sem_cidade_continua_editavel(): void
    {
        [$user, $medico] = $this->medicoComUser('cid4@revskin.com.br');
        $paciente = Paciente::create([
            'nome' => 'João Legado',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 98888-7777',
            'email1' => 'joao.legado@example.com',
            'pais' => 'Brasil',
            'medico_id' => $medico->id,
        ]);
        $paciente->medicos()->syncWithoutDetaching([$medico->id]);

        $this->actingAs($user)->putJson(route('pacientes.update', $paciente), [
            'nome' => 'João Legado',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 90000-0000',
            'email1' => 'joao.legado@example.com',
            'pais' => 'Brasil',
        ])->assertOk();

        $this->assertSame('(11) 90000-0000', $paciente->fresh()->celular);
    }

    public function test_vincular_existente_sem_cidade_nao_exige_cidade(): void
    {
        [$user] = $this->medicoComUser('cid5@revskin.com.br');
        $paciente = Paciente::create([
            'nome' => 'Cliente oList',
            'data_nascimento' => '1975-01-01',
            'celular' => '(11) 91111-2222',
            'email1' => 'olist@example.com',
            'pais' => 'Brasil',
            'tiny_id' => '888001',
        ]);

        $this->actingAs($user)->postJson(route('pacientes.store'), [
            'nome' => 'Cliente oList',
            'data_nascimento' => '1975-01-01',
            'celular' => '(11) 91111-2222',
            'email1' => 'olist@example.com',
            'pais' => 'Brasil',
            'paciente_existente_id' => $paciente->id,
        ])->assertOk();

        $this->assertSame(1, Paciente::count());
    }

    public function test_quick_create_sem_cidade_e_barrado(): void
    {
        [$user] = $this->medicoComUser('cid6@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.quickCreate'), $this->payloadNovo([
                'cidade' => '',
                'uf' => '',
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.cidade.0', PacienteController::MSG_CIDADE_OBRIGATORIA);
    }
}
