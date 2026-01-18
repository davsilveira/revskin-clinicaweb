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
     * Validate CPF digits.
     */
    private function validateCpfDigits(?string $cpf): bool
    {
        if (!$cpf) {
            return true; // CPF is optional
        }
        
        $cleanCpf = preg_replace('/\D/', '', $cpf);
        
        if (strlen($cleanCpf) !== 11) {
            return false;
        }
        
        // Check if all digits are the same
        if (preg_match('/^(\d)\1+$/', $cleanCpf)) {
            return false;
        }
        
        // Validate first digit
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cleanCpf[$i] * (10 - $i);
        }
        $digit = 11 - ($sum % 11);
        if ($digit >= 10) {
            $digit = 0;
        }
        if ($digit !== (int)$cleanCpf[9]) {
            return false;
        }
        
        // Validate second digit
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$cleanCpf[$i] * (11 - $i);
        }
        $digit = 11 - ($sum % 11);
        if ($digit >= 10) {
            $digit = 0;
        }
        if ($digit !== (int)$cleanCpf[10]) {
            return false;
        }
        
        return true;
    }

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
            ->when($request->medico_id, fn($q, $medicoId) => $q->where('medico_id', $medicoId));

        // Filter by ativo status - defaults to true (active) if not specified
        if ($request->has('ativo') && $request->ativo !== '' && $request->ativo !== null) {
            $query->where('ativo', $request->boolean('ativo'));
        } elseif (!$request->has('ativo')) {
            // Default to showing only active patients when accessing page directly
            $query->where('ativo', true);
        }
        // When ativo='' (empty string), show all patients (no filter applied)

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
            'cpf' => 'nullable|string|max:14|unique:pacientes,cpf',
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
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
        ]);

        // Validate CPF digits
        if (!$this->validateCpfDigits($validated['cpf'] ?? null)) {
            return back()->withErrors(['cpf' => 'CPF inválido. Por favor, verifique os números digitados.'])->withInput();
        }

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
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('pacientes', 'cpf')->ignore($paciente->id)],
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
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
        ]);

        // Validate CPF digits
        if (!$this->validateCpfDigits($validated['cpf'] ?? null)) {
            return back()->withErrors(['cpf' => 'CPF inválido. Por favor, verifique os números digitados.'])->withInput();
        }

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
     * Autosave - Store or update without redirect (for AJAX autosave).
     */
    public function autosave(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:pacientes,id',
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

        // Validate CPF if provided
        if (!empty($validated['cpf']) && !$this->validateCpfDigits($validated['cpf'])) {
            return response()->json(['error' => 'CPF inválido'], 422);
        }

        // Auto-assign medico if user is medico
        $user = $request->user();
        if ($user->isMedico() && $user->medico_id) {
            $validated['medico_id'] = $user->medico_id;
        }

        $id = $validated['id'] ?? null;
        unset($validated['id']);

        if ($id) {
            $paciente = Paciente::findOrFail($id);
            
            // Check access
            if (!$user->canAccessPaciente($paciente)) {
                return response()->json(['error' => 'Acesso não autorizado'], 403);
            }
            
            $paciente->update($validated);
        } else {
            $paciente = Paciente::create($validated);
        }

        return response()->json([
            'success' => true,
            'id' => $paciente->id,
            'saved_at' => now()->toISOString(),
        ]);
    }

    /**
     * Lookup address by CEP using ViaCEP API.
     */
    public function buscarCep(string $cep)
    {
        $cep = preg_replace('/\D/', '', $cep);

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
                    'success' => true,
                    'data' => [
                        'logradouro' => $data['logradouro'] ?? '',
                        'bairro' => $data['bairro'] ?? '',
                        'localidade' => $data['localidade'] ?? '',
                        'uf' => $data['uf'] ?? '',
                        'complemento' => $data['complemento'] ?? '',
                    ],
                ]);
            }

            return response()->json(['error' => 'Erro ao consultar CEP'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao consultar CEP: ' . $e->getMessage()], 500);
        }
    }
}




