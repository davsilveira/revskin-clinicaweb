<?php

namespace App\Http\Controllers;

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

        $users = User::orderBy('created_at', 'desc')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
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
            'role' => ['required', Rule::in(['admin', 'user'])],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'role.required' => 'O perfil é obrigatório.',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
        ]);

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
            'role' => ['required', Rule::in(['admin', 'user'])],
            'is_active' => 'required|boolean',
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'role.required' => 'O perfil é obrigatório.',
        ]);

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

