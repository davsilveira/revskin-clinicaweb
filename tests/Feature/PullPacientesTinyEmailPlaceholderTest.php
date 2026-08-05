<?php

namespace Tests\Feature;

use App\Jobs\PullPacientesTinyJob;
use App\Models\Paciente;
use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * E-mail de marcação na importação do oList (decisão do cliente, 08/2026): o placeholder
 * existe SÓ aqui, para as atendentes verem de cara quem ainda precisa de e-mail de verdade.
 * Cadastro novo feito no sistema não gera nada (ver PacienteEmailOpcionalTest).
 */
class PullPacientesTinyEmailPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tiny.v2_rate_limit_retry_seconds' => 0]);

        Setting::set('tiny_enabled', true);
        Setting::set('tiny_api_version', 'v2');
        Setting::set('tiny_token', 'test-token-v2');
        Setting::set('tiny_pull_somente_tipo_cliente', true);
        Setting::set('tiny_contatos_pull_since', now()->subDay()->toIso8601String());
        Setting::set('tiny_contatos_pull_api_budget', 80);
        Setting::set('tiny_contatos_pull_checkpoint', null);
        Setting::set('tiny_contatos_backfill_checkpoint', null);

        (new TinyApiRateLimiter)->resetForTesting();

        Bus::fake();
    }

    public function test_contato_sem_email_entra_com_placeholder_do_celular(): void
    {
        $this->fakeApi(
            [$this->listItem(701, 'Bruna Sem Email', '')],
            [$this->contato(701, 'Bruna Sem Email', '', ['celular' => '(21) 99999-1111'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(
            '21999991111@cadastraremail.rsk',
            Paciente::where('tiny_id', '701')->firstOrFail()->email1
        );
    }

    public function test_placeholder_do_olist_chega_no_dominio_valido(): void
    {
        $this->fakeApi(
            [$this->listItem(702, 'Andrea Underline', '')],
            [$this->contato(702, 'Andrea Underline', '', [
                'celular' => '(21) 99592-2692',
                'email' => '21995922692@cadastrar_email.com',
            ])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(
            '21995922692@cadastraremail.rsk',
            Paciente::where('tiny_id', '702')->firstOrFail()->email1
        );
    }

    public function test_contato_sem_celular_entra_sem_email(): void
    {
        $this->fakeApi(
            [$this->listItem(703, 'Sem Telefone Nenhum', '')],
            [$this->contato(703, 'Sem Telefone Nenhum', '')]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertNull(Paciente::where('tiny_id', '703')->firstOrFail()->email1);
    }

    public function test_email_de_verdade_daqui_nao_e_trocado_por_placeholder_do_olist(): void
    {
        $paciente = Paciente::create([
            'nome' => 'Carla Real',
            'celular' => '(21) 98888-2222',
            'email1' => 'carla@gmail.com',
            'tiny_id' => '704',
        ]);

        $this->fakeApi(
            [$this->listItem(704, 'Carla Real', '')],
            [$this->contato(704, 'Carla Real', '', [
                'celular' => '(21) 98888-2222',
                'email' => '21988882222@cadastrar_email.com',
            ])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame('carla@gmail.com', $paciente->fresh()->email1);
    }

    /**
     * O placeholder é derivado do telefone e mais da metade dos contatos do oList tem um.
     * Se ele valesse como identidade, duas pessoas que dividem o celular (mãe e filha)
     * virariam um cadastro só — sem passar pela conferência de nome que a regra de celular faz.
     */
    public function test_placeholder_nao_concilia_dois_pacientes_diferentes(): void
    {
        $mae = Paciente::create([
            'nome' => 'Helena Prado',
            'celular' => '(21) 97777-3333',
            'email1' => '21977773333@cadastraremail.rsk',
            'data_nascimento' => '1965-04-02',
        ]);

        $this->fakeApi(
            [$this->listItem(705, 'Beatriz Prado', '')],
            [$this->contato(705, 'Beatriz Prado', '', [
                'celular' => '(21) 97777-3333',
                'email' => '21977773333@cadastraremail.rsk',
                'data_nascimento' => '02/04/1965',
            ])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertNull($mae->fresh()->tiny_id);
        $this->assertDatabaseHas('pacientes', ['tiny_id' => '705', 'nome' => 'Beatriz Prado']);
    }

    private function fakeApi(array $itens, array $detalhes): void
    {
        $fakes = [
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'pagina' => 1,
                    'numero_paginas' => 1,
                    'contatos' => array_map(fn ($c) => ['contato' => $c], $itens),
                ],
            ]),
        ];

        if ($detalhes !== []) {
            $sequence = Http::sequence();
            foreach ($detalhes as $d) {
                $sequence->push(['retorno' => ['status' => 'OK', 'contato' => $d]]);
            }
            $fakes['api.tiny.com.br/api2/contato.obter.php'] = $sequence;
        }

        Http::fake($fakes);
    }

    private function listItem(int $id, string $nome, string $cpf, string $tipo = 'F'): array
    {
        return [
            'id' => $id,
            'nome' => $nome,
            'tipo_pessoa' => $tipo,
            'cpf_cnpj' => $cpf,
            'situacao' => 'Ativo',
        ];
    }

    private function contato(int $id, string $nome, string $cpf, array $override = []): array
    {
        return array_merge([
            'id' => $id,
            'nome' => $nome,
            'tipo_pessoa' => 'F',
            'cpf_cnpj' => $cpf,
            'tipos_contato' => [['tipo' => 'Cliente']],
            'data_atualizacao' => now()->format('d/m/Y H:i:s'),
        ], $override);
    }
}
