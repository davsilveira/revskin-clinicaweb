<?php

namespace Tests\Unit;

use App\Jobs\CancelarPedidoTinyJob;
use App\Jobs\PullPacientesTinyJob;
use App\Jobs\SyncClienteTinyJob;
use App\Jobs\SyncProdutosTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Services\IntegrationJobFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationJobFingerprintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_options_lists_only_active_filter_jobs(): void
    {
        $options = IntegrationJobFingerprint::jobOptions();
        $labels = array_column($options, 'label');

        $this->assertCount(8, $options);
        $this->assertContains('Criar negociação', $labels);
        $this->assertContains('Marcar negociação perdida', $labels);
        $this->assertContains('Webhook negociação RD', $labels);
        $this->assertContains('Criar pedido', $labels);
        $this->assertContains('Cancelar pedido', $labels);
        $this->assertContains('Sync cliente', $labels);
        $this->assertContains('Sync produtos', $labels);
        $this->assertContains('Importar pacientes', $labels);
        $this->assertNotContains('Sync venda', $labels);
        $this->assertNotContains('Webhook pedido', $labels);
    }

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

    #[Test]
    public function parse_payload_extracts_paciente_id_without_restoring_model(): void
    {
        $command = 'O:27:"App\Jobs\SyncClienteTinyJob":3:{s:8:"paciente";O:45:"Illuminate\Contracts\Database\ModelIdentifier":5:{s:5:"class";s:19:"App\Models\Paciente";s:2:"id";i:99999;s:9:"relations";a:0:{}s:10:"connection";s:5:"mysql";s:15:"collectionClass";N;}s:5:"queue";s:9:"tiny-sync";s:5:"delay";N;}';

        $payload = json_encode([
            'displayName' => SyncClienteTinyJob::class,
            'data' => ['command' => $command],
        ], JSON_THROW_ON_ERROR);

        $parsed = IntegrationJobFingerprint::parsePayload($payload);
        $this->assertNotNull($parsed);
        $this->assertSame(SyncClienteTinyJob::class, $parsed['class']);
        $this->assertSame(99999, IntegrationJobFingerprint::describe(
            $parsed['class'],
            $parsed['instance']
        )['paciente_id']);
    }

    #[Test]
    public function parse_payload_extracts_receita_id_for_cancelar_pedido_tiny_job(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Integração']);
        $paciente = Paciente::create(['nome' => 'Paciente Integração', 'medico_id' => $medico->id]);
        $receita = Receita::create([
            'numero' => '1-0099',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'ativo' => true,
        ]);

        $payload = $this->wrapPayload(new CancelarPedidoTinyJob($receita), CancelarPedidoTinyJob::class);

        $parsed = IntegrationJobFingerprint::parsePayload($payload);
        $this->assertNotNull($parsed);
        $this->assertSame(CancelarPedidoTinyJob::class, $parsed['class']);
        $this->assertSame($receita->id, IntegrationJobFingerprint::describe(
            $parsed['class'],
            $parsed['instance']
        )['receita_id']);
    }

    private function wrapPayload(object $instance, string $class): string
    {
        return json_encode([
            'displayName' => $class,
            'data' => ['command' => serialize($instance)],
        ], JSON_THROW_ON_ERROR);
    }
}
