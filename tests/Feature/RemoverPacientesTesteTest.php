<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoverPacientesTesteTest extends TestCase
{
    use RefreshDatabase;

    private function seedMedico(): Medico
    {
        return Medico::create([
            'apelido' => 'Dr Teste',
            'nome_legado' => 'Dr Teste',
            'crm' => '99999',
            'cpf' => '71508635900',
            'ativo' => true,
        ]);
    }

    public function test_sugestao_nao_pega_nome_real_que_contem_o_padrao(): void
    {
        $medico = $this->seedMedico();
        Paciente::create(['nome' => 'zzz teste melasma', 'medico_id' => $medico->id, 'ativo' => true]);
        // "Nicodemos" contém "demo" e "Modesto" contém "test" — não podem entrar na lista.
        Paciente::create(['nome' => 'Luciana Nicodemos Salles', 'medico_id' => $medico->id, 'ativo' => true]);
        Paciente::create(['nome' => 'Maria Modesto Prestes', 'medico_id' => $medico->id, 'ativo' => true]);

        // Nome completo: o rodapé de aviso do comando cita "Nicodemos"/"Modesto" de propósito.
        $this->artisan('pacientes:remover-teste')
            ->expectsOutputToContain('zzz teste melasma')
            ->doesntExpectOutputToContain('Luciana Nicodemos Salles')
            ->doesntExpectOutputToContain('Maria Modesto Prestes')
            ->assertSuccessful();

        $this->assertSame(3, Paciente::count());
    }

    public function test_force_sem_ids_e_recusado(): void
    {
        $medico = $this->seedMedico();
        Paciente::create(['nome' => 'ZZ TESTE', 'medico_id' => $medico->id, 'ativo' => true]);

        $this->artisan('pacientes:remover-teste', ['--force' => true])->assertFailed();

        $this->assertSame(1, Paciente::count());
    }

    public function test_remove_por_ids_e_leva_as_receitas_junto(): void
    {
        $medico = $this->seedMedico();
        $teste = Paciente::create(['nome' => 'Teste Lapidare T5', 'medico_id' => $medico->id, 'ativo' => true]);
        $real = Paciente::create(['nome' => 'Ana Maria Braga', 'medico_id' => $medico->id, 'ativo' => true]);

        foreach ([$teste, $real] as $p) {
            Receita::create([
                'paciente_id' => $p->id,
                'medico_id' => $medico->id,
                'data_receita' => '2026-01-10',
                'numero' => $p->id.'-0001',
                'status' => 'finalizada',
                'ativo' => true,
            ]);
        }

        $this->artisan('pacientes:remover-teste', ['--ids' => (string) $teste->id, '--force' => true])
            ->assertSuccessful();

        $this->assertNull(Paciente::find($teste->id));
        $this->assertSame(0, Receita::where('paciente_id', $teste->id)->count());
        $this->assertNotNull(Paciente::find($real->id));
        $this->assertSame(1, Receita::where('paciente_id', $real->id)->count());
    }
}
