<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TinyErpClient
{
    protected string $baseUrl = 'https://api.tiny.com.br/public-api/v3';
    protected string $tokenUrl = 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token';
    protected ?string $accessToken = null;
    protected ?string $lastError = null;

    /**
     * Get OAuth2 access token using Refresh Token Flow (preferred) or Authorization Code Flow
     */
    protected function obterAccessToken(): ?string
    {
        // Verificar se já temos token em cache
        $cachedToken = Cache::get('tiny_access_token');
        if ($cachedToken) {
            $this->accessToken = $cachedToken;
            return $cachedToken;
        }

        // Tentar usar refresh token primeiro (se disponível)
        $refreshToken = Setting::get('tiny_refresh_token');
        if ($refreshToken) {
            try {
                $decryptedRefreshToken = decrypt($refreshToken);
                $token = $this->renovarAccessTokenComRefreshToken($decryptedRefreshToken);
                if ($token) {
                    return $token;
                }
            } catch (\Exception $e) {
                Log::warning('Tiny ERP: Erro ao usar refresh token, tentando autenticação inicial', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Se não tem refresh token, precisa fazer autenticação inicial
        $this->lastError = 'Refresh token não encontrado. É necessário fazer autenticação inicial. Acesse Configurações > Integrações > Tiny ERP e clique em "Autorizar Aplicativo".';
        Log::warning('Tiny ERP: Refresh token não encontrado, autenticação inicial necessária');
        
        return null;
    }

    /**
     * Renovar access token usando refresh token
     */
    protected function renovarAccessTokenComRefreshToken(string $refreshToken): ?string
    {
        $clientId = Setting::get('tiny_client_id');
        $clientSecret = Setting::get('tiny_client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('Tiny ERP: Client ID ou Client Secret não configurados');
            return null;
        }

        // Descriptografar Client Secret se estiver criptografado
        try {
            $clientSecret = decrypt($clientSecret);
        } catch (\Exception $e) {
            // Se não conseguir descriptografar, usar como está
        }

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->tokenUrl, [
                    'grant_type' => 'refresh_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'] ?? null;
                $newRefreshToken = $data['refresh_token'] ?? $refreshToken; // Manter refresh token se não vier novo
                $expiresIn = $data['expires_in'] ?? 14400; // 4 horas conforme documentação

                if ($token) {
                    // Armazenar access token em cache
                    Cache::put('tiny_access_token', $token, now()->addSeconds($expiresIn - 300)); // -5 minutos de margem
                    
                    // Atualizar refresh token se vier um novo
                    if ($newRefreshToken !== $refreshToken) {
                        Setting::set('tiny_refresh_token', encrypt($newRefreshToken));
                    }
                    
                    $this->accessToken = $token;
                    
                    Log::info('Tiny ERP: Access token renovado com refresh token', [
                        'expires_in' => $expiresIn,
                    ]);
                    
                    return $token;
                }
            }

            // Se refresh token expirou ou é inválido, limpar
            if ($response->status() === 400 || $response->status() === 401) {
                Log::warning('Tiny ERP: Refresh token inválido ou expirado', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                Setting::set('tiny_refresh_token', null);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Tiny ERP: Exceção ao renovar access token', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Trocar código de autorização por access token e refresh token
     */
    public function trocarCodigoPorTokens(string $authorizationCode, string $redirectUri): array
    {
        $clientId = Setting::get('tiny_client_id');
        $clientSecret = Setting::get('tiny_client_secret');

        if (!$clientId || !$clientSecret) {
            return [
                'status' => 'error',
                'message' => 'Client ID ou Client Secret não configurados',
            ];
        }

        // Descriptografar Client Secret se estiver criptografado
        try {
            $clientSecret = decrypt($clientSecret);
        } catch (\Exception $e) {
            // Se não conseguir descriptografar, usar como está
        }

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->tokenUrl, [
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'code' => $authorizationCode,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'] ?? null;
                $refreshToken = $data['refresh_token'] ?? null;
                $expiresIn = $data['expires_in'] ?? 14400; // 4 horas

                if ($accessToken && $refreshToken) {
                    // Armazenar tokens
                    Cache::put('tiny_access_token', $accessToken, now()->addSeconds($expiresIn - 300));
                    Setting::set('tiny_refresh_token', encrypt($refreshToken));

                    Log::info('Tiny ERP: Tokens obtidos com sucesso via authorization code');

                    return [
                        'status' => 'success',
                        'message' => 'Autenticação realizada com sucesso!',
                    ];
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? 'Erro ao trocar código por tokens';

            Log::error('Tiny ERP: Erro ao trocar código por tokens', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'message' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Tiny ERP: Exceção ao trocar código por tokens', [
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Gerar URL de autorização OAuth2
     */
    public function gerarUrlAutorizacao(string $redirectUri): string
    {
        $clientId = Setting::get('tiny_client_id');
        $authUrl = 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth';

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'openid',
            'response_type' => 'code',
        ]);

        return $authUrl . '?' . $params;
    }

    /**
     * Make authenticated request to Tiny API v3
     */
    public function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->obterAccessToken();

        if (!$token) {
            $errorMessage = $this->lastError ?? 'Não foi possível obter access token. É necessário fazer autenticação inicial.';
            
            return [
                'status' => 'error',
                'message' => $errorMessage,
                'requires_auth' => true,
            ];
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->{strtolower($method)}($url, $method === 'GET' ? [] : $data);

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'data' => $response->json(),
                ];
            }

            // Se token expirou, tentar renovar uma vez
            if ($response->status() === 401) {
                Cache::forget('tiny_access_token');
                $token = $this->obterAccessToken();

                if ($token) {
                    $response = Http::timeout(30)
                        ->withToken($token)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])
                        ->{strtolower($method)}($url, $method === 'GET' ? [] : $data);
                }
            }

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'data' => $response->json(),
                ];
            }

            Log::error('Tiny ERP API Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Erro na comunicação com Tiny ERP',
                'status_code' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Tiny ERP API Exception', [
                'method' => $method,
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List products
     */
    public function listarProdutos(array $filters = []): array
    {
        $queryParams = http_build_query($filters);
        $endpoint = 'produtos' . ($queryParams ? '?' . $queryParams : '');
        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get product by ID
     */
    public function obterProduto(int $id): array
    {
        return $this->makeRequest('GET', "produtos/{$id}");
    }

    /**
     * List contacts
     */
    public function listarContatos(array $filters = []): array
    {
        $queryParams = http_build_query($filters);
        $endpoint = 'contatos' . ($queryParams ? '?' . $queryParams : '');
        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get contact by ID
     */
    public function obterContato(int $id): array
    {
        return $this->makeRequest('GET', "contatos/{$id}");
    }

    /**
     * Create contact
     */
    public function criarContato(array $data): array
    {
        return $this->makeRequest('POST', 'contatos', $data);
    }

    /**
     * Update contact
     */
    public function atualizarContato(int $id, array $data): array
    {
        return $this->makeRequest('PUT', "contatos/{$id}", $data);
    }

    /**
     * List orders
     */
    public function listarPedidos(array $filters = []): array
    {
        $queryParams = http_build_query($filters);
        $endpoint = 'pedidos' . ($queryParams ? '?' . $queryParams : '');
        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get order by ID
     */
    public function obterPedido(int $id): array
    {
        return $this->makeRequest('GET', "pedidos/{$id}");
    }

    /**
     * Create order
     */
    public function criarPedido(array $data): array
    {
        return $this->makeRequest('POST', 'pedidos', $data);
    }

    /**
     * Update order
     */
    public function atualizarPedido(int $id, array $data): array
    {
        return $this->makeRequest('PUT', "pedidos/{$id}", $data);
    }

    /**
     * Update order status
     */
    public function atualizarSituacaoPedido(int $id, string $situacao): array
    {
        return $this->makeRequest('PUT', "pedidos/{$id}/situacao", [
            'situacao' => $situacao,
        ]);
    }

    /**
     * Get account info (test connection)
     */
    public function obterInfo(): array
    {
        return $this->makeRequest('GET', 'info');
    }
}
