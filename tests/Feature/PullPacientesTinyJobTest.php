<?php

namespace Tests\Feature;

use App\Jobs\PullPacientesTinyJob;
use App\Models\Paciente;
use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use App\Services\TinyErpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PullPacientesTinyJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tiny.v2_rate_limit_retry_seconds' => 0]);

        Setting::set('tiny_enabled', true);
        Setting::set('tiny_api_version', 'v2');
        Setting::set('tiny_token', 'test-token-v2');
        Setting::set('tiny_pull_import_new', false);
        Setting::set('tiny_pull_somente_tipo_cliente', true);
        Setting::set('tiny_contatos_pull_since', now()->subDay()->toIso8601String());
        Setting::set('tiny_contatos_pull_api_budget', 80);
        Setting::set('tiny_contatos_pull_checkpoint', null);

        (new TinyApiRateLimiter)->resetForTesting();

        Bus::fake();
    }

    #[Test]
    public function existing_patient_is_updated_without_obter_contato(): void
    {
        Paciente::create([
            'nome' => 'Paciente Existente',
            'tiny_id' => '101',
            'cpf' => '123.456.789-01',
        ]);

        Http::fake([
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::response($this->pesquisaResponse([
                $this->contatoListItem(101, 'Paciente Atualizado Tiny', '123.456.789-01'),
            ])),
        ]);

        (new PullPacientesTinyJob)->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'contatos.pesquisa.php'));

        $paciente = Paciente::where('tiny_id', '101')->first();
        $this->assertSame('Paciente Atualizado Tiny', $paciente->nome);
        $this->assertNotNull($paciente->tiny_sync_at);
    }

    #[Test]
    public function new_contact_with_somente_cliente_calls_obter_contato(): void
    {
        Setting::set('tiny_pull_import_new', true);

        Http::fake([
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::response($this->pesquisaResponse([
                $this->contatoListItem(202, 'Novo Cliente', '987.654.321-00'),
            ])),
            'api.tiny.com.br/api2/contato.obter.php' => Http::response($this->obterResponse([
                'id' => 202,
                'nome' => 'Novo Cliente',
                'tipo_pessoa' => 'F',
                'cpf_cnpj' => '987.654.321-00',
                'tipos_contato' => [['tipo' => 'Cliente']],
                'data_atualizacao' => now()->format('d/m/Y H:i:s'),
            ])),
        ]);

        (new PullPacientesTinyJob)->handle();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'contato.obter.php'));

        $this->assertDatabaseHas('pacientes', [
            'tiny_id' => '202',
            'nome' => 'Novo Cliente',
        ]);
    }

    #[Test]
    public function api_budget_exhausted_saves_checkpoint_and_does_not_throw(): void
    {
        Setting::set('tiny_contatos_pull_api_budget', 1);

        Http::fake([
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::sequence()
                ->push($this->pesquisaResponse([
                    $this->contatoListItem(301, 'Um', '111.222.333-44'),
                ], 1, 2))
                ->push($this->pesquisaResponse([], 2, 2)),
        ]);

        $sinceBefore = Setting::get('tiny_contatos_pull_since');

        (new PullPacientesTinyJob)->handle();

        $checkpoint = json_decode((string) Setting::get('tiny_contatos_pull_checkpoint'), true);
        $this->assertIsArray($checkpoint);
        $this->assertSame(2, $checkpoint['pagina']);
        $this->assertSame($sinceBefore, Setting::get('tiny_contatos_pull_since'));
    }

    #[Test]
    public function rate_limit_pauses_run_without_exception(): void
    {
        Http::fake([
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::sequence()
                ->push([
                    'retorno' => [
                        'status' => 'Erro',
                        'codigo_erro' => 6,
                        'erros' => [['erro' => 'API Bloqueada - Excedido o número de acessos a API, aguarde alguns minutos e tente novamente']],
                    ],
                ], 200)
                ->push([
                    'retorno' => [
                        'status' => 'Erro',
                        'codigo_erro' => 6,
                        'erros' => [['erro' => 'API Bloqueada - Excedido o número de acessos a API, aguarde algenos minutos e tente novamente']],
                    ],
                ], 200),
        ]);

        (new PullPacientesTinyJob)->handle();

        $checkpoint = json_decode((string) Setting::get('tiny_contatos_pull_checkpoint'), true);
        $this->assertIsArray($checkpoint);
        $this->assertSame(1, $checkpoint['pagina']);
    }

    #[Test]
    public function pesquisa_sem_registros_finaliza_sem_erro_e_avanca_watermark(): void
    {
        Http::fake([
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::response([
                'retorno' => [
                    'status' => 'Erro',
                    'codigo_erro' => 20,
                    'erros' => [['erro' => 'A consulta não retornou registros']],
                ],
            ]),
        ]);

        $sinceBefore = Setting::get('tiny_contatos_pull_since');

        (new PullPacientesTinyJob)->handle();

        Http::assertSentCount(1);
        $this->assertNotSame($sinceBefore, Setting::get('tiny_contatos_pull_since'));
        $this->assertEmpty(Setting::get('tiny_contatos_pull_checkpoint'));
    }

    #[Test]
    public function is_no_records_error_detects_codigo_and_message(): void
    {
        $this->assertTrue(TinyErpClient::isNoRecordsError([
            'status' => 'error',
            'status_code' => 20,
            'message' => 'A consulta não retornou registros',
        ]));
        $this->assertTrue(TinyErpClient::isNoRecordsError([
            'status' => 'error',
            'message' => 'A consulta nao retornou registros',
        ]));
        $this->assertFalse(TinyErpClient::isNoRecordsError([
            'status' => 'error',
            'status_code' => 32,
            'message' => 'Registro não localizado',
        ]));
        $this->assertFalse(TinyErpClient::isNoRecordsError([
            'status' => 'success',
        ]));
    }

    #[Test]
    public function erro_por_registro_em_contato_incluir_gera_mensagem_real(): void
    {
        Http::fake([
            'api.tiny.com.br/api2/contato.incluir.php' => Http::response([
                'retorno' => [
                    'status' => 'Erro',
                    'codigo_erro' => 30,
                    'registros' => [
                        [
                            'registro' => [
                                'sequencia' => 1,
                                'status' => 'Erro',
                                'erros' => [['erro' => 'CPF/CNPJ inválido']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = (new TinyErpClient)->criarContato(['nome' => 'Teste', 'cpfCnpj' => '00000000000']);

        $this->assertSame('error', $result['status']);
        $this->assertSame('CPF/CNPJ inválido', $result['message']);
    }

    #[Test]
    public function is_rate_limit_error_detects_codigo_and_message(): void
    {
        $this->assertTrue(TinyErpClient::isRateLimitError([
            'status' => 'error',
            'status_code' => 6,
            'message' => 'API Bloqueada',
        ]));
        $this->assertTrue(TinyErpClient::isRateLimitError([
            'status' => 'error',
            'message' => 'API Bloqueada - Excedido o número de acessos a API',
        ]));
        $this->assertFalse(TinyErpClient::isRateLimitError([
            'status' => 'error',
            'status_code' => 32,
            'message' => 'Registro não localizado',
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $contatos
     */
    private function pesquisaResponse(array $contatos, int $pagina = 1, int $numeroPaginas = 1): array
    {
        return [
            'retorno' => [
                'status' => 'OK',
                'pagina' => $pagina,
                'numero_paginas' => $numeroPaginas,
                'contatos' => array_map(fn ($c) => ['contato' => $c], $contatos),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contatoListItem(int $id, string $nome, string $cpf): array
    {
        return array_merge([
            'id' => $id,
            'nome' => $nome,
            'tipo_pessoa' => 'F',
            'cpf_cnpj' => $cpf,
            'email' => 'test@example.com',
            'fone' => '11999998888',
        ], []);
    }

    /**
     * @param  array<string, mixed>  $contato
     */
    private function obterResponse(array $contato): array
    {
        return [
            'retorno' => [
                'status' => 'OK',
                'contato' => $contato,
            ],
        ];
    }
}
