<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\Setting;
use App\Models\User;
use App\Services\MedicoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(
        private MedicoService $medicoService
    ) {}

    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Acesso não autorizado.');
        }

        $search = $request->search;
        if (in_array($search, ['undefined', 'null', ''], true)) {
            $search = null;
        }
        if (is_string($search)) {
            $search = trim($search);
            if ($search === '') {
                $search = null;
            }
        }

        $allowedRoles = array_keys(User::getRoles());
        $role = $request->input('role');
        if (! is_string($role) || ! in_array($role, $allowedRoles, true)) {
            $role = null;
        }

        $users = User::with(['clinica:id,nome', 'medico.linkedUser:id,name,medico_id', 'medico.enderecos', 'medico.clinicas'])
            ->when($search, function ($q, string $s) {
                $q->where(function ($query) use ($s) {
                    $query->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->when($role, fn ($q) => $q->where('role', $role))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $clinicas = Clinica::ativo()->orderBy('nome')->get(['id', 'nome']);

        $filters = array_filter([
            'search' => $search,
            'role' => $role,
        ], fn ($v) => $v !== null && $v !== '');

        return Inertia::render('Users/Index', [
            'users' => $users,
            'clinicas' => $clinicas,
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Acesso não autorizado.');
        }

        $allowedRoles = ['admin', 'medico', 'secretaria'];
        if (! Setting::get('tiny_enabled', false)) {
            $allowedRoles[] = 'callcenter';
        }

        $userRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in($allowedRoles)],
            'clinica_id' => ['nullable', 'exists:clinicas,id'],
        ];

        $isMedico = $request->input('role') === 'medico';

        if (Setting::get('tiny_enabled', false) && $request->input('role') === 'callcenter') {
            return redirect()->back()->withErrors(['role' => 'Não é possível cadastrar usuários Call Center quando a integração Tiny está ativa.'])->withInput();
        }

        if ($isMedico) {
            $userRules = array_merge($userRules, MedicoService::validationRules());
            unset($userRules['medico_id']);
        }

        $messages = [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'role.required' => 'O perfil é obrigatório.',
            'role.in' => 'O perfil selecionado é inválido.',
            'crm.required' => 'O CRM é obrigatório.',
            'uf_crm.required' => 'A UF do CRM é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
        ];

        $validated = $request->validate($userRules, $messages);

        if ($validated['role'] === 'secretaria' && empty($validated['clinica_id'])) {
            return redirect()->back()->withErrors(['clinica_id' => 'A clínica é obrigatória para o perfil Secretária.'])->withInput();
        }

        if ($isMedico) {
            $medico = $this->medicoService->create($validated, $request);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_MEDICO,
                'medico_id' => $medico->id,
                'is_active' => true,
            ]);

            $message = 'Usuário médico criado com sucesso!';
        } else {
            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'clinica_id' => $validated['role'] === 'secretaria' ? $validated['clinica_id'] : null,
                'is_active' => true,
            ]);
            $message = 'Usuário criado com sucesso!';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Acesso não autorizado.');
        }

        $tinyEnabled = Setting::get('tiny_enabled', false);
        $allowedRoles = ['admin', 'medico', 'secretaria'];
        if (! $tinyEnabled) {
            $allowedRoles[] = 'callcenter';
        } elseif ($user->role === 'callcenter') {
            // Permitir manter callcenter ao editar usuário existente (ex: alterar nome), mas não atribuir a novos
            $allowedRoles[] = 'callcenter';
        }

        $userRules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in($allowedRoles)],
            'clinica_id' => ['nullable', 'exists:clinicas,id'],
            'is_active' => 'required|boolean',
        ];

        $isMedico = $request->input('role') === 'medico';

        // Bloquear atribuição de callcenter quando Tiny ativo (exceto manter em edição de usuário já callcenter)
        if ($tinyEnabled && $request->input('role') === 'callcenter' && $user->role !== 'callcenter') {
            return redirect()->back()->withErrors(['role' => 'Não é possível cadastrar usuários Call Center quando a integração Tiny está ativa.'])->withInput();
        }

        if ($isMedico) {
            $userRules = array_merge($userRules, MedicoService::validationRules());
            unset($userRules['medico_id']);
        }

        $messages = [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'role.required' => 'O perfil é obrigatório.',
            'role.in' => 'O perfil selecionado é inválido.',
            'crm.required' => 'O CRM é obrigatório.',
            'uf_crm.required' => 'A UF do CRM é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
        ];

        $validated = $request->validate($userRules, $messages);

        if ($validated['role'] === 'secretaria' && empty($validated['clinica_id'])) {
            return redirect()->back()->withErrors(['clinica_id' => 'A clínica é obrigatória para o perfil Secretária.'])->withInput();
        }

        if ($isMedico) {
            if ($user->medico_id) {
                $medico = $user->medico;
                $this->medicoService->update($medico, $validated, $request);
            } else {
                $medico = $this->medicoService->create($validated, $request);
                $validated['medico_id'] = $medico->id;
            }

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => User::ROLE_MEDICO,
                'medico_id' => $user->medico_id ?? $medico->id,
                'clinica_id' => null,
                'is_active' => $validated['is_active'],
            ]);
        } else {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'medico_id' => null,
                'clinica_id' => $validated['role'] === 'secretaria' ? ($validated['clinica_id'] ?? null) : null,
                'is_active' => $validated['is_active'],
            ]);
        }

        if ($request->filled('password')) {
            $request->validate([
                'password' => 'string|min:8',
            ], [
                'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            ]);
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->back()->with('success', 'Usuário atualizado com sucesso!');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Acesso não autorizado.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'Você não pode excluir sua própria conta.']);
        }

        $user->delete();

        return redirect()->back()->with('success', 'Usuário excluído com sucesso!');
    }
}
