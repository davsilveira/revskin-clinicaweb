<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookRdJob;
use App\Models\Setting;
use App\Services\RdWebhookAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookRdController extends Controller
{
    public function crmDealUpdated(Request $request): JsonResponse
    {
        $rawPayload = $request->all();
        $payload = $this->normalizarPayload($rawPayload);
        $document = is_array($payload['document'] ?? null) ? $payload['document'] : [];

        $auditBase = [
            'ip' => $request->ip(),
            'event_name' => (string) ($payload['event_name'] ?? ''),
            'deal_id' => isset($document['id']) ? (string) $document['id'] : null,
            'status' => isset($document['status']) ? (string) $document['status'] : null,
            'transaction_uuid' => (string) ($payload['transaction_uuid'] ?? ''),
            'payload_shape' => $this->descreverFormatoPayload($rawPayload, $payload),
            'has_secret_header' => $request->header('X-RD-Webhook-Secret') !== null,
        ];

        Log::info('RD Station CRM: Webhook endpoint acionado', $auditBase);

        if (! $this->validarSecret($request)) {
            Log::warning('RD Station CRM: Webhook rejeitado — segredo inválido ou ausente', [
                'ip' => $request->ip(),
                'has_secret_header' => $request->header('X-RD-Webhook-Secret') !== null,
                'has_bearer' => str_starts_with((string) $request->header('Authorization', ''), 'Bearer '),
            ]);

            RdWebhookAuditLog::record(array_merge($auditBase, [
                'outcome' => 'rejected_auth',
                'http_status' => 401,
            ]));

            return response()->json([
                'success' => false,
                'message' => 'Não autorizado',
            ], 401);
        }

        $eventName = $auditBase['event_name'];
        $dealId = $auditBase['deal_id'];
        $status = $auditBase['status'];
        $transactionUuid = $auditBase['transaction_uuid'];

        Log::info('RD Station CRM: Webhook recebido', [
            'event_name' => $eventName,
            'deal_id' => $dealId,
            'status' => $status,
            'transaction_uuid' => $transactionUuid,
        ]);

        if ($eventName !== 'crm_deal_updated') {
            RdWebhookAuditLog::record(array_merge($auditBase, [
                'outcome' => 'ignored_event',
                'http_status' => 200,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Evento ignorado',
            ]);
        }

        if (! $dealId) {
            Log::warning('RD Station CRM: Webhook sem ID da negociação', [
                'payload' => $payload,
            ]);

            RdWebhookAuditLog::record(array_merge($auditBase, [
                'outcome' => 'missing_deal_id',
                'http_status' => 400,
            ]));

            return response()->json([
                'success' => false,
                'message' => 'ID da negociação não encontrado no payload',
            ], 400);
        }

        ProcessWebhookRdJob::dispatch(
            $dealId,
            $status ?? '',
            $transactionUuid !== '' ? $transactionUuid : uniqid('rd-', true),
            $payload
        );

        RdWebhookAuditLog::record(array_merge($auditBase, [
            'outcome' => 'dispatched',
            'http_status' => 200,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido e processando',
        ]);
    }

    private function validarSecret(Request $request): bool
    {
        $expected = Setting::get('rd_webhook_secret');
        if ($expected === null || $expected === '') {
            return true;
        }

        $header = $request->header('X-RD-Webhook-Secret');
        if ($header !== null && hash_equals((string) $expected, (string) $header)) {
            return true;
        }

        $authorization = (string) $request->header('Authorization', '');
        if (str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);

            return hash_equals((string) $expected, $token);
        }

        return false;
    }

    /**
     * @param  array<string|int, mixed>  $rawPayload
     * @param  array<string, mixed>  $payload
     */
    private function descreverFormatoPayload(array $rawPayload, array $payload): string
    {
        if (isset($rawPayload[0]) && is_array($rawPayload[0])) {
            return 'wrapper_array';
        }

        if (isset($rawPayload['body']) && is_array($rawPayload['body'])) {
            return 'wrapper_body';
        }

        if ($payload !== $rawPayload) {
            return 'normalized';
        }

        if ($payload === []) {
            return 'empty';
        }

        return 'plain';
    }

    /**
     * Aceita JSON plano do RD ou envelope de proxy (ex.: autz/n8n com chave `body`).
     *
     * @param  array<string|int, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $payload): array
    {
        if (isset($payload[0]) && is_array($payload[0])) {
            $first = $payload[0];
            if (isset($first['body']) && is_array($first['body'])) {
                return $first['body'];
            }
        }

        if (
            isset($payload['body'])
            && is_array($payload['body'])
            && isset($payload['body']['event_name'])
        ) {
            return $payload['body'];
        }

        return $payload;
    }
}
