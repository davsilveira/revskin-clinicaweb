<?php

namespace Tests\Feature;

use App\Jobs\CriarNegociacaoRdStationJob;
use App\Jobs\CriarPedidoTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Médico em treinamento: receitas dele não vão para o oList nem para o RD Station.
 */
class MedicoEmTreinamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('tiny_enabled', true);
        Setting::set('rd_enabled', true);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-treinamento@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function medicoUser(bool $emTreinamento): User
    {
        $medico = Medico::create(['crm' => '123456', 'uf_crm' => 'SP', 'em_treinamento' => $emTreinamento]);

        return User::create([
            'name' => 'Dra. Treinamento',
            'email' => 'treinamento@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);
    }

    private function receitaAberta(User $user): Receita
    {
        Bus::fake(); // PacienteObserver dispara SyncClienteTinyJob
        $paciente = Paciente::create(['nome' => 'Paciente Treino', 'medico_id' => $user->medico_id]);
        $produto = Produto::create(['codigo' => 'TR-001', 'nome' => 'Produto', 'preco' => 50, 'ativo' => true]);
        $receita = Receita::create([
            'numero' => '77-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $user->medico_id,
            'status' => 'aberta',
        ]);
        ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 50,
            'valor_total' => 50,
            'imprimir' => true,
            'grupo' => 'recomendado',
            'ordem' => 0,
        ]);

        return $receita;
    }

    public function test_receita_de_medico_em_treinamento_nasce_marcada(): void
    {
        $receita = $this->receitaAberta($this->medicoUser(true));

        $this->assertTrue($receita->fresh()->treinamento);
    }

    public function test_finalizar_receita_em_treinamento_nao_despacha_olist_nem_rd(): void
    {
        $user = $this->medicoUser(true);
        $receita = $this->receitaAberta($user);

        $this->actingAs($user)
            ->post(route('receitas.finalizar', $receita))
            ->assertRedirect(route('receitas.show', $receita));

        $this->assertSame('finalizada', $receita->fresh()->status);
        $this->assertDatabaseHas('atendimentos_callcenter', ['receita_id' => $receita->id]);
        Bus::assertNotDispatched(CriarPedidoTinyJob::class);
        Bus::assertNotDispatched(CriarNegociacaoRdStationJob::class);
    }

    public function test_medico_entra_em_treinamento_com_receita_ja_aberta(): void
    {
        $user = $this->medicoUser(false);
        $receita = $this->receitaAberta($user);
        $this->assertFalse($receita->fresh()->treinamento);

        $user->medico->update(['em_treinamento' => true]);

        $this->actingAs($user)->post(route('receitas.finalizar', $receita));

        $this->assertTrue($receita->fresh()->treinamento);
        Bus::assertNotDispatched(CriarPedidoTinyJob::class);
        Bus::assertNotDispatched(CriarNegociacaoRdStationJob::class);
    }

    public function test_finalizar_receita_normal_continua_despachando(): void
    {
        $user = $this->medicoUser(false);
        $receita = $this->receitaAberta($user);

        $this->actingAs($user)->post(route('receitas.finalizar', $receita));

        Bus::assertDispatched(CriarPedidoTinyJob::class);
        Bus::assertDispatched(CriarNegociacaoRdStationJob::class);
    }

    public function test_jobs_ignoram_receita_de_treinamento_mesmo_em_reenvio(): void
    {
        $user = $this->medicoUser(true);
        $receita = $this->receitaAberta($user);
        $receita->update(['status' => 'finalizada']);

        // Saiu do treinamento depois: a receita de teste continua sem ir para fora.
        $user->medico->update(['em_treinamento' => false]);

        Http::fake();
        (new CriarPedidoTinyJob($receita))->handle();
        (new CriarNegociacaoRdStationJob($receita))->handle();
        Http::assertNothingSent();

        $this->assertNull($receita->fresh()->tiny_pedido_id);
        $this->assertNull($receita->fresh()->rd_deal_id);
    }

    public function test_admin_liga_e_desliga_treinamento_no_cadastro_de_usuario(): void
    {
        $admin = $this->admin();
        $user = $this->medicoUser(false);

        $payload = [
            'name' => 'Dra. Treinamento',
            'email' => 'treinamento@example.com',
            'role' => 'medico',
            'is_active' => true,
            'crm' => '123456',
            'uf_crm' => 'SP',
            'telefone' => '1140028922',
        ];

        $this->actingAs($admin)->put(route('users.update', $user), $payload + ['em_treinamento' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->medico->fresh()->em_treinamento);

        // Sem o campo (ex.: form de Médicos), o valor é preservado.
        $this->actingAs($admin)->put(route('users.update', $user), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($user->medico->fresh()->em_treinamento);

        $this->actingAs($admin)->put(route('users.update', $user), $payload + ['em_treinamento' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($user->medico->fresh()->em_treinamento);
    }

    public function test_filtro_de_usuarios_mostra_so_medicos_em_treinamento(): void
    {
        $admin = $this->admin();
        $emTreino = $this->medicoUser(true);
        $outro = User::create([
            'name' => 'Dr. Normal',
            'email' => 'normal@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => Medico::create(['crm' => '654321', 'uf_crm' => 'SP'])->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('users.index', ['role' => 'treinamento']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.role', 'treinamento')
                ->has('users.data', 1)
                ->where('users.data.0.id', $emTreino->id));
    }
}
