<?php

namespace App\Http\Middleware;

use App\Models\AtendimentoCallcenter;
use App\Models\Clinica;
use App\Models\Medico;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $medico = null;
        $pendingCallCenterCount = 0;

        if ($user && $user->isMedico() && $user->medico_id) {
            $medico = Medico::select([
                'id', 'crm', 'especialidade', 'telefone1', 'telefone2',
                'email1', 'cep', 'endereco', 'numero', 'complemento', 'bairro',
                'cidade', 'uf', 'rodape_receita', 'assinatura_path',
            ])->with(['enderecos', 'linkedUser:id,name,medico_id'])->find($user->medico_id);
        }

        if ($user && $user->isCallcenter()) {
            $pendingCallCenterCount = AtendimentoCallcenter::ativo()
                ->where('status', AtendimentoCallcenter::STATUS_ENTRAR_EM_CONTATO)
                ->count();
        }

        $clinica = null;
        if ($user && $user->isSecretaria() && $user->clinica_id) {
            $clinica = Clinica::select('id', 'nome')->find($user->clinica_id);
        }

        return [
            ...parent::share($request),
            /** Sempre recalculado em cada resposta Inertia; o meta do HTML fica congelado após a 1.ª carga, por isso fetches (ex.: drawer) usam isto. */
            'csrfToken' => fn () => csrf_token(),
            'assetVersion' => fn () => $this->resolveAssetVersion(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'medico_id' => $user->medico_id,
                    'clinica_id' => $user->clinica_id,
                ] : null,
                'medico' => $medico,
                'clinica' => $clinica,
                'pendingCallCenterCount' => $pendingCallCenterCount,
                'tinyEnabled' => (bool) Setting::get('tiny_enabled', false),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'import_result' => fn () => $request->session()->get('import_result'),
                'import_preview' => fn () => $request->session()->get('import_preview'),
            ],
        ];
    }

    /**
     * Query string para cache bust de arquivos em /public (imagens, etc.).
     */
    protected function resolveAssetVersion(): string
    {
        $v = config('app.asset_version');
        if (is_string($v) && $v !== '') {
            return $v;
        }

        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            return (string) filemtime($manifest);
        }

        return '1';
    }
}
