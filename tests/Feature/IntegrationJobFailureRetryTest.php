<?php

namespace Tests\Feature;

use App\Jobs\SyncProdutosTinyJob;
use App\Models\IntegrationJobFailureState;
use App\Services\IntegrationJobFailureRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationJobFailureRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function ingest_creates_state_from_failed_row(): void
    {
        $this->insertFailedJob(new SyncProdutosTinyJob);
        (new IntegrationJobFailureRetryService(false))->ingestFromFailedJobs();

        $this->assertDatabaseCount('integration_job_failure_states', 1);
        $s = IntegrationJobFailureState::first();
        $this->assertSame(3, (int) $s->fast_retries_left);
        $this->assertSame(1, (int) $s->delayed_retry_left);
        $this->assertFalse($s->exhausted);
    }

    #[Test]
    public function due_retry_dispatches_to_jobs_table(): void
    {
        $t0 = Carbon::parse('2026-01-15 10:00:00');
        Carbon::setTestNow($t0);

        $job = new SyncProdutosTinyJob;
        $this->insertFailedJob($job);

        (new IntegrationJobFailureRetryService(false))->ingestFromFailedJobs();
        $state = IntegrationJobFailureState::first();
        $this->assertNotNull($state);
        $this->assertSame(
            $t0->copy()->addMinutes(5)->format('Y-m-d H:i:s'),
            $state->next_retry_at->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow($t0->copy()->addMinutes(6));
        (new IntegrationJobFailureRetryService(false))->dispatchDueRetries();

        $this->assertDatabaseCount('jobs', 1);
        $state->refresh();
        $this->assertTrue($state->in_flight);
        $this->assertSame(2, (int) $state->fast_retries_left);
    }

    private function insertFailedJob(SyncProdutosTinyJob $job): void
    {
        $uuid = (string) str()->uuid();
        $inner = [
            'uuid' => $uuid,
            'displayName' => SyncProdutosTinyJob::class,
            'data' => ['command' => serialize($job)],
        ];
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'tiny-sync',
            'payload' => json_encode($inner, JSON_THROW_ON_ERROR),
            'exception' => 'test',
            'failed_at' => now(),
        ]);
    }
}
