<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicaController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Clinica::query()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('cnpj', 'like', "%{$search}%");
                });
            })
            ->when($request->has('ativo'), fn($q) => $q->where('ativo', $request->boolean('ativo')));

        $clinicas = $query->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

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
            'telefone1' => 'nullable|string|max:20',
            'telefone2' => 'nullable|string|max:20',
            'telefone3' => 'nullable|string|max:20',
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
        ]);

        Clinica::create($validated);

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
            'telefone1' => 'nullable|string|max:20',
            'telefone2' => 'nullable|string|max:20',
            'telefone3' => 'nullable|string|max:20',
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
        ]);

        $clinica->update($validated);

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




