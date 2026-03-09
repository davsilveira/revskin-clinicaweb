<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TinyErpClient
{
    protected string $baseUrl;
    protected string $baseUrlV2 = 'https://api.tiny.com.br/api2';
    protected string $tokenUrl = 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token';
    protected ?string $accessToken = null;
    protected ?string $lastError = null;
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = Setting::get('tiny_api_version', 'v3');
        $this->baseUrl = Setting::get('tiny_url_base', 'https://api.tiny.com.br/public-api/v3');
    }

    public function isV2(): bool
    {
        return $this->apiVersion === 'v2';
    }

    // ─── V2 API Methods ────────────────────────────────────────

    protected function getV2Token(): ?string
    {
        $token = Setting::get('tiny_token');
        if (empty($token)) {
            $this->lastError = 'Token da API V2 não configurado. Acesse Configurações > Integrações > Tiny ERP.';
            return null;
        }
        try {
            return decrypt($token);
        } catch (\Exception $e) {
            return $token;
        }
    }

    protected function makeV2Request(string $endpoint, array $extraParams = []): array
    {
        $token = $this->getV2Token();
        if (!$token) {
            return [
                'status' => 'error',
                'message' => $this->lastError ?? 'Token V2 não configurado.',
                'requires_auth' => true,
            ];
        }

        $url = rtrim($this->baseUrlV2, '/') . '/' . ltrim($endpoint, '/');
        $params = array_merge(['token' => $token, 'formato' => 'JSON'], $extraParams);

        Log::debug('Tiny ERP V2 Request', ['url' => $url, 'params' => array_diff_key($params, ['token' => ''])]);

        try {
            $response = Http::timeout(30)->asForm()->post($url, $params);

            if (!$response->successful()) {
                Log::error('Tiny ERP V2 HTTP Error', ['url' => $url, 'status' => $response->status()]);
                return [
                    'status' => 'error',
                    'message' => 'Erro HTTP ' . $response->status(),
                    'status_code' => $response->status(),
                ];
            }

            $data = $response->json();
            $retorno = $data['retorno'] ?? $data;

            if (($retorno['status'] ?? '') === 'Erro') {
                $erros = collect($retorno['erros'] ?? [])->pluck('erro')->join('; ');
                Log::error('Tiny ERP V2 API Error', ['url' => $url, 'erros' => $erros]);
                return [
                    'status' => 'error',
                    'message' => $erros ?: 'Erro na API V2',
                    'status_code' => $retorno['codigo_erro'] ?? null,
                ];
            }

            return [
                'status' => 'success',
                'data' => $retorno,
            ];
        } catch (\Exception $e) {
            Log::error('Tiny ERP V2 Exception', ['url' => $url, 'message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── V3 OAuth Methods (unchanged) ──────────────────────────

    protected function obterAccessToken(): ?string
    {
        $cachedToken = Cache::get('tiny_access_token');
        if ($cachedToken) {
            $this->accessToken = $cachedToken;
            return $cachedToken;
        }

        $refreshToken = Setting::get('tiny_refresh_token');
        if ($refreshToken) {
            try {
                $decryptedRefreshToken = decrypt($refreshToken);
                $token = $this->renovarAccessTokenComRefreshToken($decryptedRefreshToken);
                if ($token) {
                    return $token;
                }
            } catch (\Exception $e) {
                Log::warning('Tiny ERP: Erro ao usar refresh token', ['message' => $e->getMessage()]);
            }
        }

        $this->lastError = 'Refresh token não encontrado. É necessário fazer autenticação inicial. Acesse Configurações > Integrações > Tiny ERP e clique em "Autorizar Aplicativo".';
        Log::warning('Tiny ERP: Refresh token não encontrado');
        return null;
    }

    protected function renovarAccessTokenComRefreshToken(string $refreshToken): ?string
    {
        $clientId = Setting::get('tiny_client_id');
        $clientSecret = Setting::get('tiny_client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('Tiny ERP: Client ID ou Client Secret não configurados');
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
                $newRefreshToken = $data['refresh_token'] ?? $refreshToken;
                $expiresIn = $data['expires_in'] ?? 14400;

                if ($token) {
                    Cache::put('tiny_access_token', $token, now()->addSeconds($expiresIn - 300));
                    if ($newRefreshToken !== $refreshToken) {
                        Setting::set('tiny_refresh_token', encrypt($newRefreshToken));
                    }
                    $this->accessToken = $token;
                    Log::info('Tiny ERP: Access token renovado com refresh token', ['expires_in' => $expiresIn]);
                    return $token;
                }
            }

            if ($response->status() === 400 || $response->status() === 401) {
                Log::warning('Tiny ERP: Refresh token inválido ou expirado', ['status' => $response->status()]);
                Setting::set('tiny_refresh_token', null);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Tiny ERP: Exceção ao renovar access token', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function trocarCodigoPorTokens(string $authorizationCode, string $redirectUri): array
    {
        $clientId = Setting::get('tiny_client_id');
        $clientSecret = Setting::get('tiny_client_secret');

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
                'code' => $authorizationCode,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'] ?? null;
                $refreshToken = $data['refresh_token'] ?? null;
                $expiresIn = $data['expires_in'] ?? 14400;

                Log::info('Tiny ERP: Resposta do token endpoint', [
                    'token_type' => $data['token_type'] ?? 'unknown',
                    'scope' => $data['scope'] ?? 'none',
                    'expires_in' => $expiresIn,
                    'has_access_token' => !empty($accessToken),
                    'has_refresh_token' => !empty($refreshToken),
                ]);

                if ($accessToken && $refreshToken) {
                    Cache::put('tiny_access_token', $accessToken, now()->addSeconds($expiresIn - 300));
                    Setting::set('tiny_refresh_token', encrypt($refreshToken));
                    Log::info('Tiny ERP: Tokens obtidos com sucesso via authorization code');
                    return ['status' => 'success', 'message' => 'Autenticação realizada com sucesso!'];
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? 'Erro ao trocar código por tokens';
            Log::error('Tiny ERP: Erro ao trocar código por tokens', ['status' => $response->status()]);
            return ['status' => 'error', 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('Tiny ERP: Exceção ao trocar código por tokens', ['message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

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

    // ─── V3 API Request ────────────────────────────────────────

    public function makeRequest(string $method, string $endpoint, array $data = []): array
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

        Log::debug('Tiny ERP API Request', ['method' => $method, 'url' => $url]);

        try {
            $httpClient = Http::timeout(30)->withToken($token)->accept('application/json');
            if ($method !== 'GET') {
                $httpClient = $httpClient->contentType('application/json');
            }

            $response = $httpClient->{strtolower($method)}($url, $data);

            if ($response->successful()) {
                return ['status' => 'success', 'data' => $response->json()];
            }

            if ($response->status() === 401) {
                Log::warning('Tiny ERP API 401 - tentando renovar token');
                Cache::forget('tiny_access_token');
                $token = $this->obterAccessToken();

                if ($token) {
                    $httpClient = Http::timeout(30)->withToken($token)->accept('application/json');
                    if ($method !== 'GET') {
                        $httpClient = $httpClient->contentType('application/json');
                    }
                    $response = $httpClient->{strtolower($method)}($url, $data);
                }
            }

            if ($response->successful()) {
                return ['status' => 'success', 'data' => $response->json()];
            }

            Log::error('Tiny ERP API Error', ['endpoint' => $endpoint, 'status' => $response->status()]);

            return [
                'status' => 'error',
                'message' => 'Erro na comunicação com Tiny ERP (HTTP ' . $response->status() . ')',
                'status_code' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Tiny ERP API Exception', ['endpoint' => $endpoint, 'message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── Public API Methods (version-routed) ───────────────────

    public function obterInfo(): array
    {
        if ($this->isV2()) {
            $result = $this->makeV2Request('info.php');
            if ($result['status'] === 'success') {
                $result['data'] = $result['data']['conta'] ?? $result['data'];
            }
            return $result;
        }
        return $this->makeRequest('GET', 'info');
    }

    public function listarProdutos(array $filters = []): array
    {
        if ($this->isV2()) {
            $params = ['pesquisa' => $filters['pesquisa'] ?? ''];
            if (!empty($filters['pagina'])) {
                $params['pagina'] = $filters['pagina'];
            }
            if (!empty($filters['situacao'])) {
                $params['situacao'] = $filters['situacao'];
            }

            $result = $this->makeV2Request('produtos.pesquisa.php', $params);

            if ($result['status'] === 'success') {
                $retorno = $result['data'];
                $produtos = $retorno['produtos'] ?? [];
                $itens = array_map(fn($p) => $this->normalizeV2Product($p['produto'] ?? $p), $produtos);

                $result['data'] = [
                    'itens' => $itens,
                    'paginacao' => [
                        'pagina' => $retorno['pagina'] ?? 1,
                        'numero_paginas' => $retorno['numero_paginas'] ?? 1,
                    ],
                ];
            }
            return $result;
        }

        $queryParams = http_build_query($filters);
        $endpoint = 'produtos' . ($queryParams ? '?' . $queryParams : '');
        return $this->makeRequest('GET', $endpoint);
    }

    public function obterProduto(int $id): array
    {
        if ($this->isV2()) {
            $result = $this->makeV2Request('produto.obter.php', ['id' => $id]);
            if ($result['status'] === 'success') {
                $produto = $result['data']['produto'] ?? $result['data'];
                $result['data'] = $this->normalizeV2Product($produto);
            }
            return $result;
        }
        return $this->makeRequest('GET', "produtos/{$id}");
    }

    public function listarContatos(array $filters = []): array
    {
        if ($this->isV2()) {
            $params = ['pesquisa' => $filters['pesquisa'] ?? ''];
            if (!empty($filters['cpf_cnpj'])) {
                $params['cpf_cnpj'] = $filters['cpf_cnpj'];
            }
            if (!empty($filters['pagina'])) {
                $params['pagina'] = $filters['pagina'];
            }

            $result = $this->makeV2Request('contatos.pesquisa.php', $params);

            if ($result['status'] === 'success') {
                $retorno = $result['data'];
                $contatos = $retorno['contatos'] ?? [];
                $itens = array_map(fn($c) => $c['contato'] ?? $c, $contatos);
                $result['data'] = ['itens' => $itens];
            }
            return $result;
        }

        $queryParams = http_build_query($filters);
        $endpoint = 'contatos' . ($queryParams ? '?' . $queryParams : '');
        return $this->makeRequest('GET', $endpoint);
    }

    public function obterContato(int $id): array
    {
        if ($this->isV2()) {
            $result = $this->makeV2Request('contato.obter.php', ['id' => $id]);
            if ($result['status'] === 'success') {
                $result['data'] = $result['data']['contato'] ?? $result['data'];
            }
            return $result;
        }
        return $this->makeRequest('GET', "contatos/{$id}");
    }

    public function criarContato(array $data): array
    {
        if ($this->isV2()) {
            $v2Data = $this->buildV2ContactPayload($data);
            $result = $this->makeV2Request('contato.incluir.php', [
                'contato' => json_encode($v2Data),
            ]);

            if ($result['status'] === 'success') {
                $registros = $result['data']['registros'] ?? [];
                $firstReg = $registros[0]['registro'] ?? [];
                $result['data'] = ['id' => $firstReg['id'] ?? null];
            }
            return $result;
        }
        return $this->makeRequest('POST', 'contatos', $data);
    }

    public function atualizarContato(int $id, array $data): array
    {
        if ($this->isV2()) {
            $v2Data = $this->buildV2ContactPayload($data);
            $result = $this->makeV2Request('contato.alterar.php', [
                'contato' => json_encode(array_merge($v2Data, ['id' => $id])),
            ]);

            if ($result['status'] === 'success') {
                $registros = $result['data']['registros'] ?? [];
                $firstReg = $registros[0]['registro'] ?? [];
                $result['data'] = ['id' => $firstReg['id'] ?? $id];
            }
            return $result;
        }
        return $this->makeRequest('PUT', "contatos/{$id}", $data);
    }

    public function criarPedido(array $data): array
    {
        if ($this->isV2()) {
            $v2Data = $this->buildV2OrderPayload($data);
            $result = $this->makeV2Request('pedido.incluir.php', [
                'pedido' => json_encode(['pedido' => $v2Data]),
            ]);

            if ($result['status'] === 'success') {
                $registros = $result['data']['registros'] ?? [];
                $firstReg = $registros[0]['registro'] ?? [];
                $result['data'] = [
                    'id' => $firstReg['id'] ?? null,
                    'numero' => $firstReg['numero'] ?? null,
                ];
            }
            return $result;
        }
        return $this->makeRequest('POST', 'pedidos', $data);
    }

    public function obterPedido(int $id): array
    {
        if ($this->isV2()) {
            $result = $this->makeV2Request('pedido.obter.php', ['id' => $id]);
            if ($result['status'] === 'success') {
                $pedido = $result['data']['pedido'] ?? $result['data'];
                $result['data'] = $this->normalizeV2Order($pedido);
            }
            return $result;
        }
        return $this->makeRequest('GET', "pedidos/{$id}");
    }

    public function listarPedidos(array $filters = []): array
    {
        if ($this->isV2()) {
            $params = ['pesquisa' => $filters['pesquisa'] ?? ''];
            if (!empty($filters['pagina'])) {
                $params['pagina'] = $filters['pagina'];
            }
            $result = $this->makeV2Request('pedidos.pesquisa.php', $params);
            if ($result['status'] === 'success') {
                $pedidos = $result['data']['pedidos'] ?? [];
                $result['data'] = ['itens' => array_map(fn($p) => $p['pedido'] ?? $p, $pedidos)];
            }
            return $result;
        }

        $queryParams = http_build_query($filters);
        $endpoint = 'pedidos' . ($queryParams ? '?' . $queryParams : '');
        return $this->makeRequest('GET', $endpoint);
    }

    public function atualizarPedido(int $id, array $data): array
    {
        if ($this->isV2()) {
            return ['status' => 'error', 'message' => 'Atualizar pedido não disponível na V2 por esta rota.'];
        }
        return $this->makeRequest('PUT', "pedidos/{$id}", $data);
    }

    public function atualizarSituacaoPedido(int $id, string $situacao): array
    {
        if ($this->isV2()) {
            return $this->makeV2Request('pedido.alterar.situacao.php', [
                'id' => $id,
                'situacao' => $situacao,
            ]);
        }
        return $this->makeRequest('PUT', "pedidos/{$id}/situacao", ['situacao' => $situacao]);
    }

    // ─── V2 Data Normalization / Payload Building ──────────────

    protected function normalizeV2Product(array $produto): array
    {
        return [
            'id' => $produto['id'] ?? null,
            'sku' => $produto['codigo'] ?? null,
            'descricao' => $produto['nome'] ?? $produto['descricao'] ?? 'Sem nome',
            'unidade' => $produto['unidade'] ?? null,
            'situacao' => $produto['situacao'] ?? 'A',
            'precos' => [
                'preco' => (float) ($produto['preco'] ?? 0),
                'precoCusto' => isset($produto['preco_custo']) ? (float) $produto['preco_custo'] : null,
                'precoCustoMedio' => isset($produto['preco_custo_medio']) ? (float) $produto['preco_custo_medio'] : null,
                'precoPromocional' => isset($produto['preco_promocional']) ? (float) $produto['preco_promocional'] : null,
            ],
            'tipoVariacao' => $produto['tipoVariacao'] ?? 'N',
        ];
    }

    protected function normalizeV2Order(array $pedido): array
    {
        $itens = [];
        foreach ($pedido['itens'] ?? [] as $itemWrapper) {
            $item = $itemWrapper['item'] ?? $itemWrapper;
            $itens[] = [
                'produto' => ['id' => $item['id_produto'] ?? null, 'codigo' => $item['codigo'] ?? null],
                'descricao' => $item['descricao'] ?? '',
                'quantidade' => (float) ($item['quantidade'] ?? 0),
                'valorUnitario' => (float) ($item['valor_unitario'] ?? 0),
            ];
        }

        return [
            'id' => $pedido['id'] ?? null,
            'numero' => $pedido['numero'] ?? null,
            'situacao' => $pedido['situacao'] ?? null,
            'data' => $pedido['data_pedido'] ?? null,
            'observacoes' => $pedido['obs'] ?? null,
            'itens' => $itens,
        ];
    }

    protected function buildV2ContactPayload(array $data): array
    {
        $contato = [
            'sequencia' => 1,
            'nome' => $data['nome'] ?? '',
            'situacao' => 'A',
        ];

        if (!empty($data['tipoPessoa'])) {
            $contato['tipo_pessoa'] = $data['tipoPessoa'];
        }
        if (!empty($data['cpfCnpj'])) {
            $contato['cpf_cnpj'] = $data['cpfCnpj'];
        }
        if (!empty($data['email'])) {
            $contato['email'] = $data['email'];
        }
        if (!empty($data['telefone'])) {
            $contato['fone'] = $data['telefone'];
        }
        if (!empty($data['celular'])) {
            $contato['celular'] = $data['celular'];
        }
        if (!empty($data['endereco']) && is_array($data['endereco'])) {
            $end = $data['endereco'];
            $contato['endereco'] = $end['endereco'] ?? '';
            $contato['numero'] = $end['numero'] ?? '';
            $contato['complemento'] = $end['complemento'] ?? '';
            $contato['bairro'] = $end['bairro'] ?? '';
            $contato['cidade'] = $end['cidade'] ?? '';
            $contato['uf'] = $end['uf'] ?? '';
            $contato['cep'] = $end['cep'] ?? '';
        }

        return ['contatos' => [['contato' => $contato]]];
    }

    protected function buildV2OrderPayload(array $data): array
    {
        $pedido = [
            'obs' => $data['observacoes'] ?? '',
            'situacao' => 'aberto',
        ];

        if (!empty($data['data'])) {
            $pedido['data_pedido'] = date('d/m/Y', strtotime($data['data']));
        }

        if (!empty($data['valorFrete'])) {
            $pedido['valor_frete'] = number_format($data['valorFrete'], 2, '.', '');
        }
        if (!empty($data['valorDesconto'])) {
            $pedido['valor_desconto'] = number_format($data['valorDesconto'], 2, '.', '');
        }

        $pedido['nome_vendedor'] = 'ClinicaWeb';

        if (!empty($data['cliente'])) {
            $pedido['cliente'] = $data['cliente'];
            $pedido['cliente']['atualizar_cliente'] = 'S';
        } elseif (!empty($data['idContato'])) {
            $pedido['cliente'] = ['codigo' => (string) $data['idContato']];
        }

        $itens = [];
        foreach ($data['itens'] ?? [] as $item) {
            $v2Item = [
                'descricao' => $item['descricao'] ?? 'Produto',
                'unidade' => $item['unidade'] ?? 'UN',
                'quantidade' => number_format($item['quantidade'] ?? 1, 2, '.', ''),
                'valor_unitario' => number_format($item['valorUnitario'] ?? 0, 2, '.', ''),
            ];

            if (!empty($item['produto']['id'])) {
                $v2Item['id_produto'] = $item['produto']['id'];
            }

            $itens[] = ['item' => $v2Item];
        }

        $pedido['itens'] = $itens;

        return $pedido;
    }
}
