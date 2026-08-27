<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * E-mail opcional no cadastro de paciente (decisão do cliente, 08/2026) e conserto dos
 * cadastros travados pelo domínio de marcação com underline.
 */
class PacienteEmailOpcionalTest extends TestCase
{
    use RefreshDatabase;

    private const CPF_VALIDO = '111.444.777-35';

    private const CPF_VALIDO_2 = '529.982.247-25';

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
            'nome' => 'Maria da Silva',
            'data_nascimento' => '1990-03-10',
            'celular' => '(21) 99999-8888',
            'cpf' => self::CPF_VALIDO,
            'pais' => 'Brasil',
            'cidade' => 'Campinas',
            'uf' => 'SP',
        ], $override);
    }

    public function test_cadastro_novo_salva_sem_email(): void
    {
        [$user] = $this->medicoComUser('sememail1@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payload(['email1' => '']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $paciente = Paciente::where('nome', 'Maria da Silva')->firstOrFail();
        // NULL, não string vazia: "sem e-mail" precisa ter uma representação só.
        $this->assertNull($paciente->email1);
    }

    public function test_email_mal_formado_continua_barrado(): void
    {
        [$user] = $this->medicoComUser('sememail2@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.store'), $this->payload(['email1' => 'nao-e-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email1']);
    }

    /**
     * O bug dos ~150 cadastros: `@cadastrar_email.com` (underline) não é e-mail válido, então
     * qualquer edição devolvia 422 e o autosave repetia o erro a cada 2 s.
     */
    public function test_cadastro_com_placeholder_invalido_volta_a_salvar(): void
    {
        [$user, $medico] = $this->medicoComUser('travado@revskin.com.br');

        $paciente = Paciente::create([
            'nome' => 'Andrea Costa',
            'data_nascimento' => '1980-01-01',
            'celular' => '(21) 99592-2692',
            'email1' => '21995922692@cadastrar_email.com',
            'medico_id' => $medico->id,
            'pais' => 'Brasil',
        ]);
        $paciente->medicos()->syncWithoutDetaching([$medico->id]);

        $this->actingAs($user)
            ->putJson(route('pacientes.update', $paciente), [
                'nome' => 'Andrea Costa',
                'data_nascimento' => '1980-01-01',
                'celular' => '(21) 99592-2692',
                'email1' => '21995922692@cadastrar_email.com',
                'pais' => 'Brasil',
            ])
            ->assertOk();

        $this->assertSame('21995922692@cadastraremail.rsk', $paciente->fresh()->email1);
    }

    public function test_autosave_aceita_email_vazio_e_normaliza_placeholder(): void
    {
        [$user, $medico] = $this->medicoComUser('autosave@revskin.com.br');

        $paciente = Paciente::create([
            'nome' => 'Carla Souza',
            'data_nascimento' => '1975-07-07',
            'celular' => '(21) 98888-7777',
            'email1' => '21988887777@cadastraremail.com',
            'medico_id' => $medico->id,
            'pais' => 'Brasil',
        ]);
        $paciente->medicos()->syncWithoutDetaching([$medico->id]);

        $this->actingAs($user)
            ->postJson(route('pacientes.autosave'), [
                'id' => $paciente->id,
                'nome' => 'Carla Souza',
                'data_nascimento' => '1975-07-07',
                'celular' => '(21) 98888-7777',
                'email1' => '21988887777@cadastraremail.com',
                'pais' => 'Brasil',
            ])
            ->assertOk();

        $this->assertSame('21988887777@cadastraremail.rsk', $paciente->fresh()->email1);
    }

    /**
     * Opção 2: o 2º médico cadastra a mesma pessoa (mesmo CPF) sem preencher e-mail. O
     * endereço que o 1º médico já tinha registrado não pode ser apagado por isso.
     */
    public function test_upsert_sem_email_nao_apaga_o_email_de_quem_ja_tinha(): void
    {
        [$user1] = $this->medicoComUser('med1@revskin.com.br');
        [$user2] = $this->medicoComUser('med2@revskin.com.br');

        $this->actingAs($user1)
            ->postJson(route('pacientes.store'), $this->payload([
                'cpf' => self::CPF_VALIDO_2,
                'email1' => 'maria@gmail.com',
            ]))
            ->assertOk();

        $this->actingAs($user2)
            ->postJson(route('pacientes.store'), $this->payload([
                'cpf' => self::CPF_VALIDO_2,
                'email1' => '',
            ]))
            ->assertOk();

        $paciente = Paciente::where('nome', 'Maria da Silva')->firstOrFail();
        $this->assertSame('maria@gmail.com', $paciente->email1);
    }

    /**
     * Já na edição do próprio cadastro, limpar o campo apaga mesmo — é o usuário dizendo
     * que aquele endereço não vale mais.
     */
    public function test_edicao_direta_pode_limpar_o_email(): void
    {
        [$user, $medico] = $this->medicoComUser('limpa@revskin.com.br');

        $paciente = Paciente::create([
            'nome' => 'Joana Lima',
            'data_nascimento' => '1988-02-02',
            'celular' => '(21) 97777-6666',
            'email1' => 'joana@gmail.com',
            'medico_id' => $medico->id,
            'pais' => 'Brasil',
        ]);
        $paciente->medicos()->syncWithoutDetaching([$medico->id]);

        $this->actingAs($user)
            ->putJson(route('pacientes.update', $paciente), [
                'nome' => 'Joana Lima',
                'data_nascimento' => '1988-02-02',
                'celular' => '(21) 97777-6666',
                'email1' => '',
                'pais' => 'Brasil',
            ])
            ->assertOk();

        $this->assertNull($paciente->fresh()->email1);
    }

    public function test_quick_create_do_assistente_salva_sem_email(): void
    {
        [$user] = $this->medicoComUser('quick@revskin.com.br');

        $this->actingAs($user)
            ->postJson(route('pacientes.quickCreate'), [
                'nome' => 'Paula Andrade',
                'data_nascimento' => '1992-11-11',
                'celular' => '(21) 96666-5555',
                'cpf' => self::CPF_VALIDO,
                'pais' => 'Brasil',
                'cidade' => 'Campinas',
                'uf' => 'SP',
            ])
            ->assertOk();

        $this->assertNull(Paciente::where('nome', 'Paula Andrade')->firstOrFail()->email1);
    }
}
