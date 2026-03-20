<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RdStationCrmClient
{
    protected string $baseUrl = 'https://api.rd.services/crm/v2';
    protected string $authUrl = 'https://accounts.rdstation.com/oauth/authorize';
    protected string $tokenUrl = 'https://api.rd.services/oauth2/token';
    protected ?string $accessToken = null;
    protected ?string $lastError = null;

    public function gerarUrlAutorizacao(string $redirectUri): string
    {
        $clientId = Setting::get('rd_client_id');

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ]);

        return $this->authUrl . '?' . $params;
    }

    public function trocarCodigoPorTokens(string $code, string $redirectUri): array
    {
        $clientId = Setting::get('rd_client_id');
        $clientSecret = Setting::get('rd_client_secret');

        if (!$clientId || !$clientSecret) {
            return ['status' => 'error', 'message' => 'Client ID ou Client Secret não configurados'];
        }

        try {
            $clientSecret = decrypt($clientSecret);
        } catch (\Exception $e) {
        }

        try {
            $response = Http::timeout(30)->asForm()->post($this->tokenUrl, [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'] ?? null;
                $refreshToken = $data['refresh_token'] ?? null;
                $expiresIn = $data['expires_in'] ?? 7200;

                if ($accessToken && $refreshToken) {
                    Cache::put('rd_access_token', $accessToken, now()->addSeconds($expiresIn - 300));
                    Setting::set('rd_refresh_token', encrypt($refreshToken));
                    Log::info('RD Station CRM: Tokens obtidos com sucesso via authorization code');
                    return ['status' => 'success', 'message' => 'Autenticação realizada com sucesso!'];
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? 'Erro ao trocar código por tokens';
            Log::error('RD Station CRM: Erro ao trocar código por tokens', ['status' => $response->status()]);
            return ['status' => 'error', 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('RD Station CRM: Exceção ao trocar código por tokens', ['message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── OAuth Token Management ─────────────────────────────────

    protected function obterAccessToken(): ?string
    {
        $cachedToken = Cache::get('rd_access_token');
        if ($cachedToken) {
            $this->accessToken = $cachedToken;
            return $cachedToken;
        }

        $refreshToken = Setting::get('rd_refresh_token');
        if ($refreshToken) {
            try {
                $decryptedRefreshToken = decrypt($refreshToken);
                $token = $this->renovarAccessToken($decryptedRefreshToken);
                if ($token) {
                    return $token;
                }
            } catch (\Exception $e) {
                Log::warning('RD Station CRM: Erro ao usar refresh token', ['message' => $e->getMessage()]);
            }
        }

        $this->lastError = 'Refresh token não encontrado. É necessário autorizar o aplicativo em Configurações > Integrações > RD Station.';
        Log::warning('RD Station CRM: Refresh token não encontrado');
        return null;
    }

    protected function renovarAccessToken(string $refreshToken): ?string
    {
        $clientId = Setting::get('rd_client_id');
        $clientSecret = Setting::get('rd_client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('RD Station CRM: Client ID ou Client Secret não configurados');
            return null;
        }

        try {
            $clientSecret = decrypt($clientSecret);
        } catch (\Exception $e) {
        }

        try {
            $response = Http::timeout(30)->asForm()->post($this->tokenUrl, [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'] ?? null;
                $newRefreshToken = $data['refresh_token'] ?? null;
                $expiresIn = $data['expires_in'] ?? 7200;

                if ($token) {
                    Cache::put('rd_access_token', $token, now()->addSeconds($expiresIn - 300));
                    if ($newRefreshToken) {
                        Setting::set('rd_refresh_token', encrypt($newRefreshToken));
                    }
                    $this->accessToken = $token;
                    Log::info('RD Station CRM: Access token renovado', ['expires_in' => $expiresIn]);
                    return $token;
                }
            }

            if ($response->status() === 400 || $response->status() === 401) {
                Log::warning('RD Station CRM: Refresh token inválido ou expirado', ['status' => $response->status()]);
                Setting::set('rd_refresh_token', null);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('RD Station CRM: Exceção ao renovar access token', ['message' => $e->getMessage()]);
            return null;
        }
    }

    // ─── API Request ────────────────────────────────────────────

    public function makeRequest(string $method, string $endpoint, array $data = [], array $query = []): array
    {
        $token = $this->obterAccessToken();

        if (!$token) {
            return [
                'status' => 'error',
                'message' => $this->lastError ?? 'Não foi possível obter access token.',
                'requires_auth' => true,
            ];
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        Log::debug('RD Station CRM Request', ['method' => $method, 'url' => $url]);

        try {
            $response = $this->executeRequest($method, $url, $data, $token);

            if ($response->status() === 401) {
                Log::warning('RD Station CRM 401 - tentando renovar token');
                Cache::forget('rd_access_token');
                $token = $this->obterAccessToken();

                if ($token) {
                    $response = $this->executeRequest($method, $url, $data, $token);
                }
            }

            if ($response->successful()) {
                return ['status' => 'success', 'data' => $response->json()];
            }

            Log::error('RD Station CRM Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'status' => 'error',
                'message' => $this->extractErrorMessage($response),
                'status_code' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('RD Station CRM Exception', ['endpoint' => $endpoint, 'message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function executeRequest(string $method, string $url, array $data, string $token): \Illuminate\Http\Client\Response
    {
        $http = Http::timeout(30)
            ->withToken($token)
            ->accept('application/json')
            ->contentType('application/json');

        return match (strtoupper($method)) {
            'GET' => $http->get($url, $data),
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'PATCH' => $http->patch($url, $data),
            'DELETE' => $http->delete($url, $data),
            default => $http->get($url, $data),
        };
    }

    protected function extractErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $body = $response->json();
        if (isset($body['errors'])) {
            $errors = collect($body['errors'])->map(fn($e) => $e['message'] ?? $e['error_message'] ?? json_encode($e));
            return $errors->join('; ');
        }
        return $body['detail'] ?? $body['message'] ?? $body['error'] ?? 'Erro na comunicação com RD Station CRM (HTTP ' . $response->status() . ')';
    }

    // ─── Organizations (Empresas) ───────────────────────────────

    public function listarOrganizacoes(string $filter = '', int $limit = 20): array
    {
        $query = ['limit' => $limit];
        if ($filter) {
            $query['filter'] = $filter;
        }
        return $this->makeRequest('GET', 'organizations', [], $query);
    }

    public function criarOrganizacao(array $data): array
    {
        return $this->makeRequest('POST', 'organizations', ['data' => $data]);
    }

    public function atualizarOrganizacao(string $id, array $data): array
    {
        return $this->makeRequest('PUT', "organizations/{$id}", ['data' => $data]);
    }

    public function obterOrganizacao(string $id): array
    {
        return $this->makeRequest('GET', "organizations/{$id}");
    }

    /**
     * Upsert organização: prioriza rd_organization_id se existir e for válido,
     * senão busca por nome (com validação de match exato) ou cria nova.
     */
    public function upsertOrganizacao(string $nome, ?string $existingOrgId = null, ?string $ownerId = null): array
    {
        $context = ['paciente_nome' => $nome, 'existing_org_id' => $existingOrgId];

        if (!empty($existingOrgId)) {
            $getResult = $this->obterOrganizacao($existingOrgId);
            if ($getResult['status'] === 'success') {
                $org = $getResult['data']['data'] ?? $getResult['data'] ?? [];
                $orgName = $org['name'] ?? '';
                if (trim($orgName) === trim($nome)) {
                    Log::info('RD Station CRM: Organização reutilizada (rd_organization_id)', [
                        ...$context,
                        'organization_id' => $existingOrgId,
                        'org_name' => $orgName,
                        'action' => 'reused',
                    ]);
                    return ['status' => 'success', 'data' => $org, 'action' => 'reused'];
                }
                Log::warning('RD Station CRM: rd_organization_id existe mas nome não confere, buscando por nome', [
                    ...$context,
                    'org_name_no_rd' => $orgName,
                ]);
            } else {
                Log::info('RD Station CRM: rd_organization_id inválido ou org removida, buscando por nome', [
                    ...$context,
                    'get_error' => $getResult['message'] ?? null,
                ]);
            }
        }

        $filter = 'name:"' . addslashes($nome) . '"';
        $result = $this->listarOrganizacoes($filter, 1);

        if ($result['status'] === 'success') {
            $items = $result['data']['data'] ?? $result['data']['items'] ?? $result['data'] ?? [];
            if (is_array($items) && !empty($items)) {
                $org = is_array($items[0] ?? null) ? $items[0] : $items;
                if (isset($org['id'])) {
                    $orgName = $org['name'] ?? '';
                    if (trim($orgName) !== trim($nome)) {
                        Log::warning('RD Station CRM: Match parcial ignorado, criando nova organização', [
                            ...$context,
                            'org_encontrada_nome' => $orgName,
                        ]);
                    } else {
                        Log::info('RD Station CRM: Organização encontrada por nome', [
                            ...$context,
                            'organization_id' => $org['id'],
                            'org_name' => $orgName,
                            'action' => 'found',
                        ]);
                        return ['status' => 'success', 'data' => $org, 'action' => 'found'];
                    }
                }
            }
        }

        $orgData = ['name' => $nome];
        if ($ownerId) {
            $orgData['owner_id'] = $ownerId;
        }

        Log::info('RD Station CRM: Criando nova organização', [...$context, 'action' => 'created']);
        $createResult = $this->criarOrganizacao($orgData);
        if ($createResult['status'] === 'success') {
            $createResult['action'] = 'created';
        }
        return $createResult;
    }

    // ─── Contacts (Contatos) ────────────────────────────────────

    public function listarContatos(string $filter = '', int $limit = 20): array
    {
        $query = ['limit' => $limit];
        if ($filter) {
            $query['filter'] = $filter;
        }
        return $this->makeRequest('GET', 'contacts', [], $query);
    }

    public function criarContato(array $data): array
    {
        return $this->makeRequest('POST', 'contacts', ['data' => $data]);
    }

    public function atualizarContato(string $id, array $data): array
    {
        return $this->makeRequest('PUT', "contacts/{$id}", ['data' => $data]);
    }

    public function upsertContato(string $nome, ?string $telefone, ?string $email, ?string $organizationId, ?string $ownerId = null): array
    {
        $filter = 'name:"' . addslashes($nome) . '"';
        $result = $this->listarContatos($filter, 1);

        $contactData = ['name' => $nome];
        if ($organizationId) {
            $contactData['organization_id'] = $organizationId;
        }
        if ($ownerId) {
            $contactData['owner_id'] = $ownerId;
        }
        if ($telefone) {
            $contactData['phones'] = [['phone' => $telefone, 'type' => 'mobile']];
        }
        if ($email) {
            $contactData['emails'] = [['email' => $email]];
        }

        if ($result['status'] === 'success') {
            $items = $result['data']['data'] ?? $result['data']['items'] ?? $result['data'] ?? [];
            if (is_array($items) && !empty($items)) {
                $contact = is_array($items[0] ?? null) ? $items[0] : $items;
                if (isset($contact['id'])) {
                    Log::info('RD Station CRM: Contato encontrado, atualizando', ['id' => $contact['id'], 'nome' => $nome]);
                    $updateResult = $this->atualizarContato($contact['id'], $contactData);
                    if ($updateResult['status'] === 'success') {
                        $updateResult['action'] = 'updated';
                        $updateResult['data'] = array_merge($contact, $updateResult['data']['data'] ?? $updateResult['data'] ?? []);
                    }
                    return $updateResult;
                }
            }
        }

        Log::info('RD Station CRM: Criando novo contato', ['nome' => $nome]);
        $createResult = $this->criarContato($contactData);
        if ($createResult['status'] === 'success') {
            $createResult['action'] = 'created';
        }
        return $createResult;
    }

    // ─── Custom Fields (Campos personalizados) ───────────────────

    /**
     * Obtém um campo personalizado pelo ID.
     * Retorna api_identifier ou slug para usar como chave em custom_fields.
     *
     * @return array{status: string, key?: string, message?: string}
     */
    public function obterCampoPersonalizado(string $id): array
    {
        $result = $this->makeRequest('GET', "custom_fields/{$id}");

        if ($result['status'] !== 'success') {
            return $result;
        }

        $fieldData = $result['data']['data'] ?? $result['data'] ?? [];
        $key = $fieldData['api_identifier'] ?? $fieldData['slug'] ?? $id;

        return ['status' => 'success', 'key' => $key];
    }

    /**
     * Resolve a chave (api_identifier ou slug) para um custom field pelo ID.
     * Cacheia por 24h para evitar chamadas repetidas.
     */
    public function resolverChaveCustomField(string $fieldId): string
    {
        $cacheKey = "rd_custom_field_key_{$fieldId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($fieldId) {
            $result = $this->obterCampoPersonalizado($fieldId);
            if ($result['status'] === 'success' && isset($result['key'])) {
                return $result['key'];
            }
            return $fieldId;
        });
    }

    // ─── Deals (Negociações) ────────────────────────────────────

    public function criarNegociacao(array $data): array
    {
        return $this->makeRequest('POST', 'deals', ['data' => $data]);
    }

    public function atualizarNegociacao(string $id, array $data): array
    {
        return $this->makeRequest('PUT', "deals/{$id}", ['data' => $data]);
    }

    // ─── Deal Products (Produtos na negociação) ─────────────────

    public function criarProdutoNegociacao(string $dealId, array $data): array
    {
        return $this->makeRequest('POST', "deals/{$dealId}/products", ['data' => $data]);
    }

    // ─── Users (for health check) ───────────────────────────────

    public function listarUsuarios(): array
    {
        return $this->makeRequest('GET', 'users', [], ['limit' => 1]);
    }

    public function obterInfo(): array
    {
        return $this->listarUsuarios();
    }
}
