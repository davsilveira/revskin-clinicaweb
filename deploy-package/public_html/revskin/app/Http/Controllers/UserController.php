<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        // Only admins can access this
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Acesso não autorizado.');
        }

        $users = User::with('clinica:id,nome')->orderBy('created_at', 'desc')->get();
        $clinicas = Clinica::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'clinicas' => $clinicas,
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'medico', 'callcenter', 'secretaria'])],
            'clinica_id' => ['nullable', 'exists:clinicas,id'],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'role.required' => 'O perfil é obrigatório.',
            'role.in' => 'O perfil selecionado é inválido.',
            'clinica_id.required' => 'A clínica é obrigatória para o perfil Secretária.',
        ]);

        if ($validated['role'] === 'secretaria' && empty($validated['clinica_id'])) {
            return redirect()->back()->withErrors(['clinica_id' => 'A clínica é obrigatória para o perfil Secretária.'])->withInput();
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'clinica_id' => $validated['role'] === 'secretaria' ? $validated['clinica_id'] : null,
            'is_active' => true,
        ];
        User::create($payload);

        return redirect()->back()->with('success', 'Usuário criado com sucesso!');
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Acesso não autorizado.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'medico', 'callcenter', 'secretaria'])],
            'clinica_id' => ['nullable', 'exists:clinicas,id'],
            'is_active' => 'required|boolean',
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'role.required' => 'O perfil é obrigatório.',
            'role.in' => 'O perfil selecionado é inválido.',
        ]);

        if ($validated['role'] === 'secretaria' && empty($validated['clinica_id'])) {
            return redirect()->back()->withErrors(['clinica_id' => 'A clínica é obrigatória para o perfil Secretária.'])->withInput();
        }

        $validated['clinica_id'] = $validated['role'] === 'secretaria' ? ($validated['clinica_id'] ?? null) : null;
        $user->update($validated);

        // Update password if provided
        if ($request->filled('password')) {
            $request->validate([
                'password' => 'string|min:8',
            ], [
                'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            ]);

            $user->update([
                'password' => Hash::make($request->password),
            ]);
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

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'Você não pode excluir sua própria conta.']);
        }

        $user->delete();

        return redirect()->back()->with('success', 'Usuário excluído com sucesso!');
    }
}

