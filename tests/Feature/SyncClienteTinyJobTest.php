<?php

namespace Tests\Feature;

use App\Jobs\SyncClienteTinyJob;
use App\Models\Paciente;
use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncClienteTinyJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tiny.v2_rate_limit_retry_seconds' => 0]);

        Setting::set('tiny_enabled', true);
        Setting::set('tiny_api_version', 'v2');
        Setting::set('tiny_token', 'test-token-v2');

        (new TinyApiRateLimiter)->resetForTesting();
    }

    #[Test]
    public function paciente_sem_tiny_id_com_cpf_ja_existente_no_tiny_e_vinculado_sem_incluir(): void
    {
        $paciente = Paciente::withoutEvents(fn () => Paciente::create([
            'nome' => 'Paciente Teste',
            'cpf' => '045.969.099-03',
        ]));

        Http::fake([
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'pagina' => 1,
                    'numero_paginas' => 1,
                    'contatos' => [['contato' => [
                        'id' => 763155432,
                        'nome' => 'Cliente Teste Existente',
                        'cpf_cnpj' => '045.969.099-03',
                    ]]],
                ],
            ]),
            'api.tiny.com.br/api2/contato.obter.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'contato' => [
                        'id' => 763155432,
                        'nome' => 'Cliente Teste Existente',
                        'data_atualizacao' => now()->addMinute()->format('d/m/Y H:i:s'),
                    ],
                ],
            ]),
        ]);

        (new SyncClienteTinyJob($paciente))->handle();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'contato.incluir.php'));

        $paciente->refresh();
        $this->assertSame('763155432', $paciente->tiny_id);
        $this->assertNotNull($paciente->tiny_sync_at);
    }

    #[Test]
    public function paciente_sem_tiny_id_e_cpf_inexistente_no_tiny_cria_contato(): void
    {
        $paciente = Paciente::withoutEvents(fn () => Paciente::create([
            'nome' => 'Paciente Novo',
            'cpf' => '045.969.099-03',
        ]));

        Http::fake([
            // Pesquisa por CPF sem resultados: Tiny sinaliza como "erro" código 20
            'api.tiny.com.br/api2/contatos.pesquisa.php' => Http::response([
                'retorno' => [
                    'status' => 'Erro',
                    'codigo_erro' => 20,
                    'erros' => [['erro' => 'A consulta não retornou registros']],
                ],
            ]),
            'api.tiny.com.br/api2/contato.incluir.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'registros' => [['registro' => ['sequencia' => 1, 'status' => 'OK', 'id' => 900123]]],
                ],
            ]),
            'api.tiny.com.br/api2/contato.obter.php' => Http::response([
                'retorno' => [
                    'status' => 'OK',
                    'contato' => [
                        'id' => 900123,
                        'nome' => 'Paciente Novo',
                        'data_atualizacao' => now()->format('d/m/Y H:i:s'),
                    ],
                ],
            ]),
        ]);

        (new SyncClienteTinyJob($paciente))->handle();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'contato.incluir.php')) {
                return false;
            }
            $contato = json_decode((string) ($request->data()['contato'] ?? ''), true);
            $tipos = $contato['contatos'][0]['contato']['tipos_contato'] ?? null;

            return $tipos === [['tipo' => 'Cliente']];
        });

        $paciente->refresh();
        $this->assertSame('900123', $paciente->tiny_id);
    }
}
