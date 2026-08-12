<?php

namespace Tests\Feature;

use App\Jobs\PullPacientesTinyJob;
use App\Models\Paciente;
use App\Services\TinyContatoMapper;
use App\Support\TelefonePaciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Conciliação do pull do oList — o que evita cadastro repetido.
 *
 * As duas causas confirmadas na apuração do job 16e92695: nono dígito do celular e data de
 * nascimento impossível digitada no oList.
 */
class PullPacientesTinyConciliacaoTest extends TestCase
{
    use RefreshDatabase;

    private function conciliar(array $contato): ?Paciente
    {
        $job = new PullPacientesTinyJob;
        $metodo = new \ReflectionMethod($job, 'localizarPacienteEquivalente');
        $metodo->setAccessible(true);

        $digits = preg_replace('/\D/', '', (string) ($contato['cpf'] ?? ''));

        return $metodo->invoke($job, $contato, $digits);
    }

    public function test_chave_de_telefone_absorve_o_nono_digito(): void
    {
        $this->assertTrue(TelefonePaciente::iguais('(48) 99907-2096', '(48) 9907-2096'));
        $this->assertTrue(TelefonePaciente::iguais('5548999072096', '48 9907-2096'));
    }

    public function test_chave_de_telefone_separa_ddds_diferentes(): void
    {
        $this->assertFalse(TelefonePaciente::iguais('(66) 99907-5482', '(65) 99907-5482'));
    }

    public function test_ddd_55_do_rio_grande_do_sul_nao_e_confundido_com_codigo_do_pais(): void
    {
        $this->assertSame('5599072096', TelefonePaciente::chave('(55) 9907-2096'));
    }

    public function test_celular_sem_o_nono_digito_reencontra_o_cadastro(): void
    {
        $local = Paciente::create([
            'nome' => 'Carolina Canto de Macedo Villar',
            'celular' => '(48) 99907-2096',
            'data_nascimento' => '1987-09-24',
        ]);

        $achado = $this->conciliar([
            'nome' => 'Carolina Canto de Macedo Villar',
            'celular' => '(48) 9907-2096',
        ]);

        $this->assertNotNull($achado, 'o oList sem o nono dígito tem de casar com o cadastro daqui');
        $this->assertSame($local->id, $achado->id);
    }

    public function test_mesmo_numero_em_ddd_diferente_nao_funde_homonimas(): void
    {
        Paciente::create([
            'nome' => 'Hellen Uliam Uriki',
            'celular' => '(66) 99907-5482',
            'data_nascimento' => '1980-12-17',
        ]);

        $achado = $this->conciliar([
            'nome' => 'Hellen Uliam Uriki',
            'celular' => '(65) 99907-5482',
            'data_nascimento' => '17/02/1980',
        ]);

        $this->assertNull($achado, 'DDD diferente são duas pessoas — a clínica confirmou');
    }

    public function test_data_de_nascimento_no_futuro_e_descartada(): void
    {
        $this->assertNull(TinyContatoMapper::parseDataNascimento('13/04/2071'));
        $this->assertNull(TinyContatoMapper::parseDataNascimento('28/06/2091'));
        $this->assertNull(TinyContatoMapper::parseDataNascimento('28/06/9198'));
    }

    public function test_ano_de_dois_digitos_cai_no_seculo_certo(): void
    {
        $this->assertSame('1971-04-13', TinyContatoMapper::parseDataNascimento('13/04/71'));
        $this->assertSame('1991-06-28', TinyContatoMapper::parseDataNascimento('28/06/91'));
        $this->assertSame('2020-03-02', TinyContatoMapper::parseDataNascimento('02/03/20'));
    }

    public function test_data_valida_continua_passando(): void
    {
        $this->assertSame('1971-04-13', TinyContatoMapper::parseDataNascimento('13/04/1971'));
        $this->assertNull(TinyContatoMapper::parseDataNascimento(null));
        $this->assertNull(TinyContatoMapper::parseDataNascimento('1971-04-13'));
    }

    public function test_data_impossivel_nao_impede_o_pareamento_por_nome_e_celular(): void
    {
        $local = Paciente::create([
            'nome' => 'Andrea Cristina da Silva',
            'celular' => '(21) 99011-5081',
            'data_nascimento' => '1974-05-20',
        ]);

        $achado = $this->conciliar([
            'nome' => 'Andrea Cristina da Silva',
            'celular' => '(21) 99011-5081',
            'data_nascimento' => '20/05/2074',
        ]);

        $this->assertNotNull($achado);
        $this->assertSame($local->id, $achado->id);
    }
}
