<?php

namespace App\Http\Middleware;

use App\Models\Medico;
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

        if ($user && $user->isMedico() && $user->medico_id) {
            $medico = Medico::select([
                'id', 'nome', 'crm', 'especialidade', 'telefone1', 'telefone2',
                'email1', 'cep', 'endereco', 'numero', 'complemento', 'bairro',
                'cidade', 'uf', 'rodape_receita', 'assinatura_path'
            ])->find($user->medico_id);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'medico_id' => $user->medico_id,
                ] : null,
                'medico' => $medico,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

