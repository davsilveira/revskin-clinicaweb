<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MedicoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Medico::with(['clinica:id,nome'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('crm', 'like', "%{$search}%")
                        ->orWhere('cpf', 'like', "%{$search}%");
                });
            })
            ->when($request->clinica_id, fn($q, $clinicaId) => $q->where('clinica_id', $clinicaId))
            ->when($request->has('ativo'), fn($q) => $q->where('ativo', $request->boolean('ativo')));

        $medicos = $query->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        $clinicas = Clinica::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Medicos/Index', [
            'medicos' => $medicos,
            'clinicas' => $clinicas,
            'filters' => $request->only(['search', 'clinica_id', 'ativo']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $clinicas = Clinica::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Medicos/Form', [
            'clinicas' => $clinicas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'apelido' => 'nullable|string|max:255',
            'crm' => 'nullable|string|max:20',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
            'especialidade' => 'nullable|string|max:255',
            'clinica_id' => 'nullable|exists:clinicas,id',
            'telefone1' => 'nullable|string|max:20',
            'telefone2' => 'nullable|string|max:20',
            'telefone3' => 'nullable|string|max:20',
            'email1' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'tipo_endereco' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'rodape_receita' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'ativo' => 'boolean',
        ]);

        Medico::create($validated);

        return redirect()->route('medicos.index')
            ->with('success', 'Médico cadastrado com sucesso!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Medico $medico): Response
    {
        $medico->load(['clinica:id,nome', 'pacientes' => function ($q) {
            $q->ativo()->orderBy('nome')->limit(10);
        }]);

        return Inertia::render('Medicos/Show', [
            'medico' => $medico,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Medico $medico): Response
    {
        $clinicas = Clinica::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Medicos/Form', [
            'medico' => $medico,
            'clinicas' => $clinicas,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Medico $medico)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'apelido' => 'nullable|string|max:255',
            'crm' => 'nullable|string|max:20',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
            'especialidade' => 'nullable|string|max:255',
            'clinica_id' => 'nullable|exists:clinicas,id',
            'telefone1' => 'nullable|string|max:20',
            'telefone2' => 'nullable|string|max:20',
            'telefone3' => 'nullable|string|max:20',
            'email1' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'tipo_endereco' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'rodape_receita' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'ativo' => 'boolean',
        ]);

        $medico->update($validated);

        return redirect()->route('medicos.index')
            ->with('success', 'Médico atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Medico $medico)
    {
        $medico->update(['ativo' => false]);

        return redirect()->route('medicos.index')
            ->with('success', 'Médico desativado com sucesso!');
    }

    /**
     * Upload signature image.
     */
    public function uploadAssinatura(Request $request, Medico $medico)
    {
        $request->validate([
            'assinatura' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Delete old signature if exists
        if ($medico->assinatura_path) {
            Storage::disk('public')->delete($medico->assinatura_path);
        }

        // Store new signature
        $path = $request->file('assinatura')->store('assinaturas', 'public');
        $medico->update(['assinatura_path' => $path]);

        return back()->with('success', 'Assinatura atualizada com sucesso!');
    }

    /**
     * Search medicos for autocomplete.
     */
    public function search(Request $request)
    {
        $search = $request->get('q', '');

        $medicos = Medico::ativo()
            ->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('crm', 'like', "%{$search}%");
            })
            ->orderBy('nome')
            ->limit(20)
            ->get(['id', 'nome', 'crm', 'especialidade']);

        return response()->json($medicos);
    }
}










