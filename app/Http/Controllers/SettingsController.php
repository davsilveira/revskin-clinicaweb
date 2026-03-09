<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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

    protected function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }
}
