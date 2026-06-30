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

    #[Test]
    public function dispatches_job_from_autz_proxy_wrapper_payload(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/webhooks/rd/crm-deal-updated', [
            [
                'headers' => ['content-type' => 'application/json'],
                'body' => $this->realWorldPayload('lost'),
                'webhookUrl' => 'https://webhook.autz.com.br/webhook/example',
            ],
        ], [
            'X-RD-Webhook-Secret' => 'segredo-teste',
        ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessWebhookRdJob::class, function (ProcessWebhookRdJob $job) {
            return $job->dealId === '6a4280ac2af4850027e797c8'
                && $job->status === 'lost'
                && $job->transactionUuid === '789c4c43-1652-4936-8ee6-bd27202a8cad';
        });
    }

    #[Test]
    public function dispatches_job_from_body_envelope_payload(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/webhooks/rd/crm-deal-updated', [
            'body' => $this->realWorldPayload('lost'),
        ], [
            'X-RD-Webhook-Secret' => 'segredo-teste',
        ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessWebhookRdJob::class);
    }

    #[Test]
    public function real_world_ongoing_payload_dispatches_job_but_does_not_cancel_receita(): void
    {
        Bus::fake();

        $receita = $this->makeReceita('6a4280ac2af4850027e797c8', 'finalizada');

        $response = $this->postJson('/api/webhooks/rd/crm-deal-updated', $this->realWorldPayload('ongoing'), [
            'X-RD-Webhook-Secret' => 'segredo-teste',
        ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessWebhookRdJob::class);

        (new ProcessWebhookRdJob(
            '6a4280ac2af4850027e797c8',
            'ongoing',
            '789c4c43-ongoing-test',
            $this->realWorldPayload('ongoing')
        ))->handle();

        $receita->refresh();
        $this->assertSame('finalizada', $receita->status);
    }

    #[Test]
    public function real_world_lost_payload_cancels_linked_receita(): void
    {
        $receita = $this->makeReceita('6a4280ac2af4850027e797c8', 'finalizada');

        (new ProcessWebhookRdJob(
            '6a4280ac2af4850027e797c8',
            'lost',
            '789c4c43-lost-test',
            $this->realWorldPayload('lost')
        ))->handle();

        $receita->refresh();
        $this->assertSame('cancelada', $receita->status);
        $this->assertFalse((bool) $receita->ativo);
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

    /**
     * @return array<string, mixed>
     */
    private function realWorldPayload(string $status): array
    {
        return [
            'event_name' => 'crm_deal_updated',
            'event_timestamp' => '2026-06-29T14:33:42.000Z',
            'transaction_uuid' => '789c4c43-1652-4936-8ee6-bd27202a8cad',
            'document' => [
                'id' => '6a4280ac2af4850027e797c8',
                'name' => 'Teste Ignorar',
                'status' => $status,
                'deal_custom_fields' => [
                    [
                        'value' => '',
                        'custom_field' => [
                            'id' => '69a955ea78fde3001f6f61dc',
                            'label' => 'Nome do Médico',
                        ],
                    ],
                    [
                        'value' => 'Cancelada',
                        'custom_field' => [
                            'id' => '6a3d26162a6bb20028631528',
                            'label' => 'Status da Receita',
                        ],
                    ],
                ],
                'deal_lost_reason' => $status === 'lost' ? ['name' => 'Cancelamento'] : [],
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
