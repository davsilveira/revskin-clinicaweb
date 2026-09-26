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
 * Admin edita inline os campos privados de cada vínculo médico↔paciente no drawer.
 */
class AdminEditaVinculoMedicoTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?int $medicoId = null): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'@revskin.com.br',
            'password' => Hash::make('password'),
            'role' => $role,
            'medico_id' => $medicoId,
            'is_active' => true,
        ]);
    }

    private function cenario(): array
    {
        $medicoA = Medico::create(['apelido' => 'Dr A']);
        $medicoB = Medico::create(['apelido' => 'Dr B']);
        $paciente = Paciente::create(['nome' => 'Maria Teste', 'medico_id' => $medicoA->id]);
        MedicoPaciente::create(['medico_id' => $medicoA->id, 'paciente_id' => $paciente->id, 'codigo' => 'A-1', 'anotacoes' => 'nota A']);
        MedicoPaciente::create(['medico_id' => $medicoB->id, 'paciente_id' => $paciente->id, 'codigo' => 'B-1']);

        return [$paciente, $medicoA, $medicoB];
    }

    public function test_admin_edita_vinculo_de_um_medico_sem_tocar_no_outro(): void
    {
        [$paciente, $medicoA, $medicoB] = $this->cenario();
        $admin = $this->user('admin');

        $this->actingAs($admin)->putJson(route('pacientes.vinculos.update', [$paciente, $medicoB]), [
            'indicado_por' => '  Instagram ',
            'codigo' => 'B-99',
            'anotacoes' => "linha 1\nlinha 2",
        ])->assertOk()->assertJsonPath('success', true);

        $pivotB = $paciente->vinculoDoMedico($medicoB->id);
        $this->assertSame('Instagram', $pivotB->indicado_por);
        $this->assertSame('B-99', $pivotB->codigo);
        $this->assertSame("linha 1\nlinha 2", $pivotB->anotacoes);
        $this->assertSame($admin->id, $pivotB->updated_by_user_id);

        $pivotA = $paciente->vinculoDoMedico($medicoA->id);
        $this->assertSame('A-1', $pivotA->codigo);
        $this->assertSame('nota A', $pivotA->anotacoes);
    }

    public function test_campo_vazio_limpa_o_valor(): void
    {
        [$paciente, $medicoA] = $this->cenario();

        $this->actingAs($this->user('admin'))->putJson(route('pacientes.vinculos.update', [$paciente, $medicoA]), [
            'indicado_por' => '', 'codigo' => '', 'anotacoes' => '',
        ])->assertOk();

        $pivotA = $paciente->vinculoDoMedico($medicoA->id);
        $this->assertNull($pivotA->codigo);
        $this->assertNull($pivotA->anotacoes);
    }

    public function test_codigo_duplicado_no_mesmo_medico_e_recusado(): void
    {
        [$paciente, $medicoA] = $this->cenario();
        $outro = Paciente::create(['nome' => 'Outro', 'medico_id' => $medicoA->id]);
        MedicoPaciente::create(['medico_id' => $medicoA->id, 'paciente_id' => $outro->id, 'codigo' => 'A-2']);

        $this->actingAs($this->user('admin'))->putJson(route('pacientes.vinculos.update', [$paciente, $medicoA]), [
            'codigo' => 'A-2',
        ])->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_nao_admin_nao_edita(): void
    {
        [$paciente, $medicoA] = $this->cenario();

        $this->actingAs($this->user('medico', $medicoA->id))->putJson(route('pacientes.vinculos.update', [$paciente, $medicoA]), [
            'codigo' => 'X',
        ])->assertForbidden();
    }

    public function test_vinculo_inexistente_da_404(): void
    {
        [$paciente] = $this->cenario();
        $semVinculo = Medico::create(['apelido' => 'Dr C']);

        $this->actingAs($this->user('admin'))->putJson(route('pacientes.vinculos.update', [$paciente, $semVinculo]), [
            'codigo' => 'X',
        ])->assertNotFound();
    }
}
