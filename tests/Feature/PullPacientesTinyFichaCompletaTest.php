<?php

namespace Tests\Feature;

use App\Jobs\PullPacientesTinyJob;
use App\Models\Paciente;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Releitura da ficha completa do contato no oList.
 *
 * A lista de contatos do oList devolve nome, e-mail, fone e endereço — não devolve data de
 * nascimento, celular nem sexo. Enquanto o pull se contentava com a lista, correção feita no oList
 * nesses campos nunca chegava aqui.
 */
class PullPacientesTinyFichaCompletaTest extends TestCase
{
    use RefreshDatabase;

    private function precisa(Paciente $p): bool
    {
        $job = new PullPacientesTinyJob;
        $m = new \ReflectionMethod($job, 'precisaLerFichaCompleta');
        $m->setAccessible(true);

        return $m->invoke($job, $p);
    }

    public function test_contato_nunca_lido_por_inteiro_precisa_de_leitura(): void
    {
        $p = Paciente::create(['nome' => 'Maria', 'tiny_id' => '123']);

        $this->assertTrue($this->precisa($p));
    }

    public function test_lido_ha_pouco_dispensa_a_chamada_extra(): void
    {
        $p = Paciente::create([
            'nome' => 'Maria',
            'tiny_id' => '123',
            'tiny_detalhe_sync_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->assertFalse($this->precisa($p), 'contato lido há 30 min não custa outra chamada');
    }

    public function test_leitura_vencida_volta_a_valer_a_chamada(): void
    {
        $p = Paciente::create([
            'nome' => 'Maria',
            'tiny_id' => '123',
            'tiny_detalhe_sync_at' => Carbon::now()->subHours(9),
        ]);

        $this->assertTrue($this->precisa($p));
    }

    public function test_validade_e_configuravel(): void
    {
        $p = Paciente::create([
            'nome' => 'Maria',
            'tiny_id' => '123',
            'tiny_detalhe_sync_at' => Carbon::now()->subHours(2),
        ]);

        $this->assertFalse($this->precisa($p));

        Setting::set('tiny_contato_detalhe_ttl_horas', 1);
        $this->assertTrue($this->precisa($p));

        // Zero desliga a economia: relê sempre.
        Setting::set('tiny_contato_detalhe_ttl_horas', 0);
        $p->tiny_detalhe_sync_at = Carbon::now();
        $this->assertTrue($this->precisa($p));
    }
}
