<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\Medico;
use App\Models\MedicoEndereco;
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
        $query = Medico::with(['clinica:id,nome', 'clinicas:id,nome', 'enderecos'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('crm', 'like', "%{$search}%")
                        ->orWhere('cpf', 'like', "%{$search}%");
                });
            })
            ->when($request->clinica_id, fn($q, $clinicaId) => $q->where('clinica_id', $clinicaId))
            ->when($request->has('ativo'), fn($q) => $q->where('ativo', $request->boolean('ativo')));

        $medicosQuery = $query->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        // Map database fields to frontend fields
        $medicos = $medicosQuery->through(function ($medico) {
            $medico->email = $medico->email1;
            $medico->telefone = $medico->telefone1;
            $medico->celular = $medico->telefone2;
            return $medico;
        });

        $clinicas = Clinica::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Medicos/Index', [
            'medicos' => $medicos,
            'clinicas' => $clinicas,
            'filters' => $request->only(['search', 'clinica_id', 'ativo']),
            'isAdmin' => $request->user()->isAdmin(),
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
            'uf_crm' => 'nullable|string|max:2',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
            'especialidade' => 'nullable|string|max:255',
            'clinica_ids' => 'nullable|array',
            'clinica_ids.*' => 'exists:clinicas,id',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'rodape_receita' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'ativo' => 'boolean',
            'assinatura' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remover_assinatura' => 'nullable|boolean',
            'enderecos' => 'nullable|array',
            'enderecos.*.nome' => 'required|string|max:100',
            'enderecos.*.cep' => 'nullable|string|max:10',
            'enderecos.*.endereco' => 'nullable|string|max:255',
            'enderecos.*.numero' => 'nullable|string|max:20',
            'enderecos.*.complemento' => 'nullable|string|max:255',
            'enderecos.*.bairro' => 'nullable|string|max:255',
            'enderecos.*.cidade' => 'nullable|string|max:255',
            'enderecos.*.uf' => 'nullable|string|max:2',
        ]);

        $enderecos = $validated['enderecos'] ?? [];
        $clinicaIds = $validated['clinica_ids'] ?? [];
        unset($validated['enderecos'], $validated['clinica_ids']);

        // Map frontend field names to database field names
        $medicoData = [
            'nome' => $validated['nome'],
            'apelido' => $validated['apelido'] ?? null,
            'crm' => $validated['crm'] ?? null,
            'uf_crm' => $validated['uf_crm'] ?? null,
            'cpf' => $validated['cpf'] ?? null,
            'rg' => $validated['rg'] ?? null,
            'especialidade' => $validated['especialidade'] ?? null,
            'email1' => $validated['email'] ?? null,
            'telefone1' => $validated['telefone'] ?? null,
            'telefone2' => $validated['celular'] ?? null,
            'rodape_receita' => $validated['rodape_receita'] ?? null,
            'anotacoes' => $validated['anotacoes'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
        ];

        $medico = Medico::create($medicoData);

        // Sync clinicas
        if (!empty($clinicaIds)) {
            $medico->clinicas()->sync($clinicaIds);
        }

        // Handle signature upload
        if ($request->hasFile('assinatura')) {
            $path = $request->file('assinatura')->store('assinaturas', 'public');
            $medico->update(['assinatura_path' => $path]);
        }

        // Save enderecos
        foreach ($enderecos as $index => $endereco) {
            $medico->enderecos()->create([
                ...$endereco,
                'principal' => $index === 0,
            ]);
        }

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
            'uf_crm' => 'nullable|string|max:2',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
            'especialidade' => 'nullable|string|max:255',
            'clinica_ids' => 'nullable|array',
            'clinica_ids.*' => 'exists:clinicas,id',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'rodape_receita' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'ativo' => 'boolean',
            'assinatura' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remover_assinatura' => 'nullable|boolean',
            'enderecos' => 'nullable|array',
            'enderecos.*.nome' => 'required|string|max:100',
            'enderecos.*.cep' => 'nullable|string|max:10',
            'enderecos.*.endereco' => 'nullable|string|max:255',
            'enderecos.*.numero' => 'nullable|string|max:20',
            'enderecos.*.complemento' => 'nullable|string|max:255',
            'enderecos.*.bairro' => 'nullable|string|max:255',
            'enderecos.*.cidade' => 'nullable|string|max:255',
            'enderecos.*.uf' => 'nullable|string|max:2',
        ]);

        $enderecos = $validated['enderecos'] ?? [];
        $clinicaIds = $validated['clinica_ids'] ?? [];
        unset($validated['enderecos'], $validated['clinica_ids']);

        // Map frontend field names to database field names
        $medicoData = [
            'nome' => $validated['nome'],
            'apelido' => $validated['apelido'] ?? null,
            'crm' => $validated['crm'] ?? null,
            'uf_crm' => $validated['uf_crm'] ?? null,
            'cpf' => $validated['cpf'] ?? null,
            'rg' => $validated['rg'] ?? null,
            'especialidade' => $validated['especialidade'] ?? null,
            'email1' => $validated['email'] ?? null,
            'telefone1' => $validated['telefone'] ?? null,
            'telefone2' => $validated['celular'] ?? null,
            'rodape_receita' => $validated['rodape_receita'] ?? null,
            'anotacoes' => $validated['anotacoes'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
        ];

        $medico->update($medicoData);

        // Sync clinicas
        $medico->clinicas()->sync($clinicaIds);

        // Handle signature removal
        if ($request->boolean('remover_assinatura')) {
            if ($medico->assinatura_path) {
                Storage::disk('public')->delete($medico->assinatura_path);
            }
            $medico->update(['assinatura_path' => null]);
        }
        // Handle signature upload
        elseif ($request->hasFile('assinatura')) {
            // Delete old signature if exists
            if ($medico->assinatura_path) {
                Storage::disk('public')->delete($medico->assinatura_path);
            }
            $path = $request->file('assinatura')->store('assinaturas', 'public');
            $medico->update(['assinatura_path' => $path]);
        }

        // Sync enderecos
        $medico->enderecos()->delete();
        foreach ($enderecos as $index => $endereco) {
            $medico->enderecos()->create([
                ...$endereco,
                'principal' => $index === 0,
            ]);
        }

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










