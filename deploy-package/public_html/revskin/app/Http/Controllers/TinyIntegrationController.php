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
        
        return Inertia::render('Settings/Integrations/Tiny', [
            'settings' => [
                'enabled' => (bool) ($settings['tiny_enabled'] ?? false),
                'has_client_id' => !empty($settings['tiny_client_id'] ?? null),
                'has_client_secret' => !empty($settings['tiny_client_secret'] ?? null),
                'has_refresh_token' => $hasRefreshToken,
                'url_base' => $settings['tiny_url_base'] ?? 'https://api.tiny.com.br/public-api/v3',
                'last_sync' => $settings['tiny_produtos_last_sync'] ?? null,
            ],
            'isConfigured' => $this->isConfigured(),
            'isAuthenticated' => $hasRefreshToken,
        ]);
    }

    /**
     * Update integration settings.
     */
    public function updateSettings(Request $request)
    {
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
            return redirect()->route('tiny.settings')
                ->with('error', 'Erro na autorização: ' . $error);
        }

        if (!$code) {
            return redirect()->route('tiny.settings')
                ->with('error', 'Código de autorização não recebido.');
        }

        try {
            $client = new TinyErpClient();
            // Usar a mesma URL que foi usada na autorização
            $redirectUri = url('/integracoes/tiny/callback');
            $result = $client->trocarCodigoPorTokens($code, $redirectUri);

            if ($result['status'] === 'success') {
                return redirect()->route('tiny.settings')
                    ->with('success', 'Autenticação realizada com sucesso! A integração está pronta para uso.');
            }

            return redirect()->route('tiny.settings')
                ->with('error', $result['message'] ?? 'Erro ao completar autenticação.');
        } catch (\Exception $e) {
            Log::error('Tiny ERP: Erro no callback OAuth2', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('tiny.settings')
                ->with('error', 'Erro ao processar autorização: ' . $e->getMessage());
        }
    }

    /**
     * Test connection.
     */
    public function testConnection()
    {
        if (!$this->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID ou Client Secret não configurados.',
            ], 400);
        }

        try {
            $client = new TinyErpClient();
            $result = $client->obterInfo();

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexão com Tiny ERP estabelecida com sucesso!',
                    'data' => $result['data'] ?? null,
                ]);
            }

            $requiresAuth = $result['requires_auth'] ?? false;

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Erro ao conectar com Tiny ERP',
                'requires_auth' => $requiresAuth,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sync products from Tiny (manual trigger).
     */
    public function syncProdutos()
    {
        SyncProdutosTinyJob::dispatch();

        return response()->json([
            'success' => true,
            'message' => 'Sincronização de produtos iniciada em background',
        ]);
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










