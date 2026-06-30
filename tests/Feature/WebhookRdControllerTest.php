<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookRdJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookRdControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('rd_webhook_secret', 'segredo-teste');
    }

    #[Test]
    public function rejects_request_with_invalid_secret(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/webhooks/rd/crm-deal-updated', $this->payload('lost'), [
            'X-RD-Webhook-Secret' => 'errado',
        ]);

        $response->assertUnauthorized();
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function dispatches_job_for_lost_deal_update(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/webhooks/rd/crm-deal-updated', $this->payload('lost'), [
            'X-RD-Webhook-Secret' => 'segredo-teste',
        ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessWebhookRdJob::class, function (ProcessWebhookRdJob $job) {
            return $job->dealId === 'deal-123'
                && $job->status === 'lost'
                && $job->transactionUuid === 'tx-uuid-1';
        });
    }

    #[Test]
    public function ignores_non_deal_updated_events_with_ok(): void
    {
        Bus::fake();

        $payload = $this->payload('lost');
        $payload['event_name'] = 'crm_deal_created';

        $response = $this->postJson('/api/webhooks/rd/crm-deal-updated', $payload, [
            'X-RD-Webhook-Secret' => 'segredo-teste',
        ]);

        $response->assertOk();
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function webhook_job_cancels_receita_when_status_lost(): void
    {
        $receita = $this->makeReceita('deal-456', 'finalizada');

        (new ProcessWebhookRdJob('deal-456', 'lost', 'tx-unique-1'))->handle();

        $receita->refresh();
        $this->assertSame('cancelada', $receita->status);
        $this->assertFalse((bool) $receita->ativo);
    }

    #[Test]
    public function webhook_job_deduplicates_by_transaction_uuid(): void
    {
        $receita = $this->makeReceita('deal-789', 'finalizada');
        Cache::put('rd_webhook_tx:tx-dup', true, now()->addDay());

        (new ProcessWebhookRdJob('deal-789', 'lost', 'tx-dup'))->handle();

        $receita->refresh();
        $this->assertSame('finalizada', $receita->status);
    }

    private function payload(string $status): array
    {
        return [
            'event_name' => 'crm_deal_updated',
            'event_timestamp' => now()->toIso8601String(),
            'transaction_uuid' => 'tx-uuid-1',
            'document' => [
                'id' => 'deal-123',
                'status' => $status,
                'name' => 'Receita #1',
            ],
        ];
    }

    private function makeReceita(string $rdDealId, string $status): Receita
    {
        $medico = Medico::create(['nome' => 'Dr. Webhook']);
        $paciente = Paciente::create(['nome' => 'Paciente Webhook', 'medico_id' => $medico->id]);

        return Receita::create([
            'numero' => '1-0003',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => $status,
            'rd_deal_id' => $rdDealId,
            'ativo' => true,
        ]);
    }
}
