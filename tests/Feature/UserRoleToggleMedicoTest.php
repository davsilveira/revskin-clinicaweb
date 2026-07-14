<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleToggleMedicoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-toggle@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function medicoPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dra. Giovana',
            'email' => 'giovana-toggle@example.com',
            'role' => 'medico',
            'is_active' => true,
            'crm' => '283163',
            'uf_crm' => 'SP',
            'telefone' => '1140028922',
            'celular' => '',
        ], $overrides);
    }

    /**
     * Ao alternar médico -> admin -> médico, os pacientes devem continuar
     * vinculados ao mesmo médico (não pode criar um médico novo e órfão).
     */
    public function test_toggling_medico_to_admin_and_back_preserves_patients(): void
    {
        $admin = $this->admin();

        // Cria médica com pacientes
        $medico = Medico::create(['crm' => '283163', 'uf_crm' => 'SP', 'email1' => 'giovana-toggle@example.com']);
        $medicoUser = User::create([
            'name' => 'Dra. Giovana',
            'email' => 'giovana-toggle@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);
        Paciente::create(['nome' => 'Paciente A', 'medico_id' => $medico->id]);
        Paciente::create(['nome' => 'Paciente B', 'medico_id' => $medico->id]);

        $this->assertSame(2, Paciente::where('medico_id', $medico->id)->count());

        // 1) admin transforma em admin
        $this->actingAs($admin)
            ->put(route('users.update', $medicoUser), [
                'name' => 'Dra. Giovana',
                'email' => 'giovana-toggle@example.com',
                'role' => 'admin',
                'is_active' => true,
            ])
            ->assertRedirect();

        $medicoUser->refresh();
        $this->assertSame('admin', $medicoUser->role);
        // vínculo com o médico é preservado
        $this->assertSame($medico->id, $medicoUser->medico_id);

        // Os dados profissionais (CRM etc.) NÃO desaparecem: o registro de médico
        // continua existindo e acessível via a relação user->medico.
        $this->assertNotNull(Medico::find($medico->id));
        $this->assertSame('283163', $medicoUser->medico->crm);
        $this->assertSame('SP', $medicoUser->medico->uf_crm);

        // 2) admin transforma de volta em médico
        $this->actingAs($admin)
            ->put(route('users.update', $medicoUser), $this->medicoPayload())
            ->assertRedirect();

        $medicoUser->refresh();

        // Deve reaproveitar o MESMO médico, não criar um novo
        $this->assertSame($medico->id, $medicoUser->medico_id, 'medico_id deveria permanecer o mesmo');
        $this->assertSame(1, Medico::count(), 'não deveria ter criado um médico novo');

        // Pacientes continuam visíveis para a médica
        $this->assertSame(2, Paciente::where('medico_id', $medicoUser->medico_id)->count());
        $paciente = Paciente::first();
        $this->assertTrue($medicoUser->canAccessPaciente($paciente));
    }
}
