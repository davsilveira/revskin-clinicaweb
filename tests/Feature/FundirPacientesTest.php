<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Services\Pacientes\FusaoPacientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fusão de cadastros repetidos de paciente.
 */
class FundirPacientesTest extends TestCase
{
    use RefreshDatabase;

    private const CPF_A = '111.444.777-35';

    private const CPF_B = '529.982.247-25';

    private function paciente(array $attrs = []): Paciente
    {
        return Paciente::create(array_merge(['nome' => 'Maria Souza'], $attrs));
    }

    private function fusao(): FusaoPacientes
    {
        return app(FusaoPacientes::class);
    }

    public function test_completa_campo_vazio_e_apaga_a_casca(): void
    {
        $fica = $this->paciente(['data_nascimento' => '1980-05-10']);
        $sai = $this->paciente([
            'cpf' => self::CPF_A,
            'celular' => '(11) 98888-1234',
            'email1' => 'maria@exemplo.com',
            'tiny_id' => '999001',
        ]);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $this->assertTrue($r['ok'], $r['erro'] ?? '');
        $fica->refresh();
        $this->assertSame(self::CPF_A, $fica->cpf);
        $this->assertSame('(11) 98888-1234', $fica->celular);
        $this->assertSame('maria@exemplo.com', $fica->email1);
        $this->assertSame('999001', $fica->tiny_id);
        $this->assertNull(Paciente::find($sai->id));
    }

    public function test_nunca_sobrescreve_dado_do_cadastro_que_fica(): void
    {
        $fica = $this->paciente([
            'data_nascimento' => '1980-05-10',
            'celular' => '(11) 99999-1111',
            'email1' => 'bom@exemplo.com',
        ]);
        $sai = $this->paciente([
            'data_nascimento' => '1991-01-01',
            'celular' => '(11) 9999-1111',
            'email1' => 'outro@exemplo.com',
        ]);

        $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $fica->refresh();
        $this->assertSame('1980-05-10', $fica->data_nascimento->format('Y-m-d'));
        $this->assertSame('(11) 99999-1111', $fica->celular);
        $this->assertSame('bom@exemplo.com', $fica->email1);
    }

    public function test_recusa_data_de_nascimento_no_futuro(): void
    {
        $fica = $this->paciente();
        $sai = $this->paciente(['data_nascimento' => '2071-04-13']);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $this->assertTrue($r['ok']);
        $this->assertNull($fica->refresh()->data_nascimento);
        $this->assertNotEmpty($r['avisos']);
    }

    public function test_aborta_quando_os_cpfs_sao_diferentes(): void
    {
        $fica = $this->paciente(['cpf' => self::CPF_A]);
        $sai = $this->paciente(['cpf' => self::CPF_B]);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('CPF diferente', (string) $r['erro']);
        $this->assertNotNull(Paciente::find($sai->id));
    }

    public function test_email_de_marcacao_nao_vira_email_de_verdade(): void
    {
        $fica = $this->paciente();
        $sai = $this->paciente(['email1' => '11988881234@cadastraremail.rsk']);

        $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $this->assertNull($fica->refresh()->email1);
    }

    public function test_email_de_verdade_substitui_a_marcacao(): void
    {
        $fica = $this->paciente(['email1' => '11988881234@cadastraremail.rsk']);
        $sai = $this->paciente(['email1' => 'real@exemplo.com']);

        $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $this->assertSame('real@exemplo.com', $fica->refresh()->email1);
    }

    public function test_historico_exige_autorizacao_explicita(): void
    {
        $medico = Medico::create(['apelido' => 'Dra Teste']);
        $fica = $this->paciente();
        $sai = $this->paciente();
        $sai->medicos()->attach($medico->id);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('histórico', (string) $r['erro']);
        $this->assertNotNull(Paciente::find($sai->id));
    }

    public function test_move_receitas_e_vinculos_preservando_o_numero(): void
    {
        $medico = Medico::create(['apelido' => 'Dra Teste']);
        $fica = $this->paciente();
        $sai = $this->paciente();
        $sai->medicos()->attach($medico->id);

        $receita = Receita::create([
            'numero' => $sai->id.'-0001',
            'paciente_id' => $sai->id,
            'medico_id' => $medico->id,
            'data_receita' => '2026-01-10',
            'status' => 'finalizada',
        ]);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: true, permitirHistorico: true);

        $this->assertTrue($r['ok'], $r['erro'] ?? '');
        $receita->refresh();
        $this->assertSame($fica->id, $receita->paciente_id);
        $this->assertSame($sai->id.'-0001', $receita->numero, 'o número impresso/enviado ao oList não muda');
        $this->assertSame(1, DB::table('medico_paciente')->where('paciente_id', $fica->id)->count());
        $this->assertNull(Paciente::find($sai->id));
    }

    public function test_nao_duplica_o_pivot_quando_os_dois_veem_o_mesmo_medico(): void
    {
        $medico = Medico::create(['apelido' => 'Dra Teste']);
        $fica = $this->paciente();
        $sai = $this->paciente();
        $fica->medicos()->attach($medico->id);
        $sai->medicos()->attach($medico->id);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: true, permitirHistorico: true);

        $this->assertTrue($r['ok'], $r['erro'] ?? '');
        $this->assertSame(1, DB::table('medico_paciente')->where('paciente_id', $fica->id)->count());
        $this->assertSame(1, $r['vinculos_ja_existiam']);
    }

    public function test_simulacao_nao_altera_nada(): void
    {
        $fica = $this->paciente();
        $sai = $this->paciente(['cpf' => self::CPF_A, 'tiny_id' => '999002']);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: false);

        $this->assertTrue($r['ok']);
        $this->assertArrayHasKey('cpf', $r['campos']);
        $this->assertNull($fica->refresh()->cpf);
        $this->assertNotNull(Paciente::find($sai->id));
    }

    public function test_avisa_quando_os_dois_tem_contato_no_olist(): void
    {
        $fica = $this->paciente(['tiny_id' => '111']);
        $sai = $this->paciente(['tiny_id' => '222']);

        $r = $this->fusao()->fundir($fica->id, $sai->id, aplicar: true);

        $this->assertTrue($r['ok']);
        $this->assertSame('111', $fica->refresh()->tiny_id);
        $this->assertStringContainsString('dois contatos', implode(' ', $r['avisos']));
    }

    public function test_comando_simula_por_padrao(): void
    {
        $fica = $this->paciente();
        $sai = $this->paciente(['cpf' => self::CPF_A]);

        $this->artisan('pacientes:fundir', ['--pares' => "{$fica->id}:{$sai->id}"])
            ->assertExitCode(0);

        $this->assertNotNull(Paciente::find($sai->id));
    }

    public function test_comando_aplica_com_force(): void
    {
        $fica = $this->paciente();
        $sai = $this->paciente(['cpf' => self::CPF_A]);

        $this->artisan('pacientes:fundir', ['--pares' => "{$fica->id}:{$sai->id}", '--force' => true])
            ->assertExitCode(0);

        $this->assertNull(Paciente::find($sai->id));
        $this->assertSame(self::CPF_A, $fica->refresh()->cpf);
    }
}
