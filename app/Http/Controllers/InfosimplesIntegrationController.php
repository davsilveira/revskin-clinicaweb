<?php

namespace App\Http\Controllers;

use App\Models\InfosimplesCache;
use App\Models\InfosimplesSetting;
use App\Services\InfosimplesClient;
use App\Services\InfosimplesResultFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InfosimplesIntegrationController extends Controller
{
    public function tools(Request $request)
    {
        $this->ensureAdminOrFinance();

        $settings = InfosimplesSetting::current();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = $this->sanitizePerPage($request->query('per_page'));
        $search = (string) $request->query('search', '');

        return Inertia::render('Tools/Infosimples', [
            'infosimples' => [
                'enabled' => (bool) $settings->enabled,
                'cache_months' => $settings->cache_months,
                'timeout' => $settings->timeout,
                'updated_at' => optional($settings->updated_at)->format('d/m/Y H:i'),
            ],
            'history' => $this->formatHistory($page, $perPage, $search),
            'historyFilters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'enabled' => 'required|boolean',
            'token' => 'nullable|string|max:255',
            'remove_token' => 'sometimes|boolean',
            'cache_months' => 'required|integer|min:1|max:6',
            'timeout' => 'required|integer|min:15|max:600',
        ]);

        $settings = InfosimplesSetting::current();
        
        $payload = [
            'enabled' => $data['enabled'],
            'cache_months' => $data['cache_months'],
            'timeout' => $data['timeout'],
        ];

        // Token management logic
        if (!empty($data['remove_token'])) {
            $payload['token'] = null;
        } elseif (!empty($data['token'])) {
            $payload['token'] = $data['token'];
        }

        $settings->update($payload);

        Config::set('services.infosimples.enabled', $settings->enabled);
        Config::set('services.infosimples.token', $settings->token);
        Config::set('services.infosimples.cache_months', $settings->cache_months);
        Config::set('services.infosimples.timeout', $settings->timeout);

        return back()->with('success', 'Configurações atualizadas com sucesso.');
    }

    public function testConnection()
    {
        $this->ensureAdmin();

        $settings = InfosimplesSetting::current();

        if (!$settings->enabled || empty($settings->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Integração Infosimples está desativada ou sem token configurado.',
            ], 422);
        }

        try {
            $url = 'https://api.infosimples.com/api/admin/account';

            $response = Http::asForm()->get($url, [
                'token' => $settings->token,
            ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível conectar ao endpoint de conta da Infosimples.',
                ], $response->status());
            }

            $json = $response->json();
            $code = Arr::get($json, 'code');
            $message = Arr::get($json, 'code_message') ?? 'Resposta recebida da Infosimples.';

            $success = $code === 200;

            return response()->json([
                'success' => $success,
                'code' => $code,
                'message' => $success
                    ? 'Conexão com Infosimples estabelecida e token válido.'
                    : ($message ?: 'Token inválido ou sem permissão na Infosimples.'),
                'data' => Arr::get($json, 'data.0') ?: null,
            ]);
        } catch (\Throwable $throwable) {
            Log::error('Infosimples test connection failed', [
                'error' => $throwable->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao testar conexão com Infosimples.',
            ], 500);
        }
    }

    public function lookup(Request $request, string $type, InfosimplesClient $client)
    {
        $this->ensureAdminOrFinance();

        if (!in_array($type, ['cpf', 'cnpj', 'cro'], true)) {
            abort(404);
        }

        if (!config('services.infosimples.enabled')) {
            return response()->json([
                'message' => 'Integração Infosimples está desativada.',
            ], 422);
        }

        switch ($type) {
            case 'cpf':
                $payload = $request->validate([
                    'cpf' => [
                        'required',
                        'string',
                        function ($attribute, $value, $fail) {
                            if (!$this->isValidCpf($value)) {
                                $fail('CPF inválido. Verifique os números digitados.');
                            }
                        },
                    ],
                    'birthdate' => ['required', 'date_format:Y-m-d'],
                ]);

                $cpf = preg_replace('/\D/', '', $payload['cpf']);
                $birthdate = $payload['birthdate'];
                $response = $client->cpf($cpf, $birthdate, false);
                $service = 'receita-federal/cpf';
                $key = $birthdate ? $cpf . '|' . $birthdate : $cpf;
                $details = $this->extractCpfDetails($response['data'][0] ?? []);
                break;

            case 'cnpj':
                $payload = $request->validate([
                    'cnpj' => [
                        'required',
                        'string',
                        function ($attribute, $value, $fail) {
                            if (!$this->isValidCnpj($value)) {
                                $fail('CNPJ inválido. Verifique os números digitados.');
                            }
                        },
                    ],
                ]);

                $cnpj = preg_replace('/\D/', '', $payload['cnpj']);
                $response = $client->cnpj($cnpj, false);
                $service = 'receita-federal/cnpj';
                $key = $cnpj;
                $details = $this->extractCnpjDetails($response['data'][0] ?? []);
                break;

            case 'cro':
            default:
                $payload = $request->validate([
                    'state' => ['required', 'string', 'size:2'],
                    'number' => ['required', 'string', 'max:25'],
                ]);

                $uf = Str::upper($payload['state']);
                $numero = preg_replace('/\s+/', '', $payload['number']);
                $response = $client->cro($uf, $numero, false);
                $service = sprintf('cro/%s/cadastro', strtolower($uf));
                $key = $numero;
                $details = $this->extractCroDetails($response['data'][0] ?? []);
                break;
        }

        $code = Arr::get($response, 'code');
        $message = Arr::get($response, 'code_message') ?? 'Consulta concluída.';

        return response()->json([
            'success' => $code === 200,
            'code' => $code,
            'message' => $message,
            'details' => $details,
            'data' => Arr::get($response, 'data'),
            'header' => Arr::get($response, 'header'),
            'errors' => Arr::wrap(Arr::get($response, 'errors')),
            'raw' => $response,
            'service' => $service,
            'key' => $key,
            'cache' => $this->cacheMetadata($service, $key),
            'history' => $this->formatHistory(),
        ]);
    }

    public function clearHistory(Request $request)
    {
        $this->ensureAdminOrFinance();

        InfosimplesCache::query()->delete();

        $perPage = $this->sanitizePerPage($request->query('per_page'));
        $search = (string) $request->query('search', '');

        return response()->json([
            'message' => 'Histórico limpo com sucesso.',
            'history' => $this->formatHistory(1, $perPage, $search),
        ]);
    }

    public function history(Request $request)
    {
        $this->ensureAdminOrFinance();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = $this->sanitizePerPage($request->query('per_page'));
        $search = (string) $request->query('search', '');

        return response()->json($this->formatHistory($page, $perPage, $search));
    }

    protected function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    protected function ensureAdminOrFinance(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'finance'], true), 403);
    }

    protected function cacheOptions(): array
    {
        return [
            ['value' => 1, 'label' => '1 mês (30 dias)'],
            ['value' => 2, 'label' => '2 meses (60 dias)'],
            ['value' => 3, 'label' => '3 meses (90 dias)'],
            ['value' => 4, 'label' => '4 meses (120 dias)'],
            ['value' => 5, 'label' => '5 meses (150 dias)'],
            ['value' => 6, 'label' => '6 meses (180 dias)'],
        ];
    }

    protected function timeoutOptions(): array
    {
        return [
            ['value' => 30, 'label' => '30 segundos'],
            ['value' => 60, 'label' => '60 segundos'],
            ['value' => 120, 'label' => '120 segundos'],
            ['value' => 180, 'label' => '180 segundos'],
            ['value' => 300, 'label' => '300 segundos'],
            ['value' => 600, 'label' => '600 segundos'],
        ];
    }

    protected function sanitizePerPage($value): int
    {
        $allowed = [20, 50, 100];
        $perPage = (int) $value;

        if (!in_array($perPage, $allowed, true)) {
            $perPage = 20;
        }

        return $perPage;
    }

    protected function formatHistory(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        $query = InfosimplesCache::query()->latest();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('key_value', 'like', '%' . $search . '%')
                    ->orWhere('service', 'like', '%' . $search . '%');
            });
        }

        $paginator = $query
            ->paginate($perPage, ['*'], 'page', $page);

        $collection = $paginator->getCollection()->map(function (InfosimplesCache $cache) {
            $payload = $cache->response ?? [];
            $type = $this->resolveHistoryType($cache->service);
            $details = null;
            $code = (int) ($cache->code ?? Arr::get($payload, 'code'));
            $message = Arr::get($payload, 'code_message');
            $errors = Arr::wrap(Arr::get($payload, 'errors'));

            if ($type && isset($payload['data'][0]) && is_array($payload['data'][0])) {
                $data = $payload['data'][0];

                switch ($type) {
                    case 'cpf':
                        $details = $this->extractCpfDetails($data);
                        break;
                    case 'cnpj':
                        $details = $this->extractCnpjDetails($data);
                        break;
                    case 'cro':
                        $details = $this->extractCroDetails($data);
                        break;
                }
            }

            return [
                'id' => $cache->id,
                'service' => $cache->service,
                'type' => $type,
                'key' => $cache->key_value,
                'code' => $code ?: null,
                'success' => $code === 200,
                'message' => $message,
                'errors' => $errors,
                'cached_at' => optional($cache->created_at)->format('d/m/Y H:i'),
                'updated_at' => optional($cache->updated_at)->format('d/m/Y H:i'),
                'details' => $details,
                'response' => $payload,
            ];
        });

        return [
            'data' => $collection->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'search' => $search,
            ],
        ];
    }

    protected function resolveHistoryType(string $service): ?string
    {
        if (str_contains($service, 'receita-federal/cpf')) {
            return 'cpf';
        }

        if (str_contains($service, 'receita-federal/cnpj')) {
            return 'cnpj';
        }

        if (str_contains($service, 'cro/')) {
            return 'cro';
        }

        return null;
    }

    protected function cacheMetadata(string $service, string $key): ?array
    {
        $cache = InfosimplesCache::query()
            ->where('service', $service)
            ->where('key_value', $key)
            ->latest()
            ->first();

        if (!$cache) {
            return null;
        }

        $settings = InfosimplesSetting::current();

        return [
            'service' => $cache->service,
            'key' => $cache->key_value,
            'code' => $cache->code,
            'cached_at' => optional($cache->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($cache->updated_at)->format('d/m/Y H:i'),
            'is_fresh' => $cache->isFresh($settings->cache_months),
        ];
    }

    protected function isValidCpf(?string $cpf): bool
    {
        if (!$cpf) {
            return false;
        }

        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1+$/', $cpf)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $digit = 11 - ($sum % 11);
        if ($digit >= 10) {
            $digit = 0;
        }

        if ($digit !== intval($cpf[9])) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $digit = 11 - ($sum % 11);
        if ($digit >= 10) {
            $digit = 0;
        }

        return $digit === intval($cpf[10]);
    }

    protected function isValidCnpj(?string $cnpj): bool
    {
        if (!$cnpj) {
            return false;
        }

        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }

        $length = strlen($cnpj) - 2;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;
        for ($i = $length; $i >= 1; $i--) {
            $sum += intval($numbers[$length - $i]) * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        $result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
        if ($result !== intval($digits[0])) {
            return false;
        }

        $length += 1;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;
        for ($i = $length; $i >= 1; $i--) {
            $sum += intval($numbers[$length - $i]) * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        $result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);

        return $result === intval($digits[1]);
    }

    protected function extractCpfDetails(array $payload): ?array
    {
        return InfosimplesResultFormatter::extractCpfDetails($payload);
    }

    protected function extractCnpjDetails(array $payload): ?array
    {
        return InfosimplesResultFormatter::extractCnpjDetails($payload);
    }

    protected function extractCroDetails(array $payload): ?array
    {
        return InfosimplesResultFormatter::extractCroDetails($payload);
    }
}

