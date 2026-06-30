<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookRdJob;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookRdController extends Controller
{
    public function crmDealUpdated(Request $request): JsonResponse
    {
        if (! $this->validarSecret($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Não autorizado',
            ], 401);
        }

        $payload = $this->normalizarPayload($request->all());
        $eventName = (string) ($payload['event_name'] ?? '');
        $document = $payload['document'] ?? [];
        $dealId = isset($document['id']) ? (string) $document['id'] : null;
        $status = isset($document['status']) ? (string) $document['status'] : null;
        $transactionUuid = (string) ($payload['transaction_uuid'] ?? '');

        Log::info('RD Station CRM: Webhook recebido', [
            'event_name' => $eventName,
            'deal_id' => $dealId,
            'status' => $status,
            'transaction_uuid' => $transactionUuid,
        ]);

        if ($eventName !== 'crm_deal_updated') {
            return response()->json([
                'success' => true,
                'message' => 'Evento ignorado',
            ]);
        }

        if (! $dealId) {
            Log::warning('RD Station CRM: Webhook sem ID da negociação', [
                'payload' => $payload,
            ]);

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
