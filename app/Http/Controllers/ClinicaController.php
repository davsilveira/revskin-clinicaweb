<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\Medico;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicaController extends Controller
{
    /**
     * Validate CNPJ.
     */
    private function validateCNPJ(?string $cnpj): bool
    {
        if (!$cnpj) return false;

        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) return false;
        if (preg_match('/^(\d)\1+$/', $cnpj)) return false;

        $length = strlen($cnpj) - 2;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }

        $result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
        if ($result != $digits[0]) return false;

        $length++;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }

        $result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
        if ($result != $digits[1]) return false;

        return true;
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403, 'Acesso restrito a administradores.');

        $query = Clinica::with(['medicos:id,nome,crm'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('cnpj', 'like', "%{$search}%");
                });
            })
            ->when($request->has('ativo'), fn($q) => $q->where('ativo', $request->boolean('ativo')));

        $clinicasQuery = $query->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        // Map database fields to frontend fields
        $clinicas = $clinicasQuery->through(function ($clinica) {
            $clinica->telefone = $clinica->telefone1;
            return $clinica;
        });

        return Inertia::render('Clinicas/Index', [
            'clinicas' => $clinicas,
            'filters' => $request->only(['search', 'ativo']),
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Acesso restrito a administradores.');

        return Inertia::render('Clinicas/Form');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Acesso restrito a administradores.');

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'cnpj' => ['nullable', 'string', 'max:18', function ($attribute, $value, $fail) {
                if ($value && !$this->validateCNPJ($value)) {
                    $fail('O CNPJ informado é inválido.');
                }
            }],
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'anotacoes' => 'nullable|string',
            'ativo' => 'boolean',
            'medico_ids' => 'nullable|array',
            'medico_ids.*' => 'exists:medicos,id',
        ]);

        $medicoIds = $validated['medico_ids'] ?? [];
        unset($validated['medico_ids']);

        // Map frontend field to database field
        $clinicaData = $validated;
        $clinicaData['telefone1'] = $validated['telefone'] ?? null;
        unset($clinicaData['telefone']);

        $clinica = Clinica::create($clinicaData);

        // Sync medicos
        if (!empty($medicoIds)) {
            $clinica->medicos()->sync($medicoIds);
        }

        return redirect()->route('clinicas.index')
            ->with('success', 'Clínica cadastrada com sucesso!');
    }

    public function show(Clinica $clinica): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Acesso restrito a administradores.');

        $clinica->load(['medicos' => fn($q) => $q->ativo()->orderBy('nome')]);

        return Inertia::render('Clinicas/Show', [
            'clinica' => $clinica,
        ]);
    }

    public function edit(Clinica $clinica): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Acesso restrito a administradores.');

        return Inertia::render('Clinicas/Form', [
            'clinica' => $clinica,
        ]);
    }

    public function update(Request $request, Clinica $clinica)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Acesso restrito a administradores.');

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'cnpj' => ['nullable', 'string', 'max:18', function ($attribute, $value, $fail) {
                if ($value && !$this->validateCNPJ($value)) {
                    $fail('O CNPJ informado é inválido.');
                }
            }],
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'anotacoes' => 'nullable|string',
            'ativo' => 'boolean',
            'medico_ids' => 'nullable|array',
            'medico_ids.*' => 'exists:medicos,id',
        ]);

        $medicoIds = $validated['medico_ids'] ?? [];
        unset($validated['medico_ids']);

        // Map frontend field to database field
        $clinicaData = $validated;
        $clinicaData['telefone1'] = $validated['telefone'] ?? null;
        unset($clinicaData['telefone']);

        $clinica->update($clinicaData);

        // Sync medicos
        $clinica->medicos()->sync($medicoIds);

        return redirect()->route('clinicas.index')
            ->with('success', 'Clínica atualizada com sucesso!');
    }

    public function destroy(Clinica $clinica)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Acesso restrito a administradores.');

        $clinica->update(['ativo' => false]);

        return redirect()->route('clinicas.index')
            ->with('success', 'Clínica desativada com sucesso!');
    }
}










