<?php

namespace Tests\Unit;

use App\Jobs\PullPacientesTinyJob;
use App\Jobs\SyncProdutosTinyJob;
use App\Services\IntegrationJobFingerprint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationJobFingerprintTest extends TestCase
{
    #[Test]
    public function singleton_tiny_sync_jobs_produce_stable_fingerprints(): void
    {
        $job = new SyncProdutosTinyJob;
        $payload = $this->wrapPayload($job, SyncProdutosTinyJob::class);
        $a = IntegrationJobFingerprint::fromFailedJobPayload($payload);
        $this->assertNotNull($a);
        $this->assertSame(SyncProdutosTinyJob::class, $a['class']);

        $b = new SyncProdutosTinyJob;
        $payloadB = $this->wrapPayload($b, SyncProdutosTinyJob::class);
        $a2 = IntegrationJobFingerprint::fromFailedJobPayload($payloadB);
        $this->assertSame($a['fingerprint'], $a2['fingerprint']);
    }

    #[Test]
    public function pull_pacientes_is_distinct_from_sync_produtos(): void
    {
        $p1 = IntegrationJobFingerprint::fromFailedJobPayload(
            $this->wrapPayload(new SyncProdutosTinyJob, SyncProdutosTinyJob::class)
        );
        $p2 = IntegrationJobFingerprint::fromFailedJobPayload(
            $this->wrapPayload(new PullPacientesTinyJob, PullPacientesTinyJob::class)
        );
        $this->assertNotNull($p1);
        $this->assertNotNull($p2);
        $this->assertNotSame($p1['fingerprint'], $p2['fingerprint']);
    }

    #[Test]
    public function unknown_job_class_returns_null(): void
    {
        $this->assertNull(IntegrationJobFingerprint::fromFailedJobPayload(json_encode([
            'displayName' => 'App\\Jobs\\ProcessExportJob',
            'data' => ['command' => 'O:0:"":0:{}'], // nunca alcançado: filtro por displayName
        ])));
    }

    private function wrapPayload(object $instance, string $class): string
    {
        return json_encode([
            'displayName' => $class,
            'data' => ['command' => serialize($instance)],
        ], JSON_THROW_ON_ERROR);
    }
}
