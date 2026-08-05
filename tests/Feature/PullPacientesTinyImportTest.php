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
 * Import de clientes do oList que ainda NÃO existem aqui (pedido do cliente, 08/2026):
 * antes o job só atualizava quem já estava cadastrado e descartava contato sem CPF.
 */
class PullPacientesTinyImportTest extends TestCase
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

    public function test_importa_cliente_sem_cpf(): void
    {
        $this->fakeApi(
            [$this->listItem(501, 'Cliente Sem CPF', '')],
            [$this->contato(501, 'Cliente Sem CPF', '', [
                'celular' => '(11) 96666-5555',
                'data_nascimento' => '15/09/1988',
                'sexo' => 'feminino',
                'email' => 'semcpf@example.com',
            ])]
        );

        (new PullPacientesTinyJob)->handle();

        $paciente = Paciente::where('tiny_id', '501')->firstOrFail();
        $this->assertNull($paciente->cpf);
        $this->assertSame('Cliente Sem CPF', $paciente->nome);
        $this->assertSame('(11) 96666-5555', $paciente->celular);
        $this->assertSame('1988-09-15', $paciente->data_nascimento->format('Y-m-d'));
        // 'F' é o que o formulário grava — "Feminino" deixaria o campo em branco na tela.
        $this->assertSame('F', $paciente->sexo);
        $this->assertTrue($paciente->ativo);
        // Entra sem vínculo: só passa a ser "de um médico" quando alguém o usar.
        $this->assertSame(0, $paciente->medicos()->count());
    }

    public function test_import_e_ligado_por_padrao(): void
    {
        Setting::set('tiny_pull_import_new', null);

        $this->fakeApi(
            [$this->listItem(502, 'Novo Padrao', '')],
            [$this->contato(502, 'Novo Padrao', '', ['email' => 'padrao@example.com'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertDatabaseHas('pacientes', ['tiny_id' => '502']);
    }

    public function test_setting_desligado_continua_impedindo_import(): void
    {
        Setting::set('tiny_pull_import_new', false);

        $this->fakeApi([$this->listItem(503, 'Nao Importar', '')], []);

        (new PullPacientesTinyJob)->handle();

        $this->assertDatabaseMissing('pacientes', ['tiny_id' => '503']);
        Http::assertSentCount(1); // não gastou chamada de detalhe
    }

    public function test_nao_duplica_paciente_existente_com_mesmo_email_e_nascimento(): void
    {
        $existente = Paciente::create([
            'nome' => 'Maria Legado',
            'email1' => 'maria@example.com',
            'data_nascimento' => '1975-03-08',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(504, 'Maria Legado', '')],
            [$this->contato(504, 'Maria Legado', '', [
                'email' => 'maria@example.com',
                'data_nascimento' => '08/03/1975',
                'celular' => '(41) 95555-4444',
            ])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(1, Paciente::count());
        $existente->refresh();
        $this->assertSame('504', $existente->tiny_id);
        $this->assertSame('(41) 95555-4444', $existente->celular);
    }

    public function test_concilia_por_email_unico_com_nome_compativel(): void
    {
        $existente = Paciente::create([
            'nome' => 'Ana Souza',           // sem data de nascimento, como 90% do legado
            'email1' => 'ana@example.com',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(505, 'Ana Maria de Souza', '')],
            [$this->contato(505, 'Ana Maria de Souza', '', ['email' => 'ana@example.com'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(1, Paciente::count());
        $this->assertSame('505', $existente->fresh()->tiny_id);
        // Nome local preservado: é o que o médico reconhece na busca.
        $this->assertSame('Ana Souza', $existente->fresh()->nome);
    }

    public function test_concilia_por_celular_com_nome_compativel_sem_email(): void
    {
        // Caso mais comum da base daqui: sem CPF, sem e-mail, mas com celular.
        $existente = Paciente::create([
            'nome' => 'Adriana Brito',
            'celular' => '(11) 91234-5678',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(510, 'Adriana Brito', '')],
            [$this->contato(510, 'Adriana Brito', '', ['celular' => '11912345678'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(1, Paciente::count());
        $this->assertSame('510', $existente->fresh()->tiny_id);
    }

    public function test_concilia_por_data_de_nascimento_com_nome_compativel(): void
    {
        $existente = Paciente::create([
            'nome' => 'Carlos Eduardo Lima',
            'data_nascimento' => '1969-11-30',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(511, 'Carlos Lima', '')],
            [$this->contato(511, 'Carlos Lima', '', ['data_nascimento' => '30/11/1969'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(1, Paciente::count());
        $this->assertSame('511', $existente->fresh()->tiny_id);
    }

    public function test_mesmo_celular_com_nome_diferente_cria_paciente_novo(): void
    {
        // Celular de casa/família: nomes diferentes = pessoas diferentes.
        Paciente::create([
            'nome' => 'Marta Pereira',
            'celular' => '(11) 93333-2222',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(512, 'Julia Pereira', '')],
            [$this->contato(512, 'Julia Pereira', '', ['celular' => '11933332222'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(2, Paciente::count());
        $this->assertDatabaseHas('pacientes', ['tiny_id' => '512', 'nome' => 'Julia Pereira']);
    }

    public function test_email_de_familia_com_nome_diferente_cria_paciente_novo(): void
    {
        Paciente::create([
            'nome' => 'Marta Pereira',
            'email1' => 'familia@example.com',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(506, 'Julia Pereira', '')],
            [$this->contato(506, 'Julia Pereira', '', ['email' => 'familia@example.com'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(2, Paciente::count());
        $this->assertDatabaseHas('pacientes', ['tiny_id' => '506', 'nome' => 'Julia Pereira']);
    }

    public function test_ignora_pessoa_juridica(): void
    {
        $this->fakeApi(
            [$this->listItem(507, 'Clinica LTDA', '12.345.678/0001-99', 'J')],
            [$this->contato(507, 'Clinica LTDA', '12.345.678/0001-99', ['tipo_pessoa' => 'J'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertDatabaseMissing('pacientes', ['tiny_id' => '507']);
    }

    public function test_backfill_completo_nao_move_marca_dagua_e_usa_checkpoint_proprio(): void
    {
        $since = Setting::get('tiny_contatos_pull_since');

        $this->fakeApi(
            [$this->listItem(508, 'Backfill Um', '')],
            [$this->contato(508, 'Backfill Um', '', ['email' => 'bf1@example.com'])]
        );

        $job = new PullPacientesTinyJob(backfillCompleto: true);
        $job->handle();

        $this->assertDatabaseHas('pacientes', ['tiny_id' => '508']);
        $this->assertSame($since, Setting::get('tiny_contatos_pull_since'));
        $this->assertSame(1, $job->stats['importados']);
        $this->assertTrue($job->stats['concluido']);

        // Backfill varre tudo: a pesquisa não pode levar dataMinimaAtualizacao (é POST de form).
        $pesquisas = collect(Http::recorded())
            ->filter(fn ($par) => str_contains((string) $par[0]->url(), 'contatos.pesquisa.php'));

        $this->assertNotEmpty($pesquisas);
        foreach ($pesquisas as $par) {
            $this->assertArrayNotHasKey('dataMinimaAtualizacao', $par[0]->data());
        }
    }

    public function test_dry_run_nao_grava(): void
    {
        $this->fakeApi(
            [$this->listItem(509, 'Simulado', '')],
            [$this->contato(509, 'Simulado', '', ['email' => 'dry@example.com'])]
        );

        $job = new PullPacientesTinyJob(dryRun: true);
        $job->handle();

        $this->assertSame(0, Paciente::count());
        $this->assertSame(1, $job->stats['importados']);
    }

    /**
     * A conciliação por celular/nascimento só é alcançada quando os CPFs NÃO batem — gravar o
     * CPF do oList colaria o documento de outra pessoa no cadastro local.
     */
    public function test_conciliacao_nao_sobrescreve_cpf_local(): void
    {
        $existente = Paciente::create([
            'nome' => 'Maria Souza',
            'cpf' => '111.444.777-35',
            'celular' => '(11) 98888-7777',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(513, 'Maria Souza', '529.982.247-25')],
            [$this->contato(513, 'Maria Souza', '529.982.247-25', ['celular' => '11988887777'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame(1, Paciente::count());
        $this->assertSame('111.444.777-35', $existente->fresh()->cpf);
        $this->assertSame('513', $existente->fresh()->tiny_id);
    }

    public function test_conciliacao_preenche_cpf_quando_local_esta_vazio(): void
    {
        $existente = Paciente::create([
            'nome' => 'Pedro Antunes',
            'celular' => '(11) 97777-1111',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [$this->listItem(514, 'Pedro Antunes', '529.982.247-25')],
            [$this->contato(514, 'Pedro Antunes', '529.982.247-25', ['celular' => '11977771111'])]
        );

        (new PullPacientesTinyJob)->handle();

        $this->assertSame('529.982.247-25', $existente->fresh()->cpf);
    }

    /**
     * Job desta classe enfileirado ANTES do deploy não tem as novas chaves no payload; com
     * propriedade tipada promovida, `handle()` morreria em "must not be accessed before
     * initialization" e o retry automático entraria em loop.
     */
    public function test_job_serializado_sem_as_novas_propriedades_ainda_roda(): void
    {
        $antigo = unserialize(
            'O:'.strlen(PullPacientesTinyJob::class).':"'.PullPacientesTinyJob::class.'":0:{}'
        );

        $this->assertFalse($antigo->backfillCompleto);
        $this->assertNull($antigo->apiBudget);
        $this->assertNull($antigo->maxPaginas);
        $this->assertFalse($antigo->dryRun);

        $this->fakeApi([], []);
        $antigo->handle();

        $this->assertTrue($antigo->stats['concluido']);
    }

    /**
     * Os contadores por regra são o que mostra, na saída do comando, quanto cada critério está
     * segurando de cadastro repetido.
     */
    public function test_conta_conciliacoes_por_regra(): void
    {
        Paciente::create([
            'nome' => 'Por Cpf',
            'cpf' => '111.444.777-35',
            'pais' => 'Brasil',
        ]);
        Paciente::create([
            'nome' => 'Por Celular',
            'celular' => '(11) 95555-4444',
            'pais' => 'Brasil',
        ]);
        Paciente::create([
            'nome' => 'Por Nascimento',
            'data_nascimento' => '1990-06-15',
            'pais' => 'Brasil',
        ]);

        $this->fakeApi(
            [
                $this->listItem(601, 'Por Cpf', '111.444.777-35'),
                $this->listItem(602, 'Por Celular', ''),
                $this->listItem(603, 'Por Nascimento', ''),
            ],
            [
                $this->contato(601, 'Por Cpf', '111.444.777-35'),
                $this->contato(602, 'Por Celular', '', ['celular' => '11955554444']),
                $this->contato(603, 'Por Nascimento', '', ['data_nascimento' => '15/06/1990']),
            ]
        );

        $job = new PullPacientesTinyJob;
        $job->handle();

        $this->assertSame(3, Paciente::count());
        $this->assertSame(3, $job->stats['conciliados']);
        $this->assertSame(
            ['cpf' => 1, 'celular+nome' => 1, 'nascimento+nome' => 1],
            $job->stats['conciliados_por']
        );
    }

    public function test_nomes_compativeis(): void
    {
        $this->assertTrue(PullPacientesTinyJob::nomesCompativeis('João Silva', 'João Pedro da Silva'));
        $this->assertTrue(PullPacientesTinyJob::nomesCompativeis('ANA SOUZA', 'ana souza'));
        $this->assertTrue(PullPacientesTinyJob::nomesCompativeis('José da Silva', 'Jose Silva'));
        $this->assertFalse(PullPacientesTinyJob::nomesCompativeis('Marta Pereira', 'Julia Pereira'));
        $this->assertFalse(PullPacientesTinyJob::nomesCompativeis('João Silva', 'João Souza'));
        $this->assertFalse(PullPacientesTinyJob::nomesCompativeis('', 'João Silva'));
    }

    /**
     * @param  list<array<string,mixed>>  $itens
     * @param  list<array<string,mixed>>  $detalhes
     */
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

    /**
     * @return array<string,mixed>
     */
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

    /**
     * @param  array<string,mixed>  $override
     * @return array<string,mixed>
     */
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
