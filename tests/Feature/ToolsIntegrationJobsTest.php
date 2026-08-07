<?php

namespace Tests\Feature;

use App\Jobs\CriarNegociacaoRdStationJob;
use App\Jobs\PullPacientesTinyJob;
use App\Jobs\SyncClienteTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolsIntegrationJobsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        return User::create([
            'name' => 'Admin Integrações',
            'email' => 'admin-integracoes-test@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function makeMedicoUser(): User
    {
        return User::create([
            'name' => 'Medico',
            'email' => 'medico-integracoes-test@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function index_defaults_to_last_seven_days_filter(): void
    {
        $this->actingAs($this->makeAdminUser())
            ->get(route('tools.integracoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.days', 7)
                ->where('filters.queue', null)
                ->where('filters.job', null)
                ->where('filters.paciente', null));
    }

    #[Test]
    public function failed_jobs_outside_selected_period_are_excluded(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Antigo']);
        $paciente = Paciente::create(['nome' => 'Paciente Antigo', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '0-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
        ]);

        $job = new CriarNegociacaoRdStationJob($receita);
        $uuid = (string) str()->uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'rd-sync',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => CriarNegociacaoRdStationJob::class,
                'data' => ['command' => serialize($job)],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Exception: erro antigo',
            'failed_at' => now()->subDays(10),
        ]);

        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->get(route('tools.integracoes.index', ['days' => 7]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('failed', 0));

        $this->actingAs($user)
            ->get(route('tools.integracoes.index', ['days' => 15]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('failed', 1));
    }

    #[Test]
    public function failed_jobs_can_be_filtered_by_paciente_name(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Filtro']);
        $pacienteA = Paciente::create(['nome' => 'Ana Silva', 'medico_id' => $medico->id]);
        $pacienteB = Paciente::create(['nome' => 'Bruno Costa', 'medico_id' => $medico->id]);

        foreach ([$pacienteA, $pacienteB] as $paciente) {
            $receita = Receita::create([
                'numero' => '1-'.str_pad((string) $paciente->id, 4, '0', STR_PAD_LEFT),
                'data_receita' => now()->toDateString(),
                'paciente_id' => $paciente->id,
                'medico_id' => $medico->id,
                'status' => 'finalizada',
            ]);

            $job = new CriarNegociacaoRdStationJob($receita);
            $uuid = (string) str()->uuid();
            DB::table('failed_jobs')->insert([
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => 'rd-sync',
                'payload' => json_encode([
                    'uuid' => $uuid,
                    'displayName' => CriarNegociacaoRdStationJob::class,
                    'data' => ['command' => serialize($job)],
                ], JSON_THROW_ON_ERROR),
                'exception' => 'Exception: erro filtro',
                'failed_at' => now(),
            ]);
        }

        $this->actingAs($this->makeAdminUser())
            ->get(route('tools.integracoes.index', ['days' => 7, 'paciente' => 'Ana']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('failed', 1)
                ->where('failed.0.paciente_nome', 'Ana Silva'));
    }

    #[Test]
    public function failed_jobs_can_be_filtered_by_job_type(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Job']);
        $paciente = Paciente::create(['nome' => 'Paciente Job', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '3-0003',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
        ]);

        $rdJob = new CriarNegociacaoRdStationJob($receita);
        $rdUuid = (string) str()->uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $rdUuid,
            'connection' => 'database',
            'queue' => 'rd-sync',
            'payload' => json_encode([
                'uuid' => $rdUuid,
                'displayName' => CriarNegociacaoRdStationJob::class,
                'data' => ['command' => serialize($rdJob)],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Exception: rd',
            'failed_at' => now(),
        ]);

        $tinyJob = new SyncClienteTinyJob($paciente);
        $tinyUuid = (string) str()->uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $tinyUuid,
            'connection' => 'database',
            'queue' => 'tiny-sync',
            'payload' => json_encode([
                'uuid' => $tinyUuid,
                'displayName' => SyncClienteTinyJob::class,
                'data' => ['command' => serialize($tinyJob)],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Exception: tiny',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->makeAdminUser())
            ->get(route('tools.integracoes.index', [
                'days' => 7,
                'job' => 'SyncClienteTinyJob',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('failed', 1)
                ->where('failed.0.job_label', 'Sync cliente'));
    }

    #[Test]
    public function pending_jobs_are_paginated(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Pagina']);
        $paciente = Paciente::create(['nome' => 'Paciente Pagina', 'medico_id' => $medico->id]);

        for ($i = 0; $i < 55; $i++) {
            $job = new SyncClienteTinyJob($paciente);
            DB::table('jobs')->insert([
                'queue' => 'tiny-sync',
                'payload' => json_encode([
                    'uuid' => (string) str()->uuid(),
                    'displayName' => SyncClienteTinyJob::class,
                    'data' => ['command' => serialize($job)],
                ], JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ]);
        }

        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->get(route('tools.integracoes.index', ['days' => 7, 'tab' => 'pending', 'pending_page' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pending', 50)
                ->where('pendingPagination.total', 55)
                ->where('pendingPagination.current_page', 1)
                ->where('pendingPagination.last_page', 2));

        $this->actingAs($user)
            ->get(route('tools.integracoes.index', ['days' => 7, 'tab' => 'pending', 'pending_page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pending', 5)
                ->where('pendingPagination.current_page', 2));
    }

    #[Test]
    public function admin_can_access_integration_tools_page(): void
    {
        $this->actingAs($this->makeAdminUser())
            ->get(route('tools.integracoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tools/Integracoes'));
    }

    #[Test]
    public function admin_can_access_integration_tools_page_via_inertia_xhr(): void
    {
        $manifest = public_path('build/manifest.json');
        $version = is_file($manifest) ? hash_file('xxh128', $manifest) : '';

        $this->actingAs($this->makeAdminUser())
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get(route('tools.integracoes.index'))
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Tools/Integracoes');
    }

    #[Test]
    public function non_admin_cannot_access_integration_tools_page(): void
    {
        $this->actingAs($this->makeMedicoUser())
            ->get(route('tools.integracoes.index'))
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_inertia_request_receives_error_page(): void
    {
        $manifest = public_path('build/manifest.json');
        $version = is_file($manifest) ? hash_file('xxh128', $manifest) : '';

        $this->actingAs($this->makeMedicoUser())
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get(route('tools.integracoes.index'))
            ->assertForbidden()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 403);
    }

    #[Test]
    public function pending_job_with_deleted_paciente_does_not_cause_404(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Orfao']);
        $paciente = Paciente::create(['nome' => 'Paciente Orfao', 'medico_id' => $medico->id]);
        $pacienteId = $paciente->id;

        $job = new SyncClienteTinyJob($paciente);
        $payload = json_encode([
            'uuid' => (string) str()->uuid(),
            'displayName' => SyncClienteTinyJob::class,
            'data' => ['command' => serialize($job)],
        ], JSON_THROW_ON_ERROR);

        $paciente->delete();

        DB::table('jobs')->insert([
            'queue' => 'tiny-sync',
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->get(route('tools.integracoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tools/Integracoes')
                ->has('pending', 1)
                ->where('pending.0.paciente_id', $pacienteId)
                ->where('pending.0.paciente_nome', null));
    }

    #[Test]
    public function failed_job_payload_shows_receita_context_on_tools_page(): void
    {
        $medico = Medico::create(['nome' => 'Dr. RD']);
        $paciente = Paciente::create(['nome' => 'Paciente RD', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '9-9999',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
        ]);

        $job = new CriarNegociacaoRdStationJob($receita);
        $uuid = (string) str()->uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'rd-sync',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => CriarNegociacaoRdStationJob::class,
                'data' => ['command' => serialize($job)],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Exception: Erro ao criar organização no RD Station',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->makeAdminUser())
            ->get(route('tools.integracoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('failed', 1)
                ->where('failed.0.receita_id', $receita->id)
                ->where('failed.0.receita_numero', '9-9999')
                ->where('failed.0.paciente_nome', 'Paciente RD'));
    }

    #[Test]
    public function admin_can_forget_failed_integration_job(): void
    {
        $job = new PullPacientesTinyJob;
        $uuid = (string) str()->uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'tiny-sync',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => PullPacientesTinyJob::class,
                'data' => ['command' => serialize($job)],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: API Bloqueada',
            'failed_at' => now(),
        ]);

        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->delete(route('tools.integracoes.forget', ['uuid' => $uuid]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);

        $this->actingAs($user)
            ->get(route('tools.integracoes.index', ['days' => 7, 'tab' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('failed', 0));
    }

    #[Test]
    public function forgetting_one_duplicate_fingerprint_failed_job_still_lists_remaining(): void
    {
        $job = new PullPacientesTinyJob;

        $uuids = [];
        // Relativo à data de hoje: com data fixa o teste passou a falhar sozinho quando as
        // falhas saíram da janela de 30 dias consultada abaixo.
        $datasFalha = [
            now()->subDays(3)->format('Y-m-d H:i:s'),
            now()->subDays(2)->format('Y-m-d H:i:s'),
        ];
        foreach ($datasFalha as $failedAt) {
            $uuid = (string) str()->uuid();
            $uuids[] = $uuid;
            DB::table('failed_jobs')->insert([
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => 'tiny-sync',
                'payload' => json_encode([
                    'uuid' => $uuid,
                    'displayName' => PullPacientesTinyJob::class,
                    'data' => ['command' => serialize($job)],
                ], JSON_THROW_ON_ERROR),
                'exception' => 'RuntimeException: API Bloqueada',
                'failed_at' => $failedAt,
            ]);
        }

        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->get(route('tools.integracoes.index', ['days' => 30, 'tab' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('failed', 2)
                ->where('failedPagination.total', 2));

        $this->actingAs($user)
            ->delete(route('tools.integracoes.forget', ['uuid' => $uuids[0]]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('tools.integracoes.index', ['days' => 30, 'tab' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('failed', 1)
                ->where('failed.0.uuid', $uuids[1]));
    }

    #[Test]
    public function admin_can_retry_failed_integration_job(): void
    {
        config(['queue.default' => 'database']);

        $medico = Medico::create(['nome' => 'Dr. Retry']);
        $paciente = Paciente::create(['nome' => 'Paciente Retry', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
        ]);

        $job = new CriarNegociacaoRdStationJob($receita);
        $uuid = (string) str()->uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'rd-sync',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => CriarNegociacaoRdStationJob::class,
                'data' => ['command' => serialize($job)],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Exception: test',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->makeAdminUser())
            ->post(route('tools.integracoes.retry', ['uuid' => $uuid]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('jobs', ['queue' => 'rd-sync']);
    }

    #[Test]
    public function rd_job_skips_when_receita_already_has_rd_deal_id(): void
    {
        Setting::set('rd_enabled', true);
        Setting::set('rd_owner_id', 'owner-test');

        $medico = Medico::create(['nome' => 'Dr. Idempotente']);
        $paciente = Paciente::create(['nome' => 'Paciente Idempotente', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '2-0002',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'rd_deal_id' => 'deal-existing-123',
        ]);

        Http::fake();
        Log::spy();

        (new CriarNegociacaoRdStationJob($receita))->handle();

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')
            ->with('RD Station CRM: Negociação já sincronizada, ignorando', \Mockery::on(function (array $context) use ($receita) {
                return ($context['receita_id'] ?? null) === $receita->id
                    && ($context['rd_deal_id'] ?? null) === 'deal-existing-123';
            }));
    }
}
