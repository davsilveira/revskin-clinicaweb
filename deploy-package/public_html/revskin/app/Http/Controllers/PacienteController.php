<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\PacienteTelefone;
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
        $query = Paciente::with(['medico:id', 'medico.linkedUser:id,name,medico_id', 'telefones'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('cpf', 'like', "%{$search}%")
                        ->orWhere('telefone1', 'like', "%{$search}%")
                        ->orWhere('celular', 'like', "%{$search}%")
                        ->orWhere('email1', 'like', "%{$search}%")
                        ->orWhereHas('telefones', fn($tq) => $tq->where('numero', 'like', "%{$search}%"));
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
        if ($user->isSecretaria() && $user->clinica_id) {
            $medicoIds = $user->getMedicoIdsDaClinica();
            $query->whereIn('medico_id', $medicoIds);
        } elseif ($user->isSecretaria()) {
            $query->whereRaw('1 = 0'); // sem clínica = lista vazia
        }

        $pacientes = $query->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        $medicos = $this->getMedicosForPacienteForm($request->user());

        return Inertia::render('Pacientes/Index', [
            'pacientes' => $pacientes,
            'medicos' => $medicos,
            'filters' => $request->only(['search', 'medico_id', 'ativo']),
            'tiposTelefone' => PacienteTelefone::getTipos(),
            'isAdmin' => $user->isAdmin(),
            'isSecretaria' => $user->isSecretaria(),
            'canSelectMedico' => !$user->isMedico(),
            'canAccessAssistente' => $user->isAdmin() || $user->isMedico(),
        ]);
    }

    /**
     * Get medicos list for paciente form (filtered by role: all for admin/callcenter, one for medico, clinic for secretária).
     */
    private function getMedicosForPacienteForm($user): \Illuminate\Support\Collection
    {
        if ($user->isSecretaria() && $user->clinica_id) {
            $ids = $user->getMedicoIdsDaClinica();
            return Medico::ativo()->whereIn('medicos.id', $ids)
                ->join('users', 'users.medico_id', '=', 'medicos.id')
                ->orderBy('users.name')
                ->select('medicos.id')
                ->get()
                ->load('linkedUser:id,name,medico_id');
        }
        return Medico::ativo()
            ->join('users', 'users.medico_id', '=', 'medicos.id')
            ->orderBy('users.name')
            ->select('medicos.id')
            ->get()
            ->load('linkedUser:id,name,medico_id');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $medicos = $this->getMedicosForPacienteForm($request->user());

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
        $user = $request->user();
        $medicoRules = $user->isSecretaria() ? ['required', 'exists:medicos,id'] : ['nullable', 'exists:medicos,id'];

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'required|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => 'required|string|max:14|unique:pacientes,cpf',
            'rg' => 'nullable|string|max:20',
            'telefone1' => 'nullable|string|max:20',
            'celular' => 'required|string|max:20',
            'telefone3' => 'nullable|string|max:20',
            'email1' => 'required|email|max:255',
            'email2' => 'nullable|email|max:255',
            'tipo_endereco' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'pais' => 'nullable|string|max:100',
            'cep' => 'nullable|string|max:10',
            'indicado_por' => 'nullable|string|max:255',
            'anotacoes' => 'nullable|string',
            'medico_id' => $medicoRules,
            'ativo' => 'boolean',
            'telefones' => 'nullable|array',
            'telefones.*.numero' => 'required|string|max:30',
            'telefones.*.tipo' => 'required|string|max:50',
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'cpf.required' => 'O CPF é obrigatório.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
            'email1.required' => 'O e-mail é obrigatório.',
            'medico_id.required' => 'Selecione o médico responsável.',
        ]);

        // Validate CPF digits
        if (!$this->validateCpfDigits($validated['cpf'] ?? null)) {
            return back()->withErrors(['cpf' => 'CPF inválido. Por favor, verifique os números digitados.'])->withInput();
        }

        // Auto-assign medico if user is medico
        if ($user->isMedico() && $user->medico_id) {
            $validated['medico_id'] = $user->medico_id;
        }
        // Secretária: medico deve pertencer à clínica (validado como required acima)
        if ($user->isSecretaria() && !empty($validated['medico_id'])) {
            if (!in_array((int) $validated['medico_id'], $user->getMedicoIdsDaClinica(), true)) {
                return back()->withErrors(['medico_id' => 'O médico selecionado não pertence à sua clínica.'])->withInput();
            }
        }

        $telefones = $validated['telefones'] ?? [];
        unset($validated['telefones']);

        $paciente = Paciente::create($validated);

        // Save telefones
        foreach ($telefones as $index => $telefone) {
            $paciente->telefones()->create([
                'numero' => $telefone['numero'],
                'tipo' => $telefone['tipo'],
                'principal' => $index === 0,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'paciente' => $paciente->fresh(['telefones', 'medico']),
            ]);
        }

        return redirect()->route('pacientes.index')
            ->with('success', 'Paciente cadastrado com sucesso!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Paciente $paciente): Response
    {
        $user = $request->user();
        
        // Check if user can access this paciente
        if (!$user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

        $paciente->load(['medico:id', 'medico.linkedUser:id,name,medico_id', 'receitas' => function ($q) {
            $q->with('medico:id', 'medico.linkedUser:id,name,medico_id')->orderByDesc('data_receita')->limit(10);
        }]);

        return Inertia::render('Pacientes/Show', [
            'paciente' => $paciente,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Paciente $paciente): Response
    {
        $user = $request->user();
        
        // Check if user can access this paciente
        if (!$user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

        $medicos = $this->getMedicosForPacienteForm($user);

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
            'data_nascimento' => 'required|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => ['required', 'string', 'max:14', Rule::unique('pacientes', 'cpf')->ignore($paciente->id)],
            'rg' => 'nullable|string|max:20',
            'telefone1' => 'nullable|string|max:20',
            'celular' => 'required|string|max:20',
            'telefone3' => 'nullable|string|max:20',
            'email1' => 'required|email|max:255',
            'email2' => 'nullable|email|max:255',
            'tipo_endereco' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'pais' => 'nullable|string|max:100',
            'cep' => 'nullable|string|max:10',
            'indicado_por' => 'nullable|string|max:255',
            'anotacoes' => 'nullable|string',
            'medico_id' => 'nullable|exists:medicos,id',
            'ativo' => 'boolean',
            'telefones' => 'nullable|array',
            'telefones.*.numero' => 'required|string|max:30',
            'telefones.*.tipo' => 'required|string|max:50',
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'cpf.required' => 'O CPF é obrigatório.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
            'email1.required' => 'O e-mail é obrigatório.',
        ]);

        // Validate CPF digits
        if (!$this->validateCpfDigits($validated['cpf'] ?? null)) {
            return back()->withErrors(['cpf' => 'CPF inválido. Por favor, verifique os números digitados.'])->withInput();
        }

        $user = $request->user();
        // Check if user can access this paciente
        if (!$user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

        // Secretária: medico_id must be from her clinic
        if ($user->isSecretaria() && isset($validated['medico_id']) && $validated['medico_id'] !== null) {
            if (!in_array((int) $validated['medico_id'], $user->getMedicoIdsDaClinica(), true)) {
                return back()->withErrors(['medico_id' => 'O médico selecionado não pertence à sua clínica.'])->withInput();
            }
        }

        // Prevent medico from changing medico_id
        if ($user->isMedico() && $user->medico_id) {
            $validated['medico_id'] = $user->medico_id;
        }

        $telefones = $validated['telefones'] ?? [];
        unset($validated['telefones']);

        $paciente->update($validated);

        // Sync telefones
        $paciente->telefones()->delete();
        foreach ($telefones as $index => $telefone) {
            $paciente->telefones()->create([
                'numero' => $telefone['numero'],
                'tipo' => $telefone['tipo'],
                'principal' => $index === 0,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'paciente' => $paciente->fresh(['telefones', 'medico']),
            ]);
        }

        return redirect()->route('pacientes.index')
            ->with('success', 'Paciente atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Paciente $paciente)
    {
        // Check if user can access this paciente
        $user = $request->user();
        if (!$user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

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
            ->get(['id', 'nome', 'cpf', 'celular']);

        return response()->json($pacientes);
    }

    /**
     * Autosave - Store or update without redirect (for AJAX autosave).
     */
    public function autosave(Request $request)
    {
        $user = $request->user();
        $medicoRules = $user->isSecretaria() ? ['required', 'exists:medicos,id'] : ['nullable', 'exists:medicos,id'];

        $cpfRule = ['nullable', 'string', 'max:14'];
        if ($request->filled('cpf')) {
            if ($request->filled('id')) {
                $cpfRule[] = Rule::unique('pacientes', 'cpf')->ignore($request->input('id'));
            } else {
                $cpfRule[] = 'unique:pacientes,cpf';
            }
        }

        $validated = $request->validate([
            'id' => 'nullable|exists:pacientes,id',
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'nullable|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => $cpfRule,
            'rg' => 'nullable|string|max:20',
            'telefone1' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
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
            'pais' => 'nullable|string|max:100',
            'cep' => 'nullable|string|max:10',
            'indicado_por' => 'nullable|string|max:255',
            'anotacoes' => 'nullable|string',
            'medico_id' => $medicoRules,
            'ativo' => 'boolean',
            'telefones' => 'nullable|array',
            'telefones.*.numero' => 'required|string|max:30',
            'telefones.*.tipo' => 'required|string|max:50',
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'medico_id.required' => 'Selecione o médico responsável.',
        ]);

        // Validate CPF digits if provided
        if (!empty($validated['cpf']) && !$this->validateCpfDigits($validated['cpf'])) {
            return response()->json(['error' => 'CPF inválido'], 422);
        }

        if ($user->isMedico() && $user->medico_id) {
            $validated['medico_id'] = $user->medico_id;
        }
        if ($user->isSecretaria() && !empty($validated['medico_id'])) {
            if (!in_array((int) $validated['medico_id'], $user->getMedicoIdsDaClinica(), true)) {
                return response()->json(['error' => 'O médico selecionado não pertence à sua clínica.'], 422);
            }
        }

        $id = $validated['id'] ?? null;
        $telefones = $validated['telefones'] ?? [];
        unset($validated['id'], $validated['telefones']);

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

        // Sync telefones
        if (!empty($telefones)) {
            $paciente->telefones()->delete();
            foreach ($telefones as $index => $telefone) {
                $paciente->telefones()->create([
                    'numero' => $telefone['numero'],
                    'tipo' => $telefone['tipo'],
                    'principal' => $index === 0,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'id' => $paciente->id,
            'saved_at' => now()->toISOString(),
        ]);
    }

    /**
     * Quick create - Store a new patient via AJAX (for assistant wizard).
     */
    public function quickCreate(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'cpf' => 'required|string|max:14|unique:pacientes,cpf',
            'data_nascimento' => 'required|date',
            'sexo' => 'nullable|string|max:20',
            'email1' => 'required|email|max:255',
            'telefone1' => 'nullable|string|max:20',
            'celular' => 'required|string|max:20',
            'cep' => 'nullable|string|max:10',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'pais' => 'nullable|string|max:100',
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'cpf.required' => 'O CPF é obrigatório.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
            'email1.required' => 'O e-mail é obrigatório.',
        ]);

        // Validate CPF digits
        if (!$this->validateCpfDigits($validated['cpf'] ?? null)) {
            return response()->json(['error' => 'CPF inválido. Por favor, verifique os números digitados.'], 422);
        }

        $user = $request->user();
        if ($user->isMedico() && $user->medico_id) {
            $validated['medico_id'] = $user->medico_id;
        } elseif ($user->isSecretaria()) {
            $medicoIds = $user->getMedicoIdsDaClinica();
            if (!empty($medicoIds)) {
                $validated['medico_id'] = $medicoIds[0];
            }
        }

        $validated['ativo'] = true;

        $paciente = Paciente::create($validated);

        return response()->json([
            'success' => true,
            'paciente' => [
                'id' => $paciente->id,
                'nome' => $paciente->nome,
                'cpf' => $paciente->cpf,
            ],
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




