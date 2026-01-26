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
        
        return Inertia::render('Settings/Index', [
            'tiny' => [
                'settings' => [
                    'enabled' => (bool) ($settings['tiny_enabled'] ?? false),
                    'has_client_id' => !empty($settings['tiny_client_id'] ?? null),
                    'has_client_secret' => !empty($settings['tiny_client_secret'] ?? null),
                    'has_refresh_token' => $hasRefreshToken,
                    'url_base' => $settings['tiny_url_base'] ?? 'https://api.tiny.com.br/public-api/v3',
                    'last_sync' => $settings['tiny_produtos_last_sync'] ?? null,
                ],
                'isAuthenticated' => $hasRefreshToken,
            ],
        ]);
    }

    public function updateTiny(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'enabled' => 'boolean',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'remove_client_secret' => 'nullable|boolean',
            'url_base' => 'nullable|string|url',
        ]);

        Setting::set('tiny_enabled', $validated['enabled'] ?? false);
        
        if (!empty($validated['remove_client_secret'])) {
            Setting::set('tiny_client_secret', null);
        } elseif (!empty($validated['client_secret'])) {
            Setting::set('tiny_client_secret', encrypt($validated['client_secret']));
        }

        if (!empty($validated['client_id'])) {
            Setting::set('tiny_client_id', $validated['client_id']);
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
        $clientId = $settings['tiny_client_id'] ?? null;
        $clientSecret = $settings['tiny_client_secret'] ?? null;
        
        if (!$clientId || !$clientSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID ou Client Secret não configurados.',
            ], 400);
        }

        try {
            // Verificar se client_secret está criptografado
            try {
                decrypt($clientSecret);
                $isEncrypted = true;
            } catch (\Exception $e) {
                $isEncrypted = false;
            }

            $client = new \App\Services\TinyErpClient();
            $result = $client->obterInfo();

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso!',
                    'data' => $result['data'] ?? null,
                ]);
            }

            // Retornar mais detalhes do erro para debug
            $errorMessage = $result['message'] ?? 'Erro desconhecido na API.';
            $errorDetails = [
                'status_code' => $result['status_code'] ?? null,
                'error_data' => $result['data'] ?? null,
            ];

            // Se for erro de autenticação, dar dica mais específica
            if (isset($result['status_code']) && $result['status_code'] === 401) {
                $errorMessage = 'Erro de autenticação. Verifique se Client ID e Client Secret estão corretos e se o app tem as permissões necessárias no Tiny ERP.';
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'details' => $errorDetails,
                'debug' => [
                    'client_id_set' => !empty($clientId),
                    'client_secret_set' => !empty($clientSecret),
                    'client_secret_encrypted' => $isEncrypted,
                ],
            ], 400);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tiny ERP Test Connection Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
