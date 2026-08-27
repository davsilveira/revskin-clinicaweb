<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Busca por NOME no cadastro de paciente (substitui a busca por CPF) + vínculo de paciente
 * que já existe no sistema — inclusive os clientes trazidos do oList, que entram sem CPF e
 * sem médico.
 */
class PacienteBuscaPorNomeTest extends TestCase
{
    use RefreshDatabase;

    private const CPF_VALIDO = '111.444.777-35';

    /**
     * @return array{0: User, 1: Medico}
     */
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

    private function pacienteDoOlist(array $override = []): Paciente
    {
        return Paciente::create(array_merge([
            'nome' => 'João da Silva',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 98888-7777',
            'email1' => 'joao.silva@example.com',
            'pais' => 'Brasil',
            'tiny_id' => '999001',
            'ativo' => true,
        ], $override));
    }

    public function test_candidatos_exige_tres_caracteres(): void
    {
        [$user] = $this->medicoComUser('bn1@revskin.com.br');
        $this->pacienteDoOlist();

        $this->actingAs($user)
            ->getJson(route('pacientes.candidatos', ['nome' => 'Jo']))
            ->assertOk()
            ->assertJson(['candidatos' => [], 'total' => 0]);
    }

    public function test_candidatos_acha_paciente_de_outro_medico_e_sem_vinculo(): void
    {
        [$user] = $this->medicoComUser('bn2@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        // Em minúsculas de propósito. Sem acento também funciona no MySQL (a coluna é
        // utf8mb4_unicode_ci, insensível a acento) — mas o SQLite dos testes é sensível.
        $resp = $this->actingAs($user)->getJson(route('pacientes.candidatos', ['nome' => 'joão silva']));

        $resp->assertOk();
        $this->assertSame(1, $resp->json('total'));
        $c = $resp->json('candidatos.0');
        $this->assertSame($paciente->id, $c['id']);
        // Os dados que diferenciam homônimos precisam vir na lista.
        $this->assertSame('10/04/1980', $c['data_nascimento_br']);
        $this->assertSame('(11) 98888-7777', $c['celular']);
        $this->assertSame('joao.silva@example.com', $c['email1']);
        $this->assertFalse($c['ja_vinculado']);
        $this->assertTrue($c['do_olist']);
    }

    public function test_candidatos_casa_nome_do_meio_e_diferencia_homonimos(): void
    {
        [$user] = $this->medicoComUser('bn3@revskin.com.br');

        $this->pacienteDoOlist(['nome' => 'João Pedro da Silva', 'email1' => 'jp@example.com', 'tiny_id' => '999002']);
        $this->pacienteDoOlist([
            'nome' => 'João Carlos da Silva',
            'email1' => 'jc@example.com',
            'data_nascimento' => '1995-01-02',
            'celular' => '(21) 97777-6666',
            'tiny_id' => '999003',
        ]);

        // Em minúsculas de propósito. Sem acento também funciona no MySQL (a coluna é
        // utf8mb4_unicode_ci, insensível a acento) — mas o SQLite dos testes é sensível.
        $resp = $this->actingAs($user)->getJson(route('pacientes.candidatos', ['nome' => 'joão silva']));

        $resp->assertOk();
        $this->assertSame(2, $resp->json('total'));
        $celulares = collect($resp->json('candidatos'))->pluck('celular')->all();
        $this->assertContains('(11) 98888-7777', $celulares);
        $this->assertContains('(21) 97777-6666', $celulares);
    }

    public function test_candidatos_marca_ja_vinculado_do_medico_logado(): void
    {
        [$user, $medico] = $this->medicoComUser('bn4@revskin.com.br');
        $paciente = $this->pacienteDoOlist();
        app(\App\Services\PacienteVinculoService::class)->garantir($paciente, $medico->id, ['ativo' => true], $user->id);

        $resp = $this->actingAs($user)->getJson(route('pacientes.candidatos', ['nome' => 'João da Silva']));

        $resp->assertOk();
        $this->assertTrue($resp->json('candidatos.0.ja_vinculado'));
    }

    /**
     * Antes este painel escondia ficha arquivada — e era isso que fazia o sistema responder
     * "nenhum paciente encontrado" para quem tinha receita recente, levando o médico a
     * recadastrar a mesma pessoa (job f8b5e9c5). Agora aparece marcada: a busca do dia a dia
     * segue só com ativo, mas aqui a verdade é dita.
     */
    public function test_candidatos_mostra_inativos_marcados(): void
    {
        [$user] = $this->medicoComUser('bn5@revskin.com.br');
        $this->pacienteDoOlist(['ativo' => false]);

        $resp = $this->actingAs($user)
            ->getJson(route('pacientes.candidatos', ['nome' => 'João da Silva']))
            ->assertOk();

        $this->assertSame(1, $resp->json('total'));
        $this->assertTrue($resp->json('candidatos.0.arquivado'));
    }

    public function test_vincular_cria_vinculo_com_o_medico_logado(): void
    {
        [$user, $medico] = $this->medicoComUser('bn6@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)
            ->postJson(route('pacientes.vincular', $paciente))
            ->assertOk()
            ->assertJson(['success' => true, 'paciente' => ['id' => $paciente->id]]);

        $this->assertDatabaseHas('medico_paciente', [
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'ativo' => true,
            'origem' => 'busca-nome',
        ]);
    }

    public function test_vincular_e_idempotente(): void
    {
        [$user, $medico] = $this->medicoComUser('bn7@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)->postJson(route('pacientes.vincular', $paciente))->assertOk();
        $this->actingAs($user)->postJson(route('pacientes.vincular', $paciente))->assertOk();

        $this->assertSame(1, \App\Models\MedicoPaciente::where('paciente_id', $paciente->id)->count());
    }

    /**
     * O ponto do fluxo novo: escolher um cadastro existente na busca por nome não pode criar
     * um segundo cadastro, mesmo com e-mail diferente do que está gravado.
     */
    public function test_store_com_paciente_existente_id_nao_duplica_e_vincula(): void
    {
        [$user, $medico] = $this->medicoComUser('bn8@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)->postJson(route('pacientes.store'), [
            'nome' => 'João da Silva',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 98888-7777',
            'email1' => 'outro-email@example.com',
            'pais' => 'Brasil',
            'paciente_existente_id' => $paciente->id,
        ])->assertOk();

        $this->assertSame(1, Paciente::count());
        $this->assertSame('outro-email@example.com', $paciente->fresh()->email1);
        $this->assertNotNull($paciente->fresh()->vinculoDoMedico($medico->id));
    }

    /**
     * Cliente do oList entra sem CPF; exigir CPF para poder vinculá-lo travaria o médico.
     */
    public function test_cadastro_existente_sem_cpf_pode_ser_vinculado_sem_informar_cpf(): void
    {
        [$user] = $this->medicoComUser('bn9@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)->postJson(route('pacientes.store'), [
            'nome' => 'João da Silva',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 98888-7777',
            'email1' => 'joao.silva@example.com',
            'pais' => 'Brasil',
            'paciente_existente_id' => $paciente->id,
        ])->assertOk();

        $this->assertNull($paciente->fresh()->cpf);
    }

    public function test_update_de_cadastro_legado_sem_cpf_nao_exige_cpf(): void
    {
        [$user, $medico] = $this->medicoComUser('bn10@revskin.com.br');
        $paciente = $this->pacienteDoOlist();
        app(\App\Services\PacienteVinculoService::class)->garantir($paciente, $medico->id, ['ativo' => true], $user->id);

        $this->actingAs($user)->putJson(route('pacientes.update', $paciente), [
            'nome' => 'João da Silva',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 90000-0000',
            'email1' => 'joao.silva@example.com',
            'pais' => 'Brasil',
        ])->assertOk();

        $this->assertSame('(11) 90000-0000', $paciente->fresh()->celular);
    }

    public function test_update_nao_deixa_apagar_cpf_de_paciente_brasileiro(): void
    {
        [$user, $medico] = $this->medicoComUser('bn11@revskin.com.br');
        $paciente = $this->pacienteDoOlist(['cpf' => self::CPF_VALIDO]);
        app(\App\Services\PacienteVinculoService::class)->garantir($paciente, $medico->id, ['ativo' => true], $user->id);

        $this->actingAs($user)->putJson(route('pacientes.update', $paciente), [
            'nome' => 'João da Silva',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 98888-7777',
            'email1' => 'joao.silva@example.com',
            'pais' => 'Brasil',
            'cpf' => '',
        ])->assertStatus(422)->assertJsonValidationErrors('cpf');

        $this->assertSame(self::CPF_VALIDO, $paciente->fresh()->cpf);
    }

    public function test_candidatos_mascara_documento_de_paciente_de_outro_medico(): void
    {
        [$user] = $this->medicoComUser('bn15@revskin.com.br');
        $this->pacienteDoOlist(['cpf' => self::CPF_VALIDO]);

        $resp = $this->actingAs($user)->getJson(route('pacientes.candidatos', ['nome' => 'João da Silva']));

        $resp->assertOk();
        $c = $resp->json('candidatos.0');
        // O final basta para diferenciar homônimos; o documento inteiro não vaza na lista.
        $this->assertSame('CPF', $c['documento_label']);
        $this->assertStringContainsString('777-35', $c['documento']);
        $this->assertStringNotContainsString('111.444', $c['documento']);
        $this->assertArrayNotHasKey('cpf', $c);
    }

    public function test_candidatos_nao_aceita_curinga_de_like_para_listar_a_base(): void
    {
        [$user] = $this->medicoComUser('bn16@revskin.com.br');
        $this->pacienteDoOlist();
        $this->pacienteDoOlist(['nome' => 'Outra Pessoa', 'email1' => 'outra@example.com', 'tiny_id' => '999009']);

        foreach (['%%%', '___', '%a%'] as $termo) {
            $resp = $this->actingAs($user)->getJson(route('pacientes.candidatos', ['nome' => $termo]));
            $resp->assertOk();
            $this->assertSame(0, $resp->json('total'), "termo {$termo} não deveria listar a base");
        }
    }

    public function test_candidatos_fail_closed_para_medico_sem_cadastro_vinculado(): void
    {
        $user = User::create([
            'name' => 'Medico Sem Vinculo',
            'email' => 'bn17@revskin.com.br',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => null,
            'is_active' => true,
        ]);
        $this->pacienteDoOlist();

        $this->actingAs($user)
            ->getJson(route('pacientes.candidatos', ['nome' => 'João da Silva']))
            ->assertOk()
            ->assertJson(['total' => 0]);
    }

    /**
     * Sem esta travessa, o id sequencial permitiria sobrescrever o cadastro de qualquer
     * paciente do sistema chutando números.
     */
    public function test_store_recusa_paciente_existente_id_com_nome_incompativel(): void
    {
        [$user] = $this->medicoComUser('bn18@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)->postJson(route('pacientes.store'), [
            'nome' => 'Pessoa Totalmente Diferente',
            'data_nascimento' => '1990-01-01',
            'celular' => '(11) 90000-0000',
            'email1' => 'invasor@example.com',
            'cpf' => self::CPF_VALIDO,
            'pais' => 'Brasil',
            'paciente_existente_id' => $paciente->id,
        ])->assertStatus(422)->assertJsonValidationErrors('nome');

        $this->assertSame('João da Silva', $paciente->fresh()->nome);
        $this->assertSame('joao.silva@example.com', $paciente->fresh()->email1);
    }

    /**
     * Escolher na busca por nome um paciente que já é seu tem de salvar (atualização), não
     * devolver 422 — senão a única saída visível é criar o duplicado.
     */
    public function test_store_com_paciente_existente_id_ja_vinculado_atualiza_em_vez_de_barrar(): void
    {
        [$user, $medico] = $this->medicoComUser('bn19@revskin.com.br');
        $paciente = $this->pacienteDoOlist();
        app(\App\Services\PacienteVinculoService::class)->garantir($paciente, $medico->id, ['ativo' => true], $user->id);

        $this->actingAs($user)->postJson(route('pacientes.store'), [
            'nome' => 'João da Silva',
            'data_nascimento' => '1980-04-10',
            'celular' => '(11) 91111-2222',
            'email1' => 'joao.silva@example.com',
            'pais' => 'Brasil',
            'paciente_existente_id' => $paciente->id,
        ])->assertOk();

        $this->assertSame(1, Paciente::count());
        $this->assertSame('(11) 91111-2222', $paciente->fresh()->celular);
    }

    public function test_vincular_recusa_medico_id_do_corpo_para_usuario_medico(): void
    {
        [$user, $medico] = $this->medicoComUser('bn20@revskin.com.br');
        [, $outroMedico] = $this->medicoComUser('bn20b@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)
            ->postJson(route('pacientes.vincular', $paciente), ['medico_id' => $outroMedico->id])
            ->assertOk();

        // Vinculou ao médico do usuário logado, nunca ao médico enviado no corpo.
        $this->assertDatabaseHas('medico_paciente', ['medico_id' => $medico->id, 'paciente_id' => $paciente->id]);
        $this->assertDatabaseMissing('medico_paciente', ['medico_id' => $outroMedico->id, 'paciente_id' => $paciente->id]);
    }

    public function test_vincular_fail_closed_para_medico_sem_cadastro_vinculado(): void
    {
        $user = User::create([
            'name' => 'Medico Sem Vinculo 2',
            'email' => 'bn21@revskin.com.br',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => null,
            'is_active' => true,
        ]);
        $medico = Medico::create(['apelido' => 'Dr Alheio']);
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)
            ->postJson(route('pacientes.vincular', $paciente), ['medico_id' => $medico->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('medico_id');

        $this->assertDatabaseCount('medico_paciente', 0);
    }

    public function test_vincular_devolve_cadastro_completo(): void
    {
        [$user] = $this->medicoComUser('bn22@revskin.com.br');
        $paciente = $this->pacienteDoOlist([
            'cidade' => 'Lisboa',
            'pais' => 'Portugal',
            'uf' => 'Lisboa',
            'sexo' => 'F',
            'endereco' => 'Rua das Flores',
        ]);

        $resp = $this->actingAs($user)->postJson(route('pacientes.vincular', $paciente));

        // Payload parcial faria o autosave do drawer gravar vazio sobre estes campos.
        $resp->assertOk()->assertJsonPath('paciente.cidade', 'Lisboa')
            ->assertJsonPath('paciente.pais', 'Portugal')
            ->assertJsonPath('paciente.sexo', 'F')
            ->assertJsonPath('paciente.endereco', 'Rua das Flores');
        $this->assertIsArray($resp->json('paciente.telefones'));
    }

    public function test_lookup_por_email_marca_match_fraco(): void
    {
        [$user] = $this->medicoComUser('bn23@revskin.com.br');
        $this->pacienteDoOlist(['email1' => 'familia@example.com']);

        $this->actingAs($user)
            ->getJson(route('pacientes.lookup', ['email' => 'familia@example.com']))
            ->assertOk()
            ->assertJson(['found' => true, 'match_por' => 'email', 'match_forte' => false]);

        $this->actingAs($user)
            ->getJson(route('pacientes.lookup', ['id' => Paciente::first()->id]))
            ->assertOk()
            ->assertJson(['match_por' => 'id', 'match_forte' => true]);
    }

    public function test_lookup_por_id_devolve_dados_compartilhados(): void
    {
        [$user] = $this->medicoComUser('bn12@revskin.com.br');
        $paciente = $this->pacienteDoOlist();

        $this->actingAs($user)
            ->getJson(route('pacientes.lookup', ['id' => $paciente->id]))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'match_por' => 'id',
                'ja_vinculado' => false,
                'paciente' => ['id' => $paciente->id, 'nome' => 'João da Silva'],
            ]);
    }

    public function test_quick_create_brasileiro_sem_cpf_e_barrado(): void
    {
        [$user] = $this->medicoComUser('bn13@revskin.com.br');

        $this->actingAs($user)->postJson(route('pacientes.quickCreate'), [
            'nome' => 'Novo Paciente BR',
            'data_nascimento' => '1990-02-03',
            'celular' => '(11) 91234-5678',
            'email1' => 'novo@example.com',
            'pais' => 'Brasil',
        ])->assertStatus(422)->assertJsonValidationErrors('cpf');
    }

    public function test_quick_create_estrangeiro_sem_documento_continua_passando(): void
    {
        [$user] = $this->medicoComUser('bn14@revskin.com.br');

        $this->actingAs($user)->postJson(route('pacientes.quickCreate'), [
            'nome' => 'Foreign Patient',
            'data_nascimento' => '1990-02-03',
            'celular' => '+1 305 555 0199',
            'email1' => 'foreign@example.com',
            'pais' => 'Estados Unidos',
            'cidade' => 'Miami',
            'uf' => 'Florida',
        ])->assertOk();

        $this->assertDatabaseHas('pacientes', ['email1' => 'foreign@example.com', 'cpf' => null]);
    }
}
