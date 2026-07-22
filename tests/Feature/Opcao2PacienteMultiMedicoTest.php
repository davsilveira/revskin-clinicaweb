<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\MedicoPaciente;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Opção 2 — cadastro único de paciente com vínculo por médico.
 */
class Opcao2PacienteMultiMedicoTest extends TestCase
{
    use RefreshDatabase;

    // CPFs válidos (passam no dígito verificador).
    private const CPF_A = '111.444.777-35';

    private const CPF_B = '529.982.247-25';

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

    private function payloadPaciente(array $override = []): array
    {
        return array_merge([
            'nome' => 'João da Silva',
            'data_nascimento' => '1990-01-01',
            'cpf' => self::CPF_A,
            'celular' => '(11) 99999-9999',
            'email1' => 'joao@example.com',
        ], $override);
    }

    public function test_dois_medicos_compartilham_um_paciente_com_campos_privados_isolados(): void
    {
        [$userA, $medicoA] = $this->medicoComUser('a@revskin.com.br');
        [$userB, $medicoB] = $this->medicoComUser('b@revskin.com.br');

        // Médico A cadastra o paciente com seus campos privados.
        $this->actingAs($userA)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'codigo' => 'A-001',
            'indicado_por' => 'Indicação do A',
            'anotacoes' => 'Notas privadas do A',
        ]))->assertOk();

        $this->assertDatabaseCount('pacientes', 1);
        $paciente = Paciente::first();

        // Médico B cadastra "o mesmo CPF" → NÃO cria novo paciente, cria vínculo.
        $this->actingAs($userB)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'codigo' => 'B-777',
            'indicado_por' => 'Indicação do B',
            'anotacoes' => 'Notas privadas do B',
        ]))->assertOk();

        $this->assertDatabaseCount('pacientes', 1); // continua 1 paciente
        $this->assertEquals(2, MedicoPaciente::where('paciente_id', $paciente->id)->count());

        $pivotA = $paciente->vinculoDoMedico($medicoA->id);
        $pivotB = $paciente->vinculoDoMedico($medicoB->id);

        $this->assertSame('A-001', $pivotA->codigo);
        $this->assertSame('Notas privadas do A', $pivotA->anotacoes);
        $this->assertSame('B-777', $pivotB->codigo);
        $this->assertSame('Notas privadas do B', $pivotB->anotacoes);
    }

    public function test_medico_so_acessa_paciente_com_quem_tem_vinculo(): void
    {
        [$userA, $medicoA] = $this->medicoComUser('a2@revskin.com.br');
        [$userB, $medicoB] = $this->medicoComUser('b2@revskin.com.br');

        $paciente = Paciente::create(['nome' => 'Maria', 'cpf' => self::CPF_A, 'medico_id' => $medicoA->id]);
        MedicoPaciente::create(['medico_id' => $medicoA->id, 'paciente_id' => $paciente->id, 'ativo' => true]);

        $this->assertTrue($userA->canAccessPaciente($paciente));
        $this->assertFalse($userB->canAccessPaciente($paciente->fresh()));

        // B ganha vínculo → passa a acessar
        MedicoPaciente::create(['medico_id' => $medicoB->id, 'paciente_id' => $paciente->id, 'ativo' => true]);
        $this->assertTrue($userB->canAccessPaciente($paciente->fresh()));
    }

    public function test_codigo_e_unico_por_medico_nao_global(): void
    {
        [$userA, $medicoA] = $this->medicoComUser('a3@revskin.com.br');
        [$userB, $medicoB] = $this->medicoComUser('b3@revskin.com.br');

        // A usa código X para paciente 1
        $this->actingAs($userA)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'cpf' => self::CPF_A, 'codigo' => 'X-1',
        ]))->assertOk();

        // B pode usar o MESMO código X para outro paciente (é privado por médico)
        $this->actingAs($userB)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'nome' => 'Outro', 'cpf' => self::CPF_B, 'codigo' => 'X-1',
        ]))->assertOk();

        // Mas A não pode reusar X-1 num segundo paciente dele
        $this->actingAs($userA)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'nome' => 'Segundo do A', 'cpf' => self::CPF_B, 'codigo' => 'X-1',
        ]))->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_arquivar_e_por_vinculo(): void
    {
        [$userA, $medicoA] = $this->medicoComUser('a4@revskin.com.br');
        [$userB, $medicoB] = $this->medicoComUser('b4@revskin.com.br');

        $paciente = Paciente::create(['nome' => 'Ana', 'cpf' => self::CPF_A, 'medico_id' => $medicoA->id]);
        MedicoPaciente::create(['medico_id' => $medicoA->id, 'paciente_id' => $paciente->id, 'ativo' => true]);
        MedicoPaciente::create(['medico_id' => $medicoB->id, 'paciente_id' => $paciente->id, 'ativo' => true]);

        // A arquiva → só o vínculo de A fica inativo; paciente global segue ativo
        $this->actingAs($userA)->delete(route('pacientes.destroy', $paciente))->assertRedirect();

        $this->assertFalse((bool) $paciente->vinculoDoMedico($medicoA->id)->ativo);
        $this->assertTrue((bool) $paciente->vinculoDoMedico($medicoB->id)->ativo);
        $this->assertTrue((bool) $paciente->fresh()->ativo);
    }

    public function test_lookup_por_cpf_retorna_dados_principais(): void
    {
        [$userA, $medicoA] = $this->medicoComUser('a5@revskin.com.br');
        [$userB, $medicoB] = $this->medicoComUser('b5@revskin.com.br');

        $paciente = Paciente::create(['nome' => 'Carlos', 'cpf' => self::CPF_A, 'medico_id' => $medicoA->id]);
        MedicoPaciente::create(['medico_id' => $medicoA->id, 'paciente_id' => $paciente->id, 'ativo' => true]);

        // Médico B faz lookup: acha o paciente e sabe que ainda não tem vínculo.
        $resp = $this->actingAs($userB)->getJson(route('pacientes.lookup', ['cpf' => self::CPF_A]));
        $resp->assertOk()
            ->assertJson(['found' => true, 'ja_vinculado' => false])
            ->assertJsonPath('paciente.nome', 'Carlos');
    }

    public function test_admin_ve_privados_por_medico_no_index(): void
    {
        [$userA, $medicoA] = $this->medicoComUser('a6@revskin.com.br');
        [$userB, $medicoB] = $this->medicoComUser('b6@revskin.com.br');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin6@revskin.com.br',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($userA)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'codigo' => 'A-100',
            'indicado_por' => 'Indicação A',
            'anotacoes' => 'Obs A',
        ]))->assertOk();

        $paciente = Paciente::first();

        $this->actingAs($userB)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'codigo' => 'B-200',
            'indicado_por' => 'Indicação B',
            'anotacoes' => 'Obs B',
        ]))->assertOk();

        $resp = $this->actingAs($admin)->get(route('pacientes.index'));
        $resp->assertOk();

        $lista = collect($resp->original->getData()['page']['props']['pacientes']['data'] ?? []);
        $row = $lista->firstWhere('id', $paciente->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('privados_por_medico', $row);
        $privados = collect($row['privados_por_medico']);
        $this->assertCount(2, $privados);
        $this->assertTrue($privados->contains(fn ($v) => ($v['codigo'] ?? null) === 'A-100' && ($v['anotacoes'] ?? null) === 'Obs A'));
        $this->assertTrue($privados->contains(fn ($v) => ($v['codigo'] ?? null) === 'B-200' && ($v['indicado_por'] ?? null) === 'Indicação B'));
    }

    public function test_admin_update_nao_zera_privados_do_medico(): void
    {
        [$userA, $medicoA] = $this->medicoComUser('a7@revskin.com.br');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin7@revskin.com.br',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($userA)->postJson(route('pacientes.store'), $this->payloadPaciente([
            'codigo' => 'KEEP-1',
            'indicado_por' => 'Manter indicação',
            'anotacoes' => 'Manter obs',
        ]))->assertOk();

        $paciente = Paciente::first();

        $this->actingAs($admin)->putJson(route('pacientes.update', $paciente), $this->payloadPaciente([
            'codigo' => '',
            'indicado_por' => '',
            'anotacoes' => '',
            'medico_id' => $medicoA->id,
        ]))->assertOk();

        $pivot = $paciente->vinculoDoMedico($medicoA->id);
        $this->assertSame('KEEP-1', $pivot->codigo);
        $this->assertSame('Manter indicação', $pivot->indicado_por);
        $this->assertSame('Manter obs', $pivot->anotacoes);
    }
}
