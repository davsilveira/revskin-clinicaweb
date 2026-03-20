<?php

namespace App\Http\Controllers;

use App\Jobs\SyncClienteTinyJob;
use App\Jobs\SyncProdutosTinyJob;
use App\Models\Paciente;
use App\Models\Setting;
use App\Services\TinyErpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TinyIntegrationController extends Controller
{
    /**
     * Check if integration is configured.
     */
    protected function isConfigured(): bool
    {
        $apiVersion = Setting::get('tiny_api_version', 'v3');
        if ($apiVersion === 'v2') {
            return !empty(Setting::get('tiny_token'));
        }
        $clientId = Setting::get('tiny_client_id');
        $clientSecret = Setting::get('tiny_client_secret');
        return !empty($clientId) && !empty($clientSecret);
    }

    /**
     * Show integration settings.
     */
    public function settings(): Response
    {
        $settings = Setting::getSettings();
        $hasRefreshToken = !empty($settings['tiny_refresh_token'] ?? null);
        $apiVersion = $settings['tiny_api_version'] ?? 'v3';
        $isV2 = $apiVersion === 'v2';
        
        return Inertia::render('Settings/Integrations/Tiny', [
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
            'isConfigured' => $this->isConfigured(),
            'isAuthenticated' => $isV2 ? !empty($settings['tiny_token'] ?? null) : $hasRefreshToken,
        ]);
    }

    /**
     * Update integration settings.
     */
    public function updateSettings(Request $request)
    {
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

        return back()->with('success', 'Configurações do Tiny atualizadas!');
    }

    /**
     * Get authorization URL for OAuth2 flow
     */
    public function getAuthorizationUrl()
    {
        if (!$this->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID ou Client Secret não configurados.',
            ], 400);
        }

        try {
            $client = new TinyErpClient();
            // URL de callback - deve corresponder exatamente ao configurado no app do Tiny
            // Para produção: https://clinicaweb.revskin.com.br/integracoes/tiny/callback
            // Para desenvolvimento: usar url() que detecta automaticamente
            $redirectUri = url('/integracoes/tiny/callback');
            $authUrl = $client->gerarUrlAutorizacao($redirectUri);
            
            Log::info('Tiny ERP: Gerando URL de autorização', [
                'redirect_uri' => $redirectUri,
            ]);

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar URL de autorização: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle OAuth2 callback
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        $error = $request->get('error');

        if ($error) {
            return redirect()->route('settings.index')
                ->with('error', 'Erro na autorização: ' . $error);
        }

        if (!$code) {
            return redirect()->route('settings.index')
                ->with('error', 'Código de autorização não recebido.');
        }

        try {
            $client = new TinyErpClient();
            $redirectUri = url('/integracoes/tiny/callback');
            $result = $client->trocarCodigoPorTokens($code, $redirectUri);

            if ($result['status'] === 'success') {
                return redirect()->route('settings.index')
                    ->with('success', 'Autenticação realizada com sucesso! A integração está pronta para uso.');
            }

            return redirect()->route('settings.index')
                ->with('error', $result['message'] ?? 'Erro ao completar autenticação.');
        } catch (\Exception $e) {
            Log::error('Tiny ERP: Erro no callback OAuth2', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('settings.index')
                ->with('error', 'Erro ao processar autorização: ' . $e->getMessage());
        }
    }

    /**
     * Test connection.
     */
    public function testConnection()
    {
        if (!$this->isConfigured()) {
            $apiVersion = Setting::get('tiny_api_version', 'v3');
            $message = $apiVersion === 'v2'
                ? 'Token da API V2 não configurado.'
                : 'Client ID ou Client Secret não configurados.';
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 400);
        }

        try {
            $client = new TinyErpClient();
            $apiVersion = Setting::get('tiny_api_version', 'v3');
            $result = $client->obterInfo();

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => "Conexão com Tiny ERP estabelecida com sucesso! (API {$apiVersion})",
                    'data' => $result['data'] ?? null,
                ]);
            }

            Log::info('Tiny ERP: /info falhou, tentando /produtos como fallback');
            $fallbackParams = $apiVersion === 'v2' ? ['pesquisa' => ''] : ['limit' => 1];
            $fallback = $client->listarProdutos($fallbackParams);

            if ($fallback['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => "Conexão com Tiny ERP estabelecida! (API {$apiVersion}, /info sem permissão, mas /produtos funciona)",
                    'data' => ['produtos_count' => count($fallback['data']['itens'] ?? [])],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Erro ao conectar com Tiny ERP',
                'requires_auth' => $result['requires_auth'] ?? false,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Disconnect: clear refresh token and cached access token.
     */
    public function disconnect()
    {
        Setting::set('tiny_refresh_token', null);
        \Illuminate\Support\Facades\Cache::forget('tiny_access_token');

        Log::info('Tiny ERP: Desconectado manualmente');

        return response()->json([
            'success' => true,
            'message' => 'Desconectado com sucesso.',
        ]);
    }

    /**
     * Sync products from Tiny (manual trigger).
     * Runs synchronously so user sees result immediately (no queue worker needed).
     */
    public function syncProdutos()
    {
        try {
            SyncProdutosTinyJob::dispatchSync();

            return response()->json([
                'success' => true,
                'message' => 'Sincronização de produtos concluída!',
            ]);
        } catch (\Throwable $e) {
            Log::error('Tiny ERP: Erro na sincronização manual de produtos', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync cliente to Tiny (manual trigger).
     */
    public function syncCliente(Paciente $paciente)
    {
        SyncClienteTinyJob::dispatch($paciente);

        return response()->json([
            'success' => true,
            'message' => 'Sincronização de cliente iniciada em background',
        ]);
    }

    /**
     * Get pedidos from Tiny.
     */
    public function listarPedidos(Request $request)
    {
        try {
            $client = new TinyErpClient();
            
            $filters = [];
            if ($request->has('data_inicio')) {
                $filters['data_inicial'] = $request->get('data_inicio');
            }
            if ($request->has('data_fim')) {
                $filters['data_final'] = $request->get('data_fim');
            }

            $result = $client->listarPedidos($filters);

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'pedidos' => $result['data']['pedidos'] ?? [],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Erro ao listar pedidos',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar pedidos: ' . $e->getMessage(),
            ], 400);
        }
    }
}










