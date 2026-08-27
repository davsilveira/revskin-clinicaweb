<?php

namespace Tests\Feature;

use App\Jobs\SyncClienteTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * País acima do CPF, documento livre para estrangeiro e CPF opcional (07/2026).
 * A identidade do paciente entre médicos passa a ser CPF ou e-mail + data de nascimento.
 */
class PacienteEstrangeiroTest extends TestCase
{
    use RefreshDatabase;

    private const CPF_VALIDO = '111.444.777-35';

    private function medicoComUser(string $email): array
    {
        $medico = Medico::create(['apelido' => 'Dr '.$email]);
        $user = User::create([
            'name' => 'Dr '.$email,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);

        return [$user, $medico];
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nome' => 'John Smith',
            'data_nascimento' => '1985-05-20',
            'celular' => '+1 305 555 0123',
            'email1' => 'john@example.com',
            'pais' => 'Estados Unidos',
            'outro_documento' => 'X1234567',
            'cidade' => 'Miami',
            'uf' => 'Florida',
        ], $override);
    }

    public function test_estrangeiro_salva_sem_cpf_com_documento_livre(): void
    {
        [$user] = $this->medicoComUser('est1@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $paciente = Paciente::where('email1', 'john@example.com')->firstOrFail();
        $this->assertNull($paciente->cpf);
        $this->assertSame('X1234567', $paciente->outro_documento);
        $this->assertSame('Estados Unidos', $paciente->pais);
        $this->assertFalse($paciente->isBrasil());
        $this->assertSame('X1234567', $paciente->documento);
        $this->assertSame('Documento', $paciente->documento_label);
    }

    /**
     * Feedback do cliente (08/2026): no Brasil o CPF voltou a ser obrigatório em cadastro novo.
     */
    public function test_brasileiro_nao_salva_sem_cpf(): void
    {
        [$user] = $this->medicoComUser('br1@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payload([
                'nome' => 'Maria Brasileira',
                'pais' => 'Brasil',
                'outro_documento' => null,
                'email1' => 'maria@example.com',
                'celular' => '(11) 99999-9999',
                'cidade' => 'Campinas',
                'uf' => 'SP',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cpf');

        $this->assertDatabaseMissing('pacientes', ['email1' => 'maria@example.com']);
    }

    public function test_brasileiro_salva_com_cpf(): void
    {
        [$user] = $this->medicoComUser('br2@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payload([
                'nome' => 'Maria Brasileira',
                'pais' => 'Brasil',
                'outro_documento' => null,
                'cpf' => self::CPF_VALIDO,
                'email1' => 'maria2@example.com',
                'celular' => '(11) 99999-9999',
                'cidade' => 'Campinas',
                'uf' => 'SP',
            ]))
            ->assertOk();

        $this->assertSame(self::CPF_VALIDO, Paciente::where('email1', 'maria2@example.com')->value('cpf'));
    }

    public function test_cpf_informado_invalido_continua_barrado(): void
    {
        [$user] = $this->medicoComUser('cpfinv@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payload([
                'pais' => 'Brasil',
                'cpf' => '111.111.111-11',
                'cidade' => 'Campinas',
                'uf' => 'SP',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cpf');
    }

    public function test_cpf_vazio_nao_colide_entre_dois_pacientes_sem_cpf(): void
    {
        [$user] = $this->medicoComUser('vazio@revskin.com.br');

        // CPF chega como string vazia do formulário — precisa ser gravado como NULL.
        $this->actingAs($user)->postJson(route('pacientes.store'), $this->payload([
            'cpf' => '',
            'email1' => 'um@example.com',
        ]))->assertOk();

        $this->actingAs($user)->postJson(route('pacientes.store'), $this->payload([
            'nome' => 'Outra Pessoa',
            'cpf' => '',
            'email1' => 'dois@example.com',
            'outro_documento' => 'Y7654321',
        ]))->assertOk();

        $this->assertSame(2, Paciente::count());
        $this->assertSame(2, Paciente::whereNull('cpf')->count());
    }

    public function test_dois_medicos_compartilham_estrangeiro_por_email_e_nascimento(): void
    {
        [$userA] = $this->medicoComUser('estA@revskin.com.br');
        [$userB, $medicoB] = $this->medicoComUser('estB@revskin.com.br');

        $this->actingAs($userA)->postJson(route('pacientes.store'), $this->payload([
            'codigo' => 'A-1',
        ]))->assertOk();

        $this->actingAs($userB)->postJson(route('pacientes.store'), $this->payload([
            'codigo' => 'B-1',
        ]))->assertOk();

        // Um único paciente, dois vínculos — sem CPF nenhum envolvido.
        $this->assertSame(1, Paciente::count());
        $paciente = Paciente::firstOrFail();
        $this->assertNotNull($paciente->vinculoDoMedico($medicoB->id));
        $this->assertSame('B-1', $paciente->vinculoDoMedico($medicoB->id)->codigo);
    }

    public function test_mesmo_email_com_nascimento_diferente_sao_pacientes_distintos(): void
    {
        [$user] = $this->medicoComUser('familia@revskin.com.br');

        // E-mail de família: mãe e filha compartilham o e-mail mas não a data de nascimento.
        $this->actingAs($user)->postJson(route('pacientes.store'), $this->payload([
            'nome' => 'Mãe', 'email1' => 'familia@example.com', 'data_nascimento' => '1970-03-02',
        ]))->assertOk();

        $this->actingAs($user)->postJson(route('pacientes.store'), $this->payload([
            'nome' => 'Filha', 'email1' => 'familia@example.com', 'data_nascimento' => '2001-08-14',
        ]))->assertOk();

        $this->assertSame(2, Paciente::count());
    }

    public function test_estado_provincia_estrangeiro_aceita_texto_longo(): void
    {
        [$user] = $this->medicoComUser('uf@revskin.com.br');

        $this->actingAs($user)->postJson(route('pacientes.store'), $this->payload([
            'uf' => 'California',
            'cidade' => 'Los Angeles',
        ]))->assertOk();

        $this->assertSame('California', Paciente::firstOrFail()->uf);
    }

    public function test_uf_brasileira_continua_limitada_a_sigla(): void
    {
        [$user] = $this->medicoComUser('ufbr@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payload([
                'pais' => 'Brasil',
                'cidade' => 'Campinas',
                'uf' => 'California',
            ]))->assertStatus(422)->assertJsonValidationErrors('uf');
    }

    public function test_lookup_encontra_por_documento_e_por_email(): void
    {
        [$user] = $this->medicoComUser('lookup@revskin.com.br');

        $this->actingAs($user)->postJson(route('pacientes.store'), $this->payload())->assertOk();

        $this->actingAs($user)
            ->getJson(route('pacientes.lookup', ['outro_documento' => 'x1234567']))
            ->assertOk()
            ->assertJson(['found' => true, 'match_por' => 'outro_documento']);

        $this->actingAs($user)
            ->getJson(route('pacientes.lookup', ['email' => 'john@example.com']))
            ->assertOk()
            ->assertJson(['found' => true, 'match_por' => 'email']);
    }

    public function test_busca_textual_encontra_por_documento(): void
    {
        [$user] = $this->medicoComUser('busca@revskin.com.br');
        $this->actingAs($user)->postJson(route('pacientes.store'), $this->payload())->assertOk();

        $resp = $this->actingAs($user)->getJson(route('pacientes.search', ['q' => 'X1234567']));
        $resp->assertOk();
        $this->assertCount(1, $resp->json());
    }

    public function test_sync_tiny_envia_estrangeiro_como_tipo_pessoa_e_sem_cidade(): void
    {
        $paciente = Paciente::create([
            'nome' => 'John Smith',
            'pais' => 'Estados Unidos',
            'outro_documento' => 'X1234567',
            'email1' => 'john@example.com',
            'endereco' => '123 Main St',
            'numero' => '10',
            'cidade' => 'Miami',
            'uf' => 'FL',
            'cep' => '33101',
        ]);

        $job = new SyncClienteTinyJob($paciente);
        $metodo = new \ReflectionMethod($job, 'prepararDadosContato');
        $metodo->setAccessible(true);
        $dados = $metodo->invoke($job);

        $this->assertSame('E', $dados['tipoPessoa']);
        $this->assertSame('', $dados['cpfCnpj']);
        $this->assertSame('Estados Unidos', $dados['pais']);
        // Cidade/UF estrangeiras são recusadas pelo contato.incluir do oList/Tiny.
        $this->assertSame('', $dados['endereco']['cidade']);
        $this->assertSame('', $dados['endereco']['uf']);
        $this->assertSame('123 Main St', $dados['endereco']['endereco']);
        $this->assertSame([['tipo' => 'Cliente']], $dados['tiposContato']);

        // Paciente sem CPF deixa de ser descartado antes da chamada.
        $obrigatorios = new \ReflectionMethod($job, 'validarCamposObrigatorios');
        $obrigatorios->setAccessible(true);
        $this->assertTrue($obrigatorios->invoke($job));
    }

    public function test_sync_tiny_mantem_pessoa_fisica_para_brasileiro(): void
    {
        $paciente = Paciente::create([
            'nome' => 'Maria Brasileira',
            'pais' => 'Brasil',
            'cpf' => self::CPF_VALIDO,
            'endereco' => 'Rua Teste',
            'cidade' => 'Curitiba',
            'uf' => 'PR',
        ]);

        $job = new SyncClienteTinyJob($paciente);
        $metodo = new \ReflectionMethod($job, 'prepararDadosContato');
        $metodo->setAccessible(true);
        $dados = $metodo->invoke($job);

        $this->assertSame('F', $dados['tipoPessoa']);
        $this->assertArrayNotHasKey('pais', $dados);
        $this->assertSame('Curitiba', $dados['endereco']['cidade']);
        $this->assertSame('PR', $dados['endereco']['uf']);
        $this->assertSame([['tipo' => 'Cliente']], $dados['tiposContato']);
    }
}
