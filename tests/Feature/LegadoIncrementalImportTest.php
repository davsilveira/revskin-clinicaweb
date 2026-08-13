<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
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
        // Domínios antigos que o dump do CLW2 ainda carrega — também são placeholder.
        $this->assertTrue(LegadoEmail::isPlaceholder('11996960077@cadastraremail.com'));
        $this->assertTrue(LegadoEmail::isPlaceholder('x@cadastrar_email.com'));
        $this->assertFalse(LegadoEmail::isPlaceholder('real@example.com'));
        $this->assertNull(LegadoEmail::usable('x@cadastraremail.rsk'));
        $this->assertNull(LegadoEmail::usable('11996960077@cadastraremail.com'));
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

    /**
     * Regressão do job 946a5c52: paciente sem CPF, sem código, sem nascimento e sem celular era
     * recriado a cada rodada, porque nenhuma heurística o reencontrava e havia homônimo no CLW3.
     * A receita já importada (`legado_id`) é a âncora determinística.
     */
    public function test_paciente_sem_dado_conciliavel_casa_pela_receita_ja_importada(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        $paciente->update(['nome' => 'GUSTAVO RABELLO', 'cpf' => null, 'data_nascimento' => null, 'celular' => null]);

        // Homônimo: é o que fazia a heurística desistir e criar um terceiro cadastro.
        Paciente::create(['nome' => 'GUSTAVO RABELLO', 'medico_id' => $medico->id, 'ativo' => true]);

        Receita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => '2025-03-10',
            'numero' => $paciente->id.'-0001',
            'status' => 'finalizada',
            'ativo' => true,
            'legado_id' => 8801,
            'origem' => 'clw2_importada',
        ]);

        $importer = app(\App\Services\Migration\LegadoIncrementalImporter::class);
        $ref = new \ReflectionClass($importer);
        $ancora = $ref->getMethod('carregarAncoraDeImportacaoAnterior');
        $ancora->setAccessible(true);
        $ancora->invoke($importer, [
            ['legado_id' => 8801, 'legado_paciente_id' => 456, 'legado_medico_id' => 26],
        ]);

        $upsert = $ref->getMethod('upsertPaciente');
        $upsert->setAccessible(true);
        $result = $upsert->invokeArgs($importer, [
            [
                'legado_id' => 456,
                'legado_medico_id' => null,
                'nome' => 'GUSTAVO RABELLO',
                'cpf' => null,
                'data_nascimento' => null,
                'celular' => null,
                'dta_ult_alteracao' => '2024-01-01 00:00:00',
            ],
            [],
        ]);

        $this->assertNotSame('pacientes_novos', $result['stat']);
        $this->assertSame($paciente->id, $result['paciente_id']);
        $this->assertSame(2, Paciente::where('nome', 'GUSTAVO RABELLO')->count());
    }

    public function test_upsert_paciente_skips_when_no_field_diff(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        $paciente->update([
            'cpf' => '12345678909',
            'nome' => 'Paciente Teste',
            'sexo' => 'F',
        ]);

        $result = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 999001,
                'legado_medico_id' => null,
                'nome' => 'Paciente Teste',
                'cpf' => '123.456.789-09',
                'sexo' => 'F',
                'dta_ult_alteracao' => '2024-01-01 00:00:00',
            ],
            [],
        ]);

        $this->assertSame('pacientes_skip', $result['stat']);
        $this->assertNull($result['change'] ?? null);
        $this->assertSame($paciente->id, $result['paciente_id']);
    }

    /**
     * Job f8b5e9c5: a receita de junho/2026 entrou numa ficha arquivada e o report não disse nada —
     * a médica só descobriu no atendimento, procurando uma paciente que a busca jurava não existir.
     */
    public function test_receita_em_ficha_arquivada_avisa_no_report(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        $paciente->update(['ativo' => false]);

        $result = $this->invocarImporter('upsertReceita', [
            $this->itemLegadoReceita(),
            $paciente->id,
            $medico->id,
            [],
            collect(),
        ]);

        $this->assertSame('receitas_novas', $result['stat']);
        $this->assertSame('paciente_arquivado', $result['change']['warning']);
        $this->assertSame('receita_em_ficha_invisivel', $result['signal']['tipo']);
        $this->assertSame($paciente->id, $result['signal']['paciente_id']);
    }

    public function test_receita_em_vinculo_arquivado_avisa_no_report(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        app(\App\Services\PacienteVinculoService::class)->garantir($paciente, $medico->id, ['ativo' => false]);

        $result = $this->invocarImporter('upsertReceita', [
            $this->itemLegadoReceita(),
            $paciente->id,
            $medico->id,
            [],
            collect(),
        ]);

        $this->assertSame('vinculo_arquivado', $result['change']['warning']);
    }

    public function test_receita_em_ficha_visivel_nao_gera_aviso(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();

        $result = $this->invocarImporter('upsertReceita', [
            $this->itemLegadoReceita(),
            $paciente->id,
            $medico->id,
            [],
            collect(),
        ]);

        $this->assertNull($result['change']['warning']);
        $this->assertNull($result['signal']);
    }

    /**
     * Ficha ativa no CLW2 que caiu numa ficha arquivada aqui: o merge mantém arquivado (é decisão
     * do CLW3), mas isso tem de aparecer no report em vez de virar skip silencioso.
     */
    public function test_paciente_ativo_no_legado_sinaliza_ficha_arquivada_no_clw3(): void
    {
        [$medico, $paciente] = $this->seedMedicoPaciente();
        $paciente->update([
            'nome' => 'z-Paciente Teste',
            'cpf' => '12345678909',
            'sexo' => 'F',
            'ativo' => false,
        ]);
        app(\App\Services\PacienteVinculoService::class)->garantir($paciente, $medico->id, ['ativo' => false]);

        $result = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 594,
                'legado_medico_id' => null,
                'nome' => 'Paciente Teste',
                'cpf' => '123.456.789-09',
                'sexo' => 'F',
                'ativo' => true,
                'dta_ult_alteracao' => '2024-01-01 00:00:00',
            ],
            [],
        ]);

        $this->assertSame('pacientes_skip', $result['stat']);
        $this->assertSame('paciente_ativo_no_legado_arquivado_no_clw3', $result['signal']['tipo']);
        $this->assertSame(594, $result['signal']['legado_id']);
        $this->assertFalse((bool) $paciente->fresh()->ativo);
    }

    /**
     * @param  list<mixed>  $args
     */
    private function invocarImporter(string $metodo, array $args): mixed
    {
        $importer = app(\App\Services\Migration\LegadoIncrementalImporter::class);
        $ref = new \ReflectionClass($importer);
        $m = $ref->getMethod($metodo);
        $m->setAccessible(true);

        return $m->invokeArgs($importer, $args);
    }

    private function seedMedico(string $apelido, string $crm, string $cpf): Medico
    {
        return Medico::create([
            'apelido' => $apelido,
            'nome_legado' => $apelido,
            'crm' => $crm,
            'cpf' => $cpf,
            'ativo' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // Regressões da revisão do job e026f895
    // ---------------------------------------------------------------------

    /** Sem diff de campos, o médico liberado ainda precisa passar a enxergar o paciente. */
    public function test_paciente_sem_diff_ganha_vinculo_do_medico_liberado(): void
    {
        $medicoA = $this->seedMedico('Dr A', '111', '11111111111');
        $medicoB = $this->seedMedico('Dr B', '222', '22222222222');

        $paciente = Paciente::create([
            'nome' => 'Fulana de Tal',
            'cpf' => '12345678909',
            'sexo' => 'F',
            'celular' => '(11) 99999-0000',
            'medico_id' => $medicoA->id,
            'ativo' => true,
        ]);

        $result = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 4242,
                'legado_medico_id' => 77,
                'nome' => 'Fulana de Tal',
                'cpf' => '123.456.789-09',
                'sexo' => 'F',
                'celular' => '(11) 99999-0000',
                'dta_ult_alteracao' => '2024-01-01 00:00:00',
            ],
            [77 => $medicoB->id],
        ]);

        $this->assertSame('pacientes_merge', $result['stat']);
        $this->assertSame($paciente->id, $result['paciente_id']);
        $this->assertDatabaseHas('medico_paciente', [
            'medico_id' => $medicoB->id,
            'paciente_id' => $paciente->id,
        ]);

        // Segunda passada: vínculo já existe e nada mudou → skip de verdade.
        $again = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 4242,
                'legado_medico_id' => 77,
                'nome' => 'Fulana de Tal',
                'cpf' => '123.456.789-09',
                'sexo' => 'F',
                'celular' => '(11) 99999-0000',
                'dta_ult_alteracao' => '2024-01-01 00:00:00',
            ],
            [77 => $medicoB->id],
        ]);
        $this->assertSame('pacientes_skip', $again['stat']);
    }

    /** As notas privadas do médico no vínculo não podem ser trocadas pelas do dump. */
    public function test_import_nao_sobrescreve_notas_privadas_do_vinculo(): void
    {
        $medico = $this->seedMedico('Dr H', '999', '99999999999');
        $paciente = Paciente::create(['nome' => 'Fulana Vinculada', 'cpf' => '12345678909', 'ativo' => true]);

        app(\App\Services\PacienteVinculoService::class)->garantir($paciente, $medico->id, [
            'anotacoes' => 'Alergia a ácido — anotado no CLW3',
            'codigo' => 'CLW3-1',
        ], null, 'form');

        $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 9003,
                'legado_medico_id' => 504,
                'nome' => 'Fulana Vinculada',
                'cpf' => '123.456.789-09',
                'anotacoes' => 'observação antiga do CLW2',
                'codigo' => 'CW2-77',
            ],
            [504 => $medico->id],
        ]);

        $this->assertDatabaseHas('medico_paciente', [
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'anotacoes' => 'Alergia a ácido — anotado no CLW3',
            'codigo' => 'CLW3-1',
        ]);
    }

    /** Paciente sem vínculo nenhum (veio do oList) tem de ser mesclado, não duplicado. */
    public function test_paciente_de_outro_medico_casa_por_nome_e_nascimento(): void
    {
        $medico = $this->seedMedico('Dr C', '333', '33333333333');
        $paciente = Paciente::create([
            'nome' => 'Beatriz Helena de Figueiredo',
            'data_nascimento' => '1966-04-27',
            'cpf' => '09227800808',
            'ativo' => true,
        ]);

        $result = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 1504,
                'legado_medico_id' => 504,
                'nome' => 'Beatriz Helena de Figueiredo',
                'data_nascimento' => '1966-04-27',
                'cpf' => null,
            ],
            [504 => $medico->id],
        ]);

        $this->assertSame('pacientes_merge', $result['stat']);
        $this->assertSame($paciente->id, $result['paciente_id']);
        $this->assertSame(1, Paciente::where('nome', 'Beatriz Helena de Figueiredo')->count());
    }

    /** Data de nascimento lixo no dump não pode gerar cadastro paralelo. */
    public function test_paciente_com_data_invalida_casa_por_celular(): void
    {
        $medico = $this->seedMedico('Dr D', '444', '44444444444');
        $paciente = Paciente::create([
            'nome' => 'Neiva Salete Menegatti',
            'data_nascimento' => '9870-03-11',
            'celular' => '(11) 99986-2598',
            'ativo' => true,
        ]);

        $result = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 727,
                'legado_medico_id' => 504,
                'nome' => 'Neiva Salete Menegatti',
                'data_nascimento' => '9862-99-11',
                'celular' => '(11) 99986-2598',
                'cpf' => null,
            ],
            [504 => $medico->id],
        ]);

        $this->assertSame('pacientes_merge', $result['stat']);
        $this->assertSame($paciente->id, $result['paciente_id']);
    }

    /** Homônimo sem nascimento nem telefone em comum continua virando cadastro novo — sinalizado. */
    public function test_homonimo_sem_confirmacao_vira_novo_com_alerta(): void
    {
        $medico = $this->seedMedico('Dr E', '555', '55555555555');
        Paciente::create([
            'nome' => 'Maria Silva',
            'data_nascimento' => '1980-01-01',
            'celular' => '(11) 90000-0001',
            'ativo' => true,
        ]);

        $result = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 9001,
                'legado_medico_id' => 504,
                'nome' => 'Maria Silva',
                'data_nascimento' => '1995-06-15',
                'celular' => '(11) 90000-0002',
            ],
            [504 => $medico->id],
        ]);

        $this->assertSame('pacientes_novos', $result['stat']);
        $this->assertTrue($result['needs_review']);
        $this->assertSame('paciente_novo_com_homonimo', $result['signal']['tipo']);
    }

    public function test_data_invalida_do_legado_nao_e_gravada(): void
    {
        $medico = $this->seedMedico('Dr F', '666', '66666666666');

        $result = $this->invocarImporter('upsertPaciente', [
            [
                'legado_id' => 9002,
                'legado_medico_id' => 504,
                'nome' => 'Paciente Data Lixo',
                'data_nascimento' => '9862-99-11',
            ],
            [504 => $medico->id],
        ]);

        $this->assertSame('pacientes_novos', $result['stat']);
        $this->assertNull(Paciente::find($result['paciente_id'])->data_nascimento);
    }

    /** Receita idêntica ao dump não pode apagar/recriar item (cascata em receita_item_aquisicoes). */
    public function test_receita_sem_mudanca_nao_reescreve_itens(): void
    {
        [$medico, $paciente, $receita, $item] = $this->seedReceitaImportada();

        $result = $this->invocarImporter('upsertReceita', [
            $this->itemLegadoReceita(['local_uso' => 'ROSTO']),
            $paciente->id,
            $medico->id,
            [],
            Produto::all()->keyBy('codigo'),
        ]);

        $this->assertSame('receitas_skip', $result['stat']);
        $this->assertSame($item->id, ReceitaItem::where('receita_id', $receita->id)->first()->id);
        $this->assertSame(
            '2026-06-16 07:00:00',
            $receita->fresh()->updated_at->format('Y-m-d H:i:s')
        );
    }

    /** Quando muda de verdade, o item é atualizado no lugar: `vendido` e aquisição sobrevivem. */
    public function test_receita_atualizada_preserva_vendido_e_aquisicao(): void
    {
        [$medico, $paciente, $receita, $item] = $this->seedReceitaImportada();
        $item->forceFill(['vendido' => true, 'data_aquisicao' => '2026-05-01'])->saveQuietly();

        $result = $this->invocarImporter('upsertReceita', [
            $this->itemLegadoReceita(['local_uso' => 'PESCOÇO', 'quantidade' => 2]),
            $paciente->id,
            $medico->id,
            [],
            Produto::all()->keyBy('codigo'),
        ]);

        $this->assertSame('receitas_atualizadas', $result['stat']);

        $depois = ReceitaItem::where('receita_id', $receita->id)->first();
        $this->assertSame($item->id, $depois->id, 'item deve ser atualizado no lugar');
        $this->assertTrue((bool) $depois->vendido);
        $this->assertSame('2026-05-01', $depois->data_aquisicao->format('Y-m-d'));
        $this->assertSame('PESCOÇO', $depois->local_uso);

        $campos = collect($result['change']['diff'])->pluck('field')->all();
        $this->assertContains('itens_resumo', $campos, 'a troca de item precisa aparecer no diff');
    }

    /** Anotações internas escritas no CLW3 não podem ser trocadas pelas do dump. */
    public function test_refresh_preserva_anotacoes_internas_do_clw3(): void
    {
        [$medico, $paciente, $receita] = $this->seedReceitaImportada();
        // updated_at explícito: o Eloquent carimbaria a data de hoje e a receita entraria como
        // "editada no CLW3" (conflito), que não é o cenário deste teste.
        Receita::where('id', $receita->id)->update([
            'anotacoes' => "Paciente relatou ardência — reduzir concentração.\n[legado:5150|num:1]",
            'updated_at' => '2026-06-16 07:00:00',
        ]);

        $this->invocarImporter('upsertReceita', [
            $this->itemLegadoReceita(['local_uso' => 'PESCOÇO', 'anotacoes_receita' => 'obs do CLW2']),
            $paciente->id,
            $medico->id,
            [],
            Produto::all()->keyBy('codigo'),
        ]);

        $anotacoes = $receita->fresh()->anotacoes;
        $this->assertStringContainsString('reduzir concentração', $anotacoes);
        $this->assertStringContainsString('obs do CLW2', $anotacoes);
        $this->assertSame(1, substr_count($anotacoes, '[legado:5150'));
    }

    public function test_apply_exige_dry_run_da_mesma_selecao(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $medico = $this->seedMedico('Dr G', '777', '77777777777');

        $dumps = app(\App\Services\Migration\LegadoIncrementalImporter::class)->listSqlDumps();
        if ($dumps === []) {
            $this->markTestSkipped('Nenhum dump .sql disponível neste ambiente.');
        }

        $this->actingAs($admin)
            ->post(route('tools.importacao-clw2.apply'), [
                'sql_name' => $dumps[0]['name'],
                'medico_ids' => [$medico->id],
                'confirm' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /**
     * @return array{0: Medico, 1: Paciente, 2: Receita, 3: ReceitaItem}
     */
    private function seedReceitaImportada(): array
    {
        $medico = $this->seedMedico('Dr Receita', '888', '88888888888');
        $paciente = Paciente::create(['nome' => 'Beltrana', 'medico_id' => $medico->id, 'ativo' => true]);
        $produto = Produto::create(['codigo' => 'X1', 'nome' => 'X1', 'ativo' => true]);

        $receita = Receita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'data_receita' => '2026-01-10',
            'numero' => $paciente->id.'-0001',
            'status' => 'aberta',
            'ativo' => true,
            'legado_id' => 5150,
            'numero_origem' => '1',
            'origem' => 'clw2_importada',
            'subtotal' => 10,
            'valor_total' => 10,
            'anotacoes' => '[legado:5150|num:1]',
        ]);

        $item = ReceitaItem::create([
            'receita_id' => $receita->id,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'valor_total' => 10,
            'local_uso' => 'ROSTO',
            'ordem' => 1,
            'grupo' => 'recomendado',
        ]);

        // Estado "importado e nunca editado no CLW3".
        Receita::where('id', $receita->id)->update([
            'created_at' => '2026-06-16 07:00:00',
            'updated_at' => '2026-06-16 07:00:00',
        ]);
        $receita->refresh();

        return [$medico, $paciente, $receita, $item];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function itemLegadoReceita(array $overrides = []): array
    {
        return [
            'legado_id' => 5150,
            'numero_legado' => '1',
            'data_receita' => '2026-01-10',
            'status' => 'aberta',
            // dump mais novo que o CLW3 → entra no caminho de refresh
            'dta_ult_alteracao' => '2026-08-01 10:00:00',
            'subtotal' => 10 * ($overrides['quantidade'] ?? 1),
            'valor_total' => 10 * ($overrides['quantidade'] ?? 1),
            'anotacoes' => $overrides['anotacoes_receita'] ?? null,
            'itens' => [[
                'codigo_produto_legado' => 'X1',
                'codigo_produto_mapeado' => 'X1',
                'quantidade' => $overrides['quantidade'] ?? 1,
                'valor_unitario' => 10,
                'local_uso' => $overrides['local_uso'] ?? 'ROSTO',
            ]],
        ];
    }
}
