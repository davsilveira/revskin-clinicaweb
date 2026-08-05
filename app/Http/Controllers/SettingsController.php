<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\RdWebhookAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $this->ensureAdmin();

        $settings = Setting::getSettings();

        $hasRefreshToken = !empty($settings['tiny_refresh_token'] ?? null);
        $apiVersion = $settings['tiny_api_version'] ?? 'v3';
        $isV2 = $apiVersion === 'v2';

        $rdHasRefreshToken = !empty($settings['rd_refresh_token'] ?? null);

        return Inertia::render('Settings/Index', [
            'tiny' => [
                'settings' => [
                    'enabled' => (bool) ($settings['tiny_enabled'] ?? false),
                    'api_version' => $apiVersion,
                    'has_client_id' => !empty($settings['tiny_client_id'] ?? null),
                    'has_client_secret' => !empty($settings['tiny_client_secret'] ?? null),
                    'has_refresh_token' => $hasRefreshToken,
                    'has_token' => !empty($settings['tiny_token'] ?? null),
                    'url_base' => $settings['tiny_url_base'] ?? 'https://api.tiny.com.br/public-api/v3',
                    'last_sync' => $settings['tiny_produtos_last_sync'] ?? null,
                ],
                'isAuthenticated' => $isV2 ? !empty($settings['tiny_token'] ?? null) : $hasRefreshToken,
            ],
            'rdstation' => [
                'settings' => [
                    'enabled' => (bool) ($settings['rd_enabled'] ?? false),
                    'has_client_id' => !empty($settings['rd_client_id'] ?? null),
                    'has_client_secret' => !empty($settings['rd_client_secret'] ?? null),
                    'has_refresh_token' => $rdHasRefreshToken,
                    'stage_id' => $settings['rd_stage_id'] ?? '6929f60257dcba001d9b375b',
                    'produto_padrao_id' => $settings['rd_produto_padrao_id'] ?? '69a956705a1a6a00133167dc',
                    'medico_field_id' => $settings['rd_medico_field_id'] ?? '69a955ea78fde3001f6f61dc',
                    'receita_field_id' => $settings['rd_receita_field_id'] ?? '699efc3a13a467001cb81ea1',
                    'cortesia_field_id' => $settings['rd_cortesia_field_id'] ?? '6a721f71257c0d0020d8178e',
                    'owner_id' => $settings['rd_owner_id'] ?? '',
                    'cancelamento_field_id' => $settings['rd_cancelamento_field_id'] ?? '',
                    'cancelamento_field_value' => $settings['rd_cancelamento_field_value'] ?? '',
                    'webhook_secret' => $settings['rd_webhook_secret'] ?? '',
                ],
                'isAuthenticated' => $rdHasRefreshToken,
                'webhook_receipts' => RdWebhookAuditLog::all(),
                'webhook_last_received_at' => RdWebhookAuditLog::lastReceivedAt(),
            ],
        ]);
    }

    public function updateTiny(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'enabled' => 'boolean',
            'api_version' => 'nullable|string|in:v2,v3',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'remove_client_secret' => 'nullable|boolean',
            'token' => 'nullable|string',
            'remove_token' => 'nullable|boolean',
            'url_base' => 'nullable|string|url',
        ]);

        Setting::set('tiny_enabled', $validated['enabled'] ?? false);

        if (!empty($validated['api_version'])) {
            Setting::set('tiny_api_version', $validated['api_version']);
        }
        
        if (!empty($validated['remove_client_secret'])) {
            Setting::set('tiny_client_secret', null);
        } elseif (!empty($validated['client_secret'])) {
            Setting::set('tiny_client_secret', encrypt($validated['client_secret']));
        }

        if (!empty($validated['client_id'])) {
            Setting::set('tiny_client_id', $validated['client_id']);
        }

        if (!empty($validated['remove_token'])) {
            Setting::set('tiny_token', null);
        } elseif (!empty($validated['token'])) {
            Setting::set('tiny_token', encrypt($validated['token']));
        }

        if (!empty($validated['url_base'])) {
            Setting::set('tiny_url_base', $validated['url_base']);
        }

        return back()->with('success', 'Configuracoes salvas com sucesso!');
    }

    public function testTiny()
    {
        $this->ensureAdmin();

        $settings = Setting::getSettings();
        $apiVersion = $settings['tiny_api_version'] ?? 'v3';

        if ($apiVersion === 'v2') {
            if (empty($settings['tiny_token'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token da API V2 não configurado.',
                ], 400);
            }
        } else {
            $clientId = $settings['tiny_client_id'] ?? null;
            $clientSecret = $settings['tiny_client_secret'] ?? null;
            if (!$clientId || !$clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client ID ou Client Secret não configurados.',
                ], 400);
            }
        }

        try {
            $client = new \App\Services\TinyErpClient();
            $result = $client->obterInfo();

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => "Conexão estabelecida com sucesso! (API {$apiVersion})",
                    'data' => $result['data'] ?? null,
                ]);
            }

            $errorMessage = $result['message'] ?? 'Erro desconhecido na API.';

            if (isset($result['status_code']) && $result['status_code'] === 401) {
                $errorMessage = $apiVersion === 'v2'
                    ? 'Token inválido. Verifique o Token API nas configurações do Tiny ERP.'
                    : 'Erro de autenticação. Verifique Client ID e Client Secret.';
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'requires_auth' => $result['requires_auth'] ?? false,
            ], 400);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tiny ERP Test Connection Exception', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function updateRdStation(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'enabled' => 'boolean',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'remove_client_secret' => 'nullable|boolean',
            'stage_id' => 'nullable|string',
            'produto_padrao_id' => 'nullable|string',
            'medico_field_id' => 'nullable|string',
            'receita_field_id' => 'nullable|string',
            'cortesia_field_id' => 'nullable|string',
            'owner_id' => 'nullable|string',
            'cancelamento_field_id' => 'nullable|string',
            'cancelamento_field_value' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
        ]);

        Setting::set('rd_enabled', $validated['enabled'] ?? false);

        if (!empty($validated['client_id'])) {
            Setting::set('rd_client_id', $validated['client_id']);
        }

        if (!empty($validated['remove_client_secret'])) {
            Setting::set('rd_client_secret', null);
        } elseif (!empty($validated['client_secret'])) {
            Setting::set('rd_client_secret', encrypt($validated['client_secret']));
        }

        if (array_key_exists('stage_id', $validated)) {
            Setting::set('rd_stage_id', $validated['stage_id']);
        }
        if (array_key_exists('produto_padrao_id', $validated)) {
            Setting::set('rd_produto_padrao_id', $validated['produto_padrao_id']);
        }
        if (array_key_exists('medico_field_id', $validated)) {
            Setting::set('rd_medico_field_id', $validated['medico_field_id']);
        }
        if (array_key_exists('receita_field_id', $validated)) {
            Setting::set('rd_receita_field_id', $validated['receita_field_id']);
        }
        if (array_key_exists('cortesia_field_id', $validated)) {
            Setting::set('rd_cortesia_field_id', $validated['cortesia_field_id'] ? trim($validated['cortesia_field_id']) : null);
        }
        if (array_key_exists('owner_id', $validated)) {
            Setting::set('rd_owner_id', $validated['owner_id'] ? trim($validated['owner_id']) : null);
        }
        if (array_key_exists('cancelamento_field_id', $validated)) {
            Setting::set('rd_cancelamento_field_id', $validated['cancelamento_field_id'] ? trim($validated['cancelamento_field_id']) : null);
        }
        if (array_key_exists('cancelamento_field_value', $validated)) {
            Setting::set('rd_cancelamento_field_value', $validated['cancelamento_field_value'] !== null && $validated['cancelamento_field_value'] !== ''
                ? trim($validated['cancelamento_field_value'])
                : null);
        }
        if (array_key_exists('webhook_secret', $validated)) {
            Setting::set('rd_webhook_secret', $validated['webhook_secret'] ? trim($validated['webhook_secret']) : null);
        }

        return back()->with('success', 'Configurações do RD Station salvas com sucesso!');
    }

    public function testRdStation()
    {
        $this->ensureAdmin();

        $clientId = Setting::get('rd_client_id');
        $clientSecret = Setting::get('rd_client_secret');

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID ou Client Secret não configurados.',
            ], 400);
        }

        try {
            $client = new \App\Services\RdStationCrmClient();
            $result = $client->obterInfo();

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexão com RD Station CRM estabelecida com sucesso!',
                    'data' => $result['data'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Erro ao conectar com RD Station CRM',
                'requires_auth' => $result['requires_auth'] ?? false,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function rdWebhookLog()
    {
        $this->ensureAdmin();

        return response()->json([
            'receipts' => RdWebhookAuditLog::all(),
            'last_received_at' => RdWebhookAuditLog::lastReceivedAt(),
        ]);
    }

    protected function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }
}
