<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\RdStationCrmClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RdStationIntegrationController extends Controller
{
    protected function isConfigured(): bool
    {
        $clientId = Setting::get('rd_client_id');
        $clientSecret = Setting::get('rd_client_secret');
        return !empty($clientId) && !empty($clientSecret);
    }

    public function getAuthorizationUrl()
    {
        if (!$this->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID ou Client Secret não configurados.',
            ], 400);
        }

        try {
            $client = new RdStationCrmClient();
            $redirectUri = url('/integracoes/rd-station/callback');
            $authUrl = $client->gerarUrlAutorizacao($redirectUri);

            Log::info('RD Station CRM: Gerando URL de autorização', [
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

    public function callback(Request $request)
    {
        $code = $request->get('code');
        $error = $request->get('error');

        if ($error) {
            return redirect()->route('settings.index')
                ->with('error', 'Erro na autorização RD Station: ' . $error);
        }

        if (!$code) {
            return redirect()->route('settings.index')
                ->with('error', 'Código de autorização não recebido do RD Station.');
        }

        try {
            $client = new RdStationCrmClient();
            $redirectUri = url('/integracoes/rd-station/callback');
            $result = $client->trocarCodigoPorTokens($code, $redirectUri);

            if ($result['status'] === 'success') {
                return redirect()->route('settings.index')
                    ->with('success', 'RD Station CRM autenticado com sucesso! A integração está pronta para uso.');
            }

            return redirect()->route('settings.index')
                ->with('error', $result['message'] ?? 'Erro ao completar autenticação com RD Station.');
        } catch (\Exception $e) {
            Log::error('RD Station CRM: Erro no callback OAuth2', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('settings.index')
                ->with('error', 'Erro ao processar autorização RD Station: ' . $e->getMessage());
        }
    }

    public function testConnection()
    {
        if (!$this->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID ou Client Secret não configurados.',
            ], 400);
        }

        try {
            $client = new RdStationCrmClient();
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

    public function disconnect()
    {
        Setting::set('rd_refresh_token', null);
        \Illuminate\Support\Facades\Cache::forget('rd_access_token');

        Log::info('RD Station CRM: Desconectado manualmente');

        return response()->json([
            'success' => true,
            'message' => 'Desconectado do RD Station CRM com sucesso.',
        ]);
    }
}
