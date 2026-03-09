<?php

namespace App\Services;

use App\Models\InfosimplesCache;
use App\Models\InfosimplesSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfosimplesClient
{
    protected string $baseUrl;
    protected InfosimplesSetting $settings;

    public function __construct(?InfosimplesSetting $settings = null)
    {
        $this->baseUrl = rtrim(config('services.infosimples.base_url', 'https://api.infosimples.com/api/v2/consultas'), '/');
        $this->settings = $settings ?: InfosimplesSetting::current();
    }

    public function enabled(): bool
    {
        return $this->settings->enabled && !empty($this->settings->token);
    }

    public function cacheTtlMonths(): int
    {
        return $this->settings->cacheTtlMonths();
    }

    public function cnpj(string $cnpj, bool $forceRefresh = false): array
    {
        $service = 'receita-federal/cnpj';

        return $this->requestWithCache($service, $cnpj, [
            'cnpj' => $cnpj,
        ], $forceRefresh);
    }

    public function cpf(string $cpf, ?string $birthdate = null, bool $forceRefresh = false): array
    {
        $service = 'receita-federal/cpf';
        $params = ['cpf' => $cpf];

        if ($birthdate) {
            $params['birthdate'] = $birthdate;
        }

        $cacheKey = $birthdate ? $cpf . '|' . $birthdate : $cpf;

        return $this->requestWithCache($service, $cacheKey, $params, $forceRefresh);
    }

    public function cro(string $uf, string $numero, bool $forceRefresh = false): array
    {
        $service = sprintf('cro/%s/cadastro', strtolower($uf));

        return $this->requestWithCache($service, $numero, [
            'inscricao' => $numero,
        ], $forceRefresh);
    }

    public function requestWithCache(string $service, string $key, array $params, bool $forceRefresh = false): array
    {
        if (!$forceRefresh) {
            $cache = InfosimplesCache::query()
                ->where('service', $service)
                ->where('key_value', $key)
                ->first();

            if ($cache && $cache->isFresh($this->cacheTtlMonths())) {
                return $cache->response;
            }
        }

        $response = $this->call($service, $params);

        if (array_key_exists('code', $response)) {
            InfosimplesCache::query()->updateOrCreate(
                [
                    'service' => $service,
                    'key_value' => $key,
                ],
                [
                    'response' => $response,
                    'code' => Arr::get($response, 'code', 0),
                ]
            );
        }

        return $response;
    }

    public function clearCache(string $service, string $key): void
    {
        InfosimplesCache::query()
            ->where('service', $service)
            ->where('key_value', $key)
            ->delete();
    }

    protected function call(string $service, array $params): array
    {
        if (!$this->enabled()) {
            return [
                'code' => 601,
                'code_message' => 'Infosimples integration disabled or token missing.',
                'data' => [],
                'errors' => ['Integration disabled'],
            ];
        }

        $body = array_merge([
            'token' => $this->settings->token,
            'timeout' => $this->settings->timeout ?? 30,
            'ignore_site_receipt' => 1,
        ], $params);

        try {
            $response = Http::asForm()
                ->timeout(max(15, (int) ($this->settings->timeout ?: 30)))
                ->post($this->baseUrl . '/' . ltrim($service, '/'), $body);
        } catch (ConnectionException $exception) {
            Log::error('Infosimples HTTP connection exception', [
                'service' => $service,
                'params' => array_keys($params),
                'error' => $exception->getMessage(),
            ]);

            return [
                'code' => 600,
                'code_message' => 'Erro ao conectar com a Infosimples: ' . $exception->getMessage(),
                'data' => [],
                'errors' => [$exception->getMessage()],
            ];
        } catch (\Throwable $throwable) {
            Log::error('Infosimples unexpected error', [
                'service' => $service,
                'params' => array_keys($params),
                'error' => $throwable->getMessage(),
            ]);

            return [
                'code' => 600,
                'code_message' => 'Erro inesperado ao chamar Infosimples.',
                'data' => [],
                'errors' => [$throwable->getMessage()],
            ];
        }

        if ($response->failed()) {
            $payload = $response->json();

            return [
                'code' => $response->status(),
                'code_message' => 'HTTP error calling Infosimples',
                'data' => [],
                'errors' => $payload ?? [$response->body()],
            ];
        }

        $json = $response->json();

        if (!is_array($json)) {
            Log::warning('Infosimples response not JSON', [
                'service' => $service,
                'body' => $response->body(),
            ]);

            return [
                'code' => 600,
                'code_message' => 'Resposta inválida da Infosimples.',
                'data' => [],
                'errors' => ['Invalid JSON response'],
            ];
        }

        // Log price information when available for cost tracking
        $price = Arr::get($json, 'header.price');
        if ($price) {
            Log::info('Infosimples consulta realizada', [
                'service' => $service,
                'price' => $price,
                'code' => Arr::get($json, 'code'),
            ]);
        }

        return $json;
    }
}

