<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\Medico;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicaController extends Controller
{
    public function index(Request $request): Response
    {
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
        return Inertia::render('Clinicas/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
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
        $clinica->load(['medicos' => fn($q) => $q->ativo()->orderBy('nome')]);

        return Inertia::render('Clinicas/Show', [
            'clinica' => $clinica,
        ]);
    }

    public function edit(Clinica $clinica): Response
    {
        return Inertia::render('Clinicas/Form', [
            'clinica' => $clinica,
        ]);
    }

    public function update(Request $request, Clinica $clinica)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
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
        $clinica->update(['ativo' => false]);

        return redirect()->route('clinicas.index')
            ->with('success', 'Clínica desativada com sucesso!');
    }
}










