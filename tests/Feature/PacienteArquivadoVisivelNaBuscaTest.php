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
 * Job f8b5e9c5: paciente com receita de junho/2026 ficou `ativo=0` na carga do CLW2 e o Assistente
 * de Receita respondia "Nenhum paciente encontrado" para a médica que a atendia — que então
 * recadastrava a mesma pessoa. A busca do dia a dia continua só com ficha ativa; o painel de
 * candidatos é quem conta a verdade, e escolher a ficha reativa.
 */
class PacienteArquivadoVisivelNaBuscaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Medico, 2: Paciente} */
    private function cenarioFichaArquivada(): array
    {
        $medico = Medico::create(['apelido' => 'Dra Sullege', 'ativo' => true]);
        $user = User::create([
            'name' => 'Dra Sullege',
            'email' => 'sullege.teste@revskin.com.br',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);

        $paciente = Paciente::create([
            'nome' => 'Fanilde Pirro Viana Paquer',
            'cpf' => '850.146.051-68',
            'data_nascimento' => '1978-06-09',
            'celular' => '(65) 98111-5111',
            'ativo' => false,
        ]);
        MedicoPaciente::create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'ativo' => false,
            'origem' => 'import',
        ]);

        return [$user, $medico, $paciente];
    }

    public function test_busca_do_dia_a_dia_continua_sem_ficha_arquivada(): void
    {
        [$user, , $paciente] = $this->cenarioFichaArquivada();

        $resp = $this->actingAs($user)->getJson('/api/pacientes/search?q=Fanilde');

        $resp->assertOk();
        $this->assertNotContains($paciente->id, collect($resp->json())->pluck('id')->all());
    }

    public function test_candidatos_mostra_ficha_arquivada_marcada(): void
    {
        [$user, , $paciente] = $this->cenarioFichaArquivada();

        $resp = $this->actingAs($user)->getJson('/api/pacientes/candidatos?nome=Fanilde');

        $resp->assertOk();
        $linha = collect($resp->json('candidatos'))->firstWhere('id', $paciente->id);
        $this->assertNotNull($linha, 'ficha arquivada tem de aparecer nos candidatos');
        $this->assertTrue($linha['arquivado']);
    }

    public function test_ativas_vem_antes_das_arquivadas(): void
    {
        [$user] = $this->cenarioFichaArquivada();
        $ativa = Paciente::create(['nome' => 'Fanilde Outra Pessoa', 'ativo' => true]);

        $resp = $this->actingAs($user)->getJson('/api/pacientes/candidatos?nome=Fanilde');

        $resp->assertOk();
        $this->assertSame($ativa->id, $resp->json('candidatos.0.id'));
    }

    public function test_selecionar_ficha_arquivada_reativa_e_devolve_a_busca(): void
    {
        [$user, $medico, $paciente] = $this->cenarioFichaArquivada();

        $this->actingAs($user)
            ->postJson("/api/pacientes/{$paciente->id}/vincular")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue((bool) $paciente->fresh()->ativo);
        $this->assertDatabaseHas('medico_paciente', [
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'ativo' => 1,
        ]);

        $resp = $this->actingAs($user)->getJson('/api/pacientes/search?q=Fanilde');
        $resp->assertOk();
        $this->assertContains($paciente->id, collect($resp->json())->pluck('id')->all());
    }
}
