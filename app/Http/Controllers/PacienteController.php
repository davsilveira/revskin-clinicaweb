<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PacienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Paciente::with(['medico:id,nome'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('cpf', 'like', "%{$search}%")
                        ->orWhere('telefone1', 'like', "%{$search}%")
                        ->orWhere('email1', 'like', "%{$search}%");
                });
            })
            ->when($request->medico_id, fn($q, $medicoId) => $q->where('medico_id', $medicoId))
            ->when($request->has('ativo'), fn($q) => $q->where('ativo', $request->boolean('ativo')));

        // Filter by user access
        $user = $request->user();
        if ($user->isMedico() && $user->medico_id) {
            $query->where('medico_id', $user->medico_id);
        }

        $pacientes = $query->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Pacientes/Index', [
            'pacientes' => $pacientes,
            'medicos' => $medicos,
            'filters' => $request->only(['search', 'medico_id', 'ativo']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Pacientes/Form', [
            'medicos' => $medicos,
            'sexoOptions' => ['Masculino', 'Feminino', 'Outro'],
            'fototipoOptions' => ['I', 'II', 'III', 'IV', 'V', 'VI'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'nullable|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
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
            'indicado_por' => 'nullable|string|max:255',
            'anotacoes' => 'nullable|string',
            'medico_id' => 'nullable|exists:medicos,id',
            'ativo' => 'boolean',
        ]);

        // Auto-assign medico if user is medico
        $user = $request->user();
        if ($user->isMedico() && $user->medico_id) {
            $validated['medico_id'] = $user->medico_id;
        }

        Paciente::create($validated);

        return redirect()->route('pacientes.index')
            ->with('success', 'Paciente cadastrado com sucesso!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Paciente $paciente): Response
    {
        $paciente->load(['medico:id,nome', 'receitas' => function ($q) {
            $q->with('medico:id,nome')->orderByDesc('data_receita')->limit(10);
        }]);

        return Inertia::render('Pacientes/Show', [
            'paciente' => $paciente,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Paciente $paciente): Response
    {
        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Pacientes/Form', [
            'paciente' => $paciente,
            'medicos' => $medicos,
            'sexoOptions' => ['Masculino', 'Feminino', 'Outro'],
            'fototipoOptions' => ['I', 'II', 'III', 'IV', 'V', 'VI'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Paciente $paciente)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'nullable|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
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
            'indicado_por' => 'nullable|string|max:255',
            'anotacoes' => 'nullable|string',
            'medico_id' => 'nullable|exists:medicos,id',
            'ativo' => 'boolean',
        ]);

        $paciente->update($validated);

        return redirect()->route('pacientes.index')
            ->with('success', 'Paciente atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Paciente $paciente)
    {
        // Soft delete by setting ativo to false
        $paciente->update(['ativo' => false]);

        return redirect()->route('pacientes.index')
            ->with('success', 'Paciente desativado com sucesso!');
    }

    /**
     * Search pacientes for autocomplete.
     */
    public function search(Request $request)
    {
        $search = $request->get('q', '');

        $query = Paciente::ativo()
            ->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%");
            });

        // Filter by user access
        $user = $request->user();
        if ($user->isMedico() && $user->medico_id) {
            $query->where('medico_id', $user->medico_id);
        }

        $pacientes = $query->orderBy('nome')
            ->limit(20)
            ->get(['id', 'nome', 'cpf', 'telefone1']);

        return response()->json($pacientes);
    }

    /**
     * Lookup address by CEP using ViaCEP API.
     */
    public function buscarCep(Request $request)
    {
        $cep = preg_replace('/\D/', '', $request->get('cep', ''));

        if (strlen($cep) !== 8) {
            return response()->json(['error' => 'CEP inválido'], 422);
        }

        try {
            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['erro'])) {
                    return response()->json(['error' => 'CEP não encontrado'], 404);
                }

                return response()->json([
                    'endereco' => $data['logradouro'] ?? '',
                    'bairro' => $data['bairro'] ?? '',
                    'cidade' => $data['localidade'] ?? '',
                    'uf' => $data['uf'] ?? '',
                    'complemento' => $data['complemento'] ?? '',
                ]);
            }

            return response()->json(['error' => 'Erro ao consultar CEP'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao consultar CEP: ' . $e->getMessage()], 500);
        }
    }
}

