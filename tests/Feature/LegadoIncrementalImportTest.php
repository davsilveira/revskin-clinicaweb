<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\User;
use App\Services\Migration\LegadoMedicoResolver;
use App\Support\LegadoEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegadoIncrementalImportTest extends TestCase
{
    use RefreshDatabase;

    private function seedMedicoPaciente(): array
    {
        $medico = Medico::create([
            'apelido' => 'Dr Teste',
            'nome_legado' => 'Dr Teste',
            'crm' => '99999',
            'cpf' => '71508635900',
            'ativo' => true,
        ]);
        $paciente = Paciente::create([
            'nome' => 'Paciente Teste',
            'medico_id' => $medico->id,
            'ativo' => true,
        ]);

        return [$medico, $paciente];
    }

    public function test_gerar_numero_usa_max_nao_count(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();

        Receita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => now()->toDateString(),
            'numero' => $paciente->id.'-0005',
            'status' => 'aberta',
            'ativo' => true,
        ]);
        Receita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => now()->toDateString(),
            'numero' => $paciente->id.'-0002',
            'status' => 'aberta',
            'ativo' => true,
        ]);

        $this->assertSame($paciente->id.'-0006', Receita::gerarNumero($paciente->id));
    }

    public function test_legado_email_placeholder(): void
    {
        $this->assertTrue(LegadoEmail::isPlaceholder(null));
        $this->assertTrue(LegadoEmail::isPlaceholder(''));
        $this->assertTrue(LegadoEmail::isPlaceholder('x@cadastraremail.rsk'));
        $this->assertFalse(LegadoEmail::isPlaceholder('real@example.com'));
        $this->assertNull(LegadoEmail::usable('x@cadastraremail.rsk'));
        $this->assertSame('real@example.com', LegadoEmail::usable('real@example.com'));
    }

    public function test_medico_resolver_by_cpf(): void
    {
        Medico::create([
            'apelido' => 'Bhertha',
            'nome_legado' => 'Bhertha Miyuki Tamura',
            'crm' => '67946',
            'cpf' => '71508635900',
            'ativo' => true,
        ]);

        $resolver = app(LegadoMedicoResolver::class);
        $hit = $resolver->resolveOne('715.086.359-00');
        $this->assertNotNull($hit);
        $this->assertSame('cpf', $hit['match_by']);
        $this->assertSame('71508635900', preg_replace('/\D+/', '', (string) $hit['medico']->cpf));
    }

    public function test_admin_can_open_importacao_clw2_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('tools.importacao-clw2.index'))
            ->assertOk();
    }

    public function test_medico_cannot_open_importacao_clw2_page(): void
    {
        $medicoUser = User::factory()->create(['role' => 'medico']);

        $this->actingAs($medicoUser)
            ->get(route('tools.importacao-clw2.index'))
            ->assertForbidden();
    }

    public function test_backfill_receita_legado_id_from_tag(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        $receita = Receita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => now()->toDateString(),
            'numero' => $paciente->id.'-0001',
            'status' => 'aberta',
            'ativo' => true,
            'anotacoes' => "obs\n[legado:504|num:12]",
        ]);

        $this->artisan('migration:backfill-receita-legado-id', ['--force' => true])
            ->assertSuccessful();

        $receita->refresh();
        $this->assertSame(504, (int) $receita->legado_id);
        $this->assertSame('12', $receita->numero_origem);
        $this->assertSame('clw2_importada', $receita->origem);
    }
}
