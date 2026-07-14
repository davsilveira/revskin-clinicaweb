<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReestabelecerPacientesMedicoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Monta o estado quebrado do bug: usuário ligado a um médico novo e vazio,
     * pacientes presos num médico órfão (mesmo CRM, sem usuário).
     *
     * @return array{0: User, 1: Medico, 2: Medico} [user, orfao, novoVazio]
     */
    private function seedEstadoQuebrado(): array
    {
        $orfao = Medico::create(['crm' => '283163', 'uf_crm' => 'SP', 'nome_legado' => 'Giovana Naccarato']);
        $novo = Medico::create(['crm' => '283163', 'uf_crm' => 'SP']);

        $user = User::create([
            'name' => 'Dra. Giovana',
            'email' => 'giovana@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $novo->id, // ligada ao médico NOVO e vazio
            'is_active' => true,
        ]);

        $p1 = Paciente::create(['nome' => 'Paciente A', 'medico_id' => $orfao->id]);
        Paciente::create(['nome' => 'Paciente B', 'medico_id' => $orfao->id]);
        Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $p1->id,
            'medico_id' => $orfao->id,
            'status' => 'aberta',
        ]);

        return [$user, $orfao, $novo];
    }

    public function test_dry_run_nao_altera_nada(): void
    {
        [$user, $orfao, $novo] = $this->seedEstadoQuebrado();

        $this->artisan('medicos:reestabelecer-pacientes', ['email' => 'giovana@example.com'])
            ->assertSuccessful();

        // nada muda no dry-run
        $this->assertSame(2, Paciente::where('medico_id', $orfao->id)->count());
        $this->assertSame(0, Paciente::where('medico_id', $novo->id)->count());
    }

    public function test_force_move_pacientes_e_receitas_para_o_medico_atual(): void
    {
        [$user, $orfao, $novo] = $this->seedEstadoQuebrado();

        $this->artisan('medicos:reestabelecer-pacientes', [
            'email' => 'giovana@example.com',
            '--force' => true,
        ])->assertSuccessful();

        // pacientes e receitas migram para o médico ao qual o usuário está ligado
        $this->assertSame(0, Paciente::where('medico_id', $orfao->id)->count());
        $this->assertSame(2, Paciente::where('medico_id', $novo->id)->count());
        $this->assertSame(1, Receita::where('medico_id', $novo->id)->count());

        // login/vínculo do usuário não muda
        $user->refresh();
        $this->assertSame($novo->id, $user->medico_id);

        // a médica volta a enxergar os pacientes
        $this->assertTrue($user->canAccessPaciente(Paciente::first()));

        // médico de origem mantido por padrão (sem --apagar-origem)
        $this->assertNotNull(Medico::find($orfao->id));
    }

    public function test_apagar_origem_remove_o_medico_vazio(): void
    {
        [$user, $orfao] = $this->seedEstadoQuebrado();

        $this->artisan('medicos:reestabelecer-pacientes', [
            'email' => 'giovana@example.com',
            '--force' => true,
            '--apagar-origem' => true,
        ])->assertSuccessful();

        $this->assertNull(Medico::find($orfao->id));
    }

    public function test_origem_explicita_e_respeitada(): void
    {
        [$user, $orfao, $novo] = $this->seedEstadoQuebrado();

        $this->artisan('medicos:reestabelecer-pacientes', [
            'email' => 'giovana@example.com',
            '--origem' => $orfao->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Paciente::where('medico_id', $novo->id)->count());
    }

    public function test_vinculo_apenas_na_pivot_nao_impede_reparo(): void
    {
        // Cenário real de produção: o médico órfão tem uma linha remanescente na
        // pivot user_medico (vínculo secundário), mas nenhum usuário PRINCIPAL.
        [$user, $orfao, $novo] = $this->seedEstadoQuebrado();
        \Illuminate\Support\Facades\DB::table('user_medico')->insert([
            'user_id' => $user->id,
            'medico_id' => $orfao->id,
        ]);

        $this->artisan('medicos:reestabelecer-pacientes', [
            'email' => 'giovana@example.com',
            '--force' => true,
        ])->assertSuccessful();

        // auto-detecção ignora o vínculo de pivot e move mesmo assim
        $this->assertSame(2, Paciente::where('medico_id', $novo->id)->count());
    }

    public function test_recusa_origem_com_usuario_vinculado(): void
    {
        [$user, $orfao, $novo] = $this->seedEstadoQuebrado();

        // vincula um usuário ao "órfão" -> deixa de ser órfão
        User::create([
            'name' => 'Outro Médico',
            'email' => 'outro@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $orfao->id,
            'is_active' => true,
        ]);

        $this->artisan('medicos:reestabelecer-pacientes', [
            'email' => 'giovana@example.com',
            '--origem' => $orfao->id,
            '--force' => true,
        ])->assertFailed();

        // nada foi movido
        $this->assertSame(2, Paciente::where('medico_id', $orfao->id)->count());
    }
}
