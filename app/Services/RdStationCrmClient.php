<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /**
     * @param  bool  $jsonPreserveZeroFraction  quando true, envia floats JSON como `100.0` em vez de `100` — exigência do CRM v2 para `one_time_price`, `price`, etc.
     */
    public function makeRequest(string $method, string $endpoint, array $data = [], array $query = [], bool $jsonPreserveZeroFraction = false): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');
        if (! empty($query)) {
            $url .= '?'.http_build_query($query);
        }

        return $this->executeAuthenticatedRequest($method, $url, $data, $jsonPreserveZeroFraction);
    }

    /**
     * @param  bool  $jsonPreserveZeroFraction  quando true, envia floats JSON como `100.0` em vez de `100` — exigência do CRM v2 para `one_time_price`, `price`, etc.
     */
    protected function executeAuthenticatedRequest(string $method, string $url, array $data = [], bool $jsonPreserveZeroFraction = false): array
    {
        $token = $this->obterAccessToken();

        if (! $token) {
            return [
                'status' => 'error',
                'message' => $this->lastError ?? 'Não foi possível obter access token.',
                'requires_auth' => true,
            ];
        }

        Log::debug('RD Station CRM Request', ['method' => $method, 'url' => $url]);

        try {
            $dispatch = function () use (&$token, $method, $url, $data, $jsonPreserveZeroFraction): \Illuminate\Http\Client\Response {
                return $jsonPreserveZeroFraction
                    ? $this->executeRequestWithPreservedFloatJson($method, $url, $data, $token)
                    : $this->executeRequest($method, $url, $data, $token);
            };

            $response = $dispatch();

            if ($response->status() === 401) {
                Log::warning('RD Station CRM 401 - tentando renovar token');
                Cache::forget('rd_access_token');
                $token = $this->obterAccessToken();

                if ($token) {
                    $response = $dispatch();
                }
            }

            if ($response->successful()) {
                return ['status' => 'success', 'data' => $response->json()];
            }

            Log::error('RD Station CRM Error', [
                'url' => $url,
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
            Log::error('RD Station CRM Exception', ['url' => $url, 'message' => $e->getMessage()]);

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
            // Laravel Http::get($url, []) drops query params already present in $url.
            'GET' => $data !== []
                ? $http->get($url, $data)
                : $http->get($url),
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'PATCH' => $http->patch($url, $data),
            'DELETE' => $http->delete($url, $data),
            default => $http->get($url, $data),
        };
    }

    /**
     * Serializa o corpo com JSON_PRESERVE_ZERO_FRACTION para que números “inteiros” em float
     * (ex.: 100.0) não sejam emitidos como int no JSON, o que o CRM rejeita em campos float.
     */
    protected function executeRequestWithPreservedFloatJson(string $method, string $url, array $data, string $token): \Illuminate\Http\Client\Response
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        $base = Http::timeout(30)
            ->withToken($token)
            ->accept('application/json')
            ->contentType('application/json')
            ->withBody($json, 'application/json');

        return match (strtoupper($method)) {
            'POST' => $base->post($url),
            'PUT' => $base->put($url),
            'PATCH' => $base->patch($url),
            'DELETE' => $base->delete($url),
            default => $this->executeRequest($method, $url, $data, $token),
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

    protected function buildListQuery(string $filter, int $limit): array
    {
        $query = [
            'page' => [
                'size' => $limit,
                'number' => 1,
            ],
        ];
        if ($filter !== '') {
            $query['filter'] = $filter;
        }

        return $query;
    }

    protected function formatRdqlNameFilter(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'name:';
        }
        if (preg_match('/[\s"\\\\]/', $nome)) {
            return 'name:"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $nome).'"';
        }

        return 'name:'.$nome;
    }

    protected function formatRdqlMatchFilter(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'name:~';
        }
        if (preg_match('/[\s"\\\\]/', $nome)) {
            return 'name:~"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $nome).'"';
        }

        return 'name:~'.$nome;
    }

    /**
     * @return list<string>
     */
    protected function buildNameSearchFilters(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['name:~', 'name:'];
        }

        $filters = [
            $this->formatRdqlMatchFilter($nome),
            $this->formatRdqlNameFilter($nome),
        ];

        $firstToken = strtok($nome, ' ');
        if (is_string($firstToken) && $firstToken !== '' && $firstToken !== $nome) {
            $filters[] = $this->formatRdqlMatchFilter($firstToken);
            $filters[] = $this->formatRdqlNameFilter($firstToken);
        }

        return array_values(array_unique($filters));
    }

    protected function normalizeNome(string $nome): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($nome));

        return mb_strtolower(Str::ascii($collapsed ?? ''));
    }

    /**
     * @param  array{status: string, data?: mixed}  $result
     * @return list<array<string, mixed>>
     */
    protected function extrairItensListagem(array $result): array
    {
        if ($result['status'] !== 'success') {
            return [];
        }

        $payload = $result['data'] ?? [];
        if (! is_array($payload)) {
            return [];
        }

        $items = $payload['data'] ?? $payload['items'] ?? $payload;
        if (! is_array($items)) {
            return [];
        }

        if ($items !== [] && ! array_is_list($items)) {
            return [$items];
        }

        return array_values(array_filter($items, fn ($item) => is_array($item)));
    }

    protected function isDuplicateNameError(array $result): bool
    {
        $candidates = [(string) ($result['message'] ?? '')];

        $data = $result['data'] ?? null;
        if (is_array($data)) {
            if (isset($data['detail'])) {
                $candidates[] = (string) $data['detail'];
            }
            if (isset($data['data']['detail'])) {
                $candidates[] = (string) $data['data']['detail'];
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->messageIndicatesDuplicateName($candidate)) {
                return true;
            }
        }

        return false;
    }

    protected function messageIndicatesDuplicateName(string $message): bool
    {
        $text = trim($message);
        if ($text === '') {
            return false;
        }

        if (str_starts_with($text, '{')) {
            $decoded = json_decode($text, true);
            if (is_array($decoded) && isset($decoded['detail'])) {
                $text = (string) $decoded['detail'];
            }
        }

        $lower = mb_strtolower($text);

        return str_contains($lower, 'cadastrad')
            || str_contains($lower, 'already registered')
            || str_contains($lower, 'already exists');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listarRecursoComFiltro(string $endpoint, string $filter, int $pageSize = 25, int $maxPages = 10): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');
        $url .= '?'.http_build_query($this->buildListQuery($filter, $pageSize));

        $items = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $result = $this->executeAuthenticatedRequest('GET', $url);
            if ($result['status'] !== 'success') {
                break;
            }

            $items = array_merge($items, $this->extrairItensListagem($result));

            $next = $result['data']['links']['next'] ?? null;
            if (! is_string($next) || $next === '') {
                break;
            }

            $url = $next;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buscarRecursoPorNome(string $endpoint, string $nome): ?array
    {
        $normalizedTarget = $this->normalizeNome($nome);

        foreach ($this->buildNameSearchFilters($nome) as $filter) {
            foreach ($this->listarRecursoComFiltro($endpoint, $filter) as $item) {
                if (! isset($item['id'])) {
                    continue;
                }
                if ($this->normalizeNome((string) ($item['name'] ?? '')) === $normalizedTarget) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buscarOrganizacaoPorNomeExato(string $nome): ?array
    {
        return $this->buscarRecursoPorNome('organizations', $nome);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buscarContatoPorNomeExato(string $nome): ?array
    {
        return $this->buscarRecursoPorNome('contacts', $nome);
    }

    public function listarOrganizacoes(string $filter = '', int $limit = 20): array
    {
        return $this->makeRequest('GET', 'organizations', [], $this->buildListQuery($filter, $limit));
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

        if (! empty($existingOrgId)) {
            $getResult = $this->obterOrganizacao($existingOrgId);
            if ($getResult['status'] === 'success') {
                $org = $getResult['data']['data'] ?? $getResult['data'] ?? [];
                Log::info('RD Station CRM: Organização reutilizada (rd_organization_id)', [
                    ...$context,
                    'organization_id' => $existingOrgId,
                    'org_name' => $org['name'] ?? null,
                    'action' => 'reused',
                ]);

                return ['status' => 'success', 'data' => $org, 'action' => 'reused'];
            }

            Log::info('RD Station CRM: rd_organization_id inválido ou org removida, buscando por nome', [
                ...$context,
                'get_error' => $getResult['message'] ?? null,
            ]);
        }

        $org = $this->buscarOrganizacaoPorNomeExato($nome);
        if ($org !== null) {
            Log::info('RD Station CRM: Organização encontrada por nome', [
                ...$context,
                'organization_id' => $org['id'],
                'org_name' => $org['name'] ?? null,
                'action' => 'found',
            ]);

            return ['status' => 'success', 'data' => $org, 'action' => 'found'];
        }

        $orgData = ['name' => $nome];
        if ($ownerId) {
            $orgData['owner_id'] = $ownerId;
        }

        Log::info('RD Station CRM: Criando nova organização', [...$context, 'action' => 'created']);
        $createResult = $this->criarOrganizacao($orgData);
        if ($createResult['status'] === 'success') {
            $createResult['action'] = 'created';

            return $createResult;
        }

        if ($this->isDuplicateNameError($createResult)) {
            Log::warning('RD Station CRM: Organização duplicada ao criar, buscando existente', [
                ...$context,
                'error' => $createResult['message'] ?? null,
            ]);
            $org = $this->buscarOrganizacaoPorNomeExato($nome);
            if ($org !== null) {
                Log::info('RD Station CRM: Organização reutilizada após erro de duplicata', [
                    ...$context,
                    'organization_id' => $org['id'],
                    'org_name' => $org['name'] ?? null,
                    'action' => 'found_after_duplicate',
                ]);

                return ['status' => 'success', 'data' => $org, 'action' => 'found_after_duplicate'];
            }

            Log::warning('RD Station CRM: Duplicata detectada mas organização não encontrada na busca', [
                ...$context,
                'filters_tried' => $this->buildNameSearchFilters($nome),
                'error' => $createResult['message'] ?? null,
            ]);
        }

        return $createResult;
    }

    // ─── Contacts (Contatos) ────────────────────────────────────

    public function listarContatos(string $filter = '', int $limit = 20): array
    {
        return $this->makeRequest('GET', 'contacts', [], $this->buildListQuery($filter, $limit));
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

        $contact = $this->buscarContatoPorNomeExato($nome);
        if ($contact !== null) {
            Log::info('RD Station CRM: Contato encontrado, atualizando', ['id' => $contact['id'], 'nome' => $nome]);
            $updateResult = $this->atualizarContato($contact['id'], $contactData);
            if ($updateResult['status'] === 'success') {
                $updateResult['action'] = 'updated';
                $updateResult['data'] = array_merge($contact, $updateResult['data']['data'] ?? $updateResult['data'] ?? []);
            }

            return $updateResult;
        }

        Log::info('RD Station CRM: Criando novo contato', ['nome' => $nome]);
        $createResult = $this->criarContato($contactData);
        if ($createResult['status'] === 'success') {
            $createResult['action'] = 'created';

            return $createResult;
        }

        if ($this->isDuplicateNameError($createResult)) {
            Log::warning('RD Station CRM: Contato duplicado ao criar, buscando existente', [
                'nome' => $nome,
                'error' => $createResult['message'] ?? null,
            ]);
            $contact = $this->buscarContatoPorNomeExato($nome);
            if ($contact !== null) {
                $updateResult = $this->atualizarContato($contact['id'], $contactData);
                if ($updateResult['status'] === 'success') {
                    $updateResult['action'] = 'updated_after_duplicate';
                    $updateResult['data'] = array_merge($contact, $updateResult['data']['data'] ?? $updateResult['data'] ?? []);
                }

                return $updateResult;
            }
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
        return $this->makeRequest('POST', 'deals', ['data' => $data], [], true);
    }

    public function atualizarNegociacao(string $id, array $data): array
    {
        return $this->makeRequest('PUT', "deals/{$id}", ['data' => $data], [], true);
    }

    // ─── Deal Products (Produtos na negociação) ─────────────────

    public function criarProdutoNegociacao(string $dealId, array $data): array
    {
        return $this->makeRequest('POST', "deals/{$dealId}/products", ['data' => $data], [], true);
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
