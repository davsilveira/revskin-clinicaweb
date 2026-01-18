<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Show the profile page
     */
    public function show()
    {
        $user = auth()->user();
        $medico = null;

        if ($user->isMedico() && $user->medico_id) {
            $medico = Medico::with('clinica:id,nome')->find($user->medico_id);
        }

        return Inertia::render('Profile/Show', [
            'medico' => $medico,
        ]);
    }

    /**
     * Update profile information
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Perfil atualizado com sucesso!');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'A senha atual é obrigatória.',
            'password.required' => 'A nova senha é obrigatória.',
            'password.min' => 'A nova senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        // Check current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'A senha atual está incorreta.']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', 'Senha atualizada com sucesso!');
    }

    /**
     * Update medico profile (for medico users)
     */
    public function updateMedico(Request $request)
    {
        $user = auth()->user();

        if (!$user->isMedico() || !$user->medico_id) {
            abort(403, 'Acesso não autorizado.');
        }

        $medico = Medico::findOrFail($user->medico_id);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'crm' => 'nullable|string|max:20',
            'especialidade' => 'nullable|string|max:255',
            'telefone1' => 'nullable|string|max:20',
            'telefone2' => 'nullable|string|max:20',
            'email1' => 'nullable|email|max:255',
            'cep' => 'nullable|string|max:10',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'rodape_receita' => 'nullable|string',
        ]);

        $medico->update($validated);

        // Also update user name to match
        $user->update(['name' => $validated['nome']]);

        return redirect()->back()->with('success', 'Dados profissionais atualizados com sucesso!');
    }

    /**
     * Upload medico signature (for medico users)
     */
    public function uploadAssinatura(Request $request)
    {
        $user = auth()->user();

        if (!$user->isMedico() || !$user->medico_id) {
            abort(403, 'Acesso não autorizado.');
        }

        $request->validate([
            'assinatura' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $medico = Medico::findOrFail($user->medico_id);

        // Delete old signature if exists
        if ($medico->assinatura_path) {
            Storage::disk('public')->delete($medico->assinatura_path);
        }

        // Store new signature
        $path = $request->file('assinatura')->store('assinaturas', 'public');
        $medico->update(['assinatura_path' => $path]);

        return back()->with('success', 'Assinatura atualizada com sucesso!');
    }
}

