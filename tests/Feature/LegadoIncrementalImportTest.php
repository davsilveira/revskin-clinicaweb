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

    public function test_admin_can_fetch_report_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hash = 'abcdef0123456789abcdef0123456789';
        $dir = storage_path('app/imports/'.$hash);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/report-latest.json', json_encode([
            'report_hash' => $hash,
            'dry_run' => true,
            'generated_at' => now()->toIso8601String(),
            'changes' => [
                'pacientes' => [[
                    'id' => 'p-1',
                    'entity' => 'paciente',
                    'action' => 'novo',
                    'action_label' => 'Novo',
                    'label' => 'Fulano',
                    'subtitle' => 'teste',
                    'diff' => [['field' => 'nome', 'from' => null, 'to' => 'Fulano', 'op' => 'add']],
                    'before' => [],
                    'after' => ['nome' => 'Fulano'],
                ]],
                'receitas' => [],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $this->actingAs($admin)
            ->getJson(route('tools.importacao-clw2.report.changes', ['hash' => $hash]))
            ->assertOk()
            ->assertJsonPath('pacientes.0.id', 'p-1')
            ->assertJsonPath('pacientes.0.diff_count', 1)
            ->assertJsonMissingPath('pacientes.0.diff');

        $this->actingAs($admin)
            ->getJson(route('tools.importacao-clw2.report.change', ['hash' => $hash, 'id' => 'p-1']))
            ->assertOk()
            ->assertJsonPath('id', 'p-1')
            ->assertJsonPath('diff.0.to', 'Fulano');
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

    public function test_upsert_receita_skips_when_dump_not_newer_and_no_local_edit(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        $receita = Receita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => '2025-08-12',
            'numero' => $paciente->id.'-0001',
            'status' => 'finalizada',
            'ativo' => true,
            'legado_id' => 866,
            'numero_origem' => '1',
            'origem' => 'clw2_importada',
        ]);
        $receita->forceFill([
            'created_at' => '2026-06-16 07:56:03',
            'updated_at' => '2026-06-16 07:56:03',
        ])->saveQuietly();

        $importer = app(\App\Services\Migration\LegadoIncrementalImporter::class);
        $ref = new \ReflectionClass($importer);
        $method = $ref->getMethod('upsertReceita');
        $method->setAccessible(true);

        $result = $method->invoke(
            $importer,
            [
                'legado_id' => 866,
                'numero_legado' => '1',
                'data_receita' => '2025-08-12',
                'status' => 'finalizada',
                'dta_ult_alteracao' => '2025-02-04 10:00:00',
                'itens' => [],
            ],
            $paciente->id,
            $medico->id,
            [],
            collect()
        );

        $this->assertSame('receitas_skip', $result['stat']);
        $this->assertNull($result['change'] ?? null);
        $this->assertSame('2026-06-16 07:56:03', $receita->fresh()->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_upsert_paciente_skips_when_no_field_diff(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        $paciente->update([
            'cpf' => '12345678909',
            'nome' => 'Paciente Teste',
            'sexo' => 'F',
        ]);

        $importer = app(\App\Services\Migration\LegadoIncrementalImporter::class);
        $ref = new \ReflectionClass($importer);
        $method = $ref->getMethod('upsertPaciente');
        $method->setAccessible(true);

        $result = $method->invoke(
            $importer,
            [
                'legado_id' => 999001,
                'legado_medico_id' => null,
                'nome' => 'Paciente Teste',
                'cpf' => '123.456.789-09',
                'sexo' => 'F',
                'dta_ult_alteracao' => '2024-01-01 00:00:00',
            ],
            []
        );

        $this->assertSame('pacientes_skip', $result['stat']);
        $this->assertNull($result['change'] ?? null);
        $this->assertSame($paciente->id, $result['paciente_id']);
    }
}
