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

class PacienteCanceladasOcultasTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_e_ficha_do_paciente_ignoram_receitas_canceladas(): void
    {
        Setting::set('receitas_ocultar_canceladas', true);
        [$paciente, $finalizada] = $this->cenario();

        $lista = $this->get('/pacientes?search=Canceladas');
        $row = collect($lista->viewData('page')['props']['pacientes']['data'])->firstWhere('id', $paciente->id);
        $this->assertSame($finalizada->id, (int) $row['ultima_receita_id']);

        $ficha = $this->get('/pacientes/'.$paciente->id);
        $ids = collect($ficha->viewData('page')['props']['paciente']['receitas'])->pluck('id')->all();
        $this->assertSame([$finalizada->id], $ids);
    }

    public function test_com_a_setting_desligada_canceladas_continuam_aparecendo(): void
    {
        [$paciente, $finalizada, $cancelada] = $this->cenario();

        $lista = $this->get('/pacientes?search=Canceladas');
        $row = collect($lista->viewData('page')['props']['pacientes']['data'])->firstWhere('id', $paciente->id);
        $this->assertSame($cancelada->id, (int) $row['ultima_receita_id']);

        $ficha = $this->get('/pacientes/'.$paciente->id);
        $ids = collect($ficha->viewData('page')['props']['paciente']['receitas'])->pluck('id')->all();
        $this->assertSame([$cancelada->id, $finalizada->id], $ids);
    }

    /**
     * @return array{0: Paciente, 1: Receita, 2: Receita}
     */
    private function cenario(): array
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin-canceladas-test@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $medico = Medico::create(['nome' => 'Dr. Teste']);
        $paciente = Paciente::create(['nome' => 'Paciente Canceladas', 'medico_id' => $medico->id]);

        $finalizada = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->subDay()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
        ]);
        $cancelada = Receita::create([
            'numero' => '1-0002',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'cancelada',
            'ativo' => false,
        ]);

        $this->actingAs($user);

        return [$paciente, $finalizada, $cancelada];
    }
}
