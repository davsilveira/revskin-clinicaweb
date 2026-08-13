<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReativarPacientesArquivadosTest extends TestCase
{
    use RefreshDatabase;

    private function medico(string $crm, string $cpf): Medico
    {
        return Medico::create([
            'apelido' => 'Dr '.$crm,
            'nome_legado' => 'Dr '.$crm,
            'crm' => $crm,
            'cpf' => $cpf,
            'ativo' => true,
        ]);
    }

    private function receita(Paciente $paciente, Medico $medico, string $data): Receita
    {
        return Receita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => $data,
            'numero' => $paciente->id.'-0001',
            'status' => 'finalizada',
            'ativo' => true,
        ]);
    }

    private function vinculo(Paciente $paciente, Medico $medico, bool $ativo): void
    {
        DB::table('medico_paciente')->insert([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'ativo' => $ativo,
            'origem' => 'import',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_lista_candidatos_sem_alterar_nada(): void
    {
        $medico = $this->medico('11111', '71508635900');
        $arquivada = Paciente::create(['nome' => 'z-Fanilde Pirro Viana Paquer', 'medico_id' => $medico->id, 'ativo' => false]);
        $this->receita($arquivada, $medico, '2026-06-11');
        $this->vinculo($arquivada, $medico, false);

        // Arquivada sem receita nenhuma: não é candidata (ninguém prescreveu para ela).
        Paciente::create(['nome' => 'Sem Receita Nenhuma', 'medico_id' => $medico->id, 'ativo' => false]);

        $this->artisan('pacientes:reativar')
            ->expectsOutputToContain('z-Fanilde')
            ->doesntExpectOutputToContain('Sem Receita Nenhuma')
            ->assertSuccessful();

        $this->assertFalse((bool) $arquivada->fresh()->ativo);
        $this->assertSame(0, (int) DB::table('medico_paciente')->where('paciente_id', $arquivada->id)->value('ativo'));
    }

    public function test_force_sem_ids_e_recusado(): void
    {
        $medico = $this->medico('22222', '71508635900');
        $p = Paciente::create(['nome' => 'z-Alguem Silva', 'medico_id' => $medico->id, 'ativo' => false]);
        $this->receita($p, $medico, '2026-01-02');

        $this->artisan('pacientes:reativar', ['--force' => true])->assertFailed();

        $this->assertFalse((bool) $p->fresh()->ativo);
    }

    public function test_reativa_cadastro_vinculo_de_quem_prescreveu_e_limpa_o_prefixo(): void
    {
        $prescritor = $this->medico('33333', '71508635900');
        $outro = $this->medico('44444', '52998224725');

        $p = Paciente::create(['nome' => 'z-Fanilde Pirro Viana Paquer', 'medico_id' => $prescritor->id, 'ativo' => false]);
        $this->receita($p, $prescritor, '2026-06-11');
        $this->vinculo($p, $prescritor, false);
        // Vínculo inativo de médico sem receita: arquivamento pode ser decisão dele, não mexer.
        $this->vinculo($p, $outro, false);

        $this->artisan('pacientes:reativar', [
            '--ids' => (string) $p->id,
            '--limpar-prefixo' => true,
            '--force' => true,
        ])->assertSuccessful();

        $p->refresh();
        $this->assertTrue((bool) $p->ativo);
        $this->assertSame('Fanilde Pirro Viana Paquer', $p->nome);
        $this->assertSame(1, (int) DB::table('medico_paciente')->where('paciente_id', $p->id)->where('medico_id', $prescritor->id)->value('ativo'));
        $this->assertSame(0, (int) DB::table('medico_paciente')->where('paciente_id', $p->id)->where('medico_id', $outro->id)->value('ativo'));
    }

    public function test_simulacao_por_ids_nao_grava(): void
    {
        $medico = $this->medico('55555', '71508635900');
        $p = Paciente::create(['nome' => 'zzzFanilde Paquer', 'medico_id' => $medico->id, 'ativo' => false]);
        $this->receita($p, $medico, '2025-06-24');
        $this->vinculo($p, $medico, false);

        $this->artisan('pacientes:reativar', ['--ids' => (string) $p->id, '--limpar-prefixo' => true])
            ->assertSuccessful();

        $p->refresh();
        $this->assertFalse((bool) $p->ativo);
        $this->assertSame('zzzFanilde Paquer', $p->nome);
    }

    public function test_nome_de_gente_que_comeca_com_z_ou_x_nao_e_cortado(): void
    {
        $medico = $this->medico('66666', '71508635900');
        $zilda = Paciente::create(['nome' => 'Zilda Maria Souza', 'medico_id' => $medico->id, 'ativo' => false]);
        $this->receita($zilda, $medico, '2026-02-02');
        $xuxa = Paciente::create(['nome' => 'Xuxa Meneghel', 'medico_id' => $medico->id, 'ativo' => false]);
        $this->receita($xuxa, $medico, '2026-02-03');
        // Marcador sem sobrenome: sem como saber se é pessoa de verdade, o nome fica igual.
        $marcelo = Paciente::create(['nome' => 'zzz-Marcelo1', 'medico_id' => $medico->id, 'ativo' => false]);
        $this->receita($marcelo, $medico, '2025-05-21');

        $this->artisan('pacientes:reativar', [
            '--ids' => implode(',', [$zilda->id, $xuxa->id, $marcelo->id]),
            '--limpar-prefixo' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame('Zilda Maria Souza', $zilda->fresh()->nome);
        $this->assertSame('Xuxa Meneghel', $xuxa->fresh()->nome);
        $this->assertSame('zzz-Marcelo1', $marcelo->fresh()->nome);
    }

    public function test_paciente_com_busca_do_medico_volta_a_achar(): void
    {
        $medico = $this->medico('77777', '71508635900');
        $p = Paciente::create(['nome' => 'z-Fanilde Pirro Viana Paquer', 'medico_id' => $medico->id, 'ativo' => false]);
        $this->receita($p, $medico, '2026-06-11');
        $this->vinculo($p, $medico, false);

        $achaComoNoApp = fn () => Paciente::query()
            ->where('ativo', true)
            ->where('nome', 'like', '%Fanilde Pirro Viana Paquer%')
            ->whereHas('medicos', fn ($q) => $q->where('medicos.id', $medico->id)->where('medico_paciente.ativo', true))
            ->count();

        $this->assertSame(0, $achaComoNoApp());

        $this->artisan('pacientes:reativar', [
            '--ids' => (string) $p->id,
            '--limpar-prefixo' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, $achaComoNoApp());
    }
}
