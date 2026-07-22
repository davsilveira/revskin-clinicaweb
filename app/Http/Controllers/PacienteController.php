<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\PacienteTelefone;
use App\Models\Receita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PacienteController extends Controller
{
    /**
     * Trata Nº Registro vazio como null (evita violação de unique com string vazia).
     */
    private function normalizePacienteCodigo(array &$validated): void
    {
        if (array_key_exists('codigo', $validated) && trim((string) ($validated['codigo'] ?? '')) === '') {
            $validated['codigo'] = null;
        }
    }

    /**
     * Opção 2: remove os campos privados por médico do payload do paciente e devolve-os
     * para gravar no pivot (não são mais colunas compartilhadas de `pacientes`).
     *
     * @return array<string,mixed>
     */
    private function extractPrivados(array &$validated): array
    {
        $privados = [];
        foreach (['anotacoes', 'codigo', 'indicado_por'] as $campo) {
            if (array_key_exists($campo, $validated)) {
                $privados[$campo] = $validated[$campo];
                unset($validated[$campo]);
            }
        }

        return $privados;
    }

    /**
     * Opção 2: sobrepõe nos atributos do paciente os campos privados do vínculo do médico
     * de contexto (médico logado, ou o médico de origem do registro), para exibição.
     */
    private function injectPrivadosDoMedico(Paciente $paciente, $user): void
    {
        $medicoId = ($user->isMedico() && $user->medico_id) ? $user->medico_id : $paciente->medico_id;
        if (! $medicoId) {
            return;
        }

        $pivot = $paciente->vinculoDoMedico((int) $medicoId);
        if ($pivot) {
            $paciente->setAttribute('anotacoes', $pivot->anotacoes);
            $paciente->setAttribute('codigo', $pivot->codigo);
            $paciente->setAttribute('indicado_por', $pivot->indicado_por);
        }
    }

    /**
     * Nº Registro (codigo) é único POR médico (no pivot). Retorna true se já usado por
     * outro vínculo do mesmo médico.
     */
    private function codigoDuplicadoNoMedico(?int $medicoId, ?string $codigo, ?int $ignorePacienteId = null): bool
    {
        if (! $medicoId || $codigo === null || trim((string) $codigo) === '') {
            return false;
        }

        return \App\Models\MedicoPaciente::where('medico_id', $medicoId)
            ->where('codigo', $codigo)
            ->when($ignorePacienteId, fn ($q) => $q->where('paciente_id', '!=', $ignorePacienteId))
            ->exists();
    }

    /**
     * Validate CPF digits.
     */
    private function validateCpfDigits(?string $cpf): bool
    {
        if (! $cpf) {
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
            $sum += (int) $cleanCpf[$i] * (10 - $i);
        }
        $digit = 11 - ($sum % 11);
        if ($digit >= 10) {
            $digit = 0;
        }
        if ($digit !== (int) $cleanCpf[9]) {
            return false;
        }

        // Validate second digit
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $cleanCpf[$i] * (11 - $i);
        }
        $digit = 11 - ($sum % 11);
        if ($digit >= 10) {
            $digit = 0;
        }
        if ($digit !== (int) $cleanCpf[10]) {
            return false;
        }

        return true;
    }

    /**
     * Erro de validação após o validate(): requisições com Accept JSON (fetch no drawer) precisam de 422 JSON.
     * Se devolver only back(), o fetch segue 302, recebe 200 em HTML, e o front trata como "sucesso" falso.
     */
    private function jsonOrBackForFieldError(Request $request, string $field, string $message)
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }

    /**
     * Filtro textual: nome, CPF (com ou sem pontuação), Nº registro (codigo), telefones, e-mail.
     */
    private function applyPacienteTextSearch(Builder $query, string $search): void
    {
        $query->where('nome', 'like', "%{$search}%")
            ->orWhere('cpf', 'like', "%{$search}%")
            ->orWhere('codigo', 'like', "%{$search}%")
            ->orWhere('telefone1', 'like', "%{$search}%")
            ->orWhere('celular', 'like', "%{$search}%")
            ->orWhere('email1', 'like', "%{$search}%")
            ->orWhereHas('telefones', fn ($tq) => $tq->where('numero', 'like', "%{$search}%"));

        $digits = preg_replace('/\D/', '', $search);
        if (strlen($digits) >= 3) {
            $query->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cpf, ''), '.', ''), '-', ''), ' ', ''), '/', '') LIKE ?",
                ['%'.$digits.'%']
            );
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Paciente::with(['medico:id', 'medico.linkedUser:id,name,medico_id', 'medicos:id,apelido,crm,uf_crm,nome_legado', 'medicos.linkedUser:id,name,medico_id', 'telefones', 'createdBy:id,name', 'updatedBy:id,name'])
            ->when($request->search, function ($q, $search) {
                $q->where(function (Builder $sub) use ($search) {
                    $this->applyPacienteTextSearch($sub, $search);
                });
            })
            ->when($request->medico_id, fn ($q, $medicoId) => $q->whereHas('medicos', fn ($mq) => $mq->where('medicos.id', $medicoId)));

        // Filter by ativo status - defaults to true (active) if not specified
        if ($request->has('ativo') && $request->ativo !== '' && $request->ativo !== null) {
            $query->where('ativo', $request->boolean('ativo'));
        } elseif (! $request->has('ativo')) {
            // Default to showing only active patients when accessing page directly
            $query->where('ativo', true);
        }
        // When ativo='' (empty string), show all patients (no filter applied)

        // Filter by user access — Opção 2: pertencimento ao pivot medico_paciente.
        // Para médico/secretária, "arquivar" é por vínculo, então o padrão só mostra
        // vínculos ativos (a menos que ativo='' peça todos).
        $user = $request->user();
        $mostrarSoVinculoAtivo = ! ($request->has('ativo') && $request->ativo === '');
        if ($user->isMedico() && $user->medico_id) {
            $query->whereHas('medicos', function ($mq) use ($user, $mostrarSoVinculoAtivo) {
                $mq->where('medicos.id', $user->medico_id);
                if ($mostrarSoVinculoAtivo) {
                    $mq->where('medico_paciente.ativo', true);
                }
            });
        } elseif ($user->isMedico()) {
            $query->whereRaw('1 = 0'); // médico sem vínculo (medico_id nulo) = lista vazia (fail-closed)
        }
        if ($user->isSecretaria() && $user->clinica_id) {
            $medicoIds = $user->getMedicoIdsDaClinica();
            $query->whereHas('medicos', function ($mq) use ($medicoIds, $mostrarSoVinculoAtivo) {
                $mq->whereIn('medicos.id', $medicoIds);
                if ($mostrarSoVinculoAtivo) {
                    $mq->where('medico_paciente.ativo', true);
                }
            });
        } elseif ($user->isSecretaria()) {
            $query->whereRaw('1 = 0'); // sem clínica = lista vazia
        }

        $ultimaReceitaSub = Receita::query()
            ->select('id')
            ->whereColumn('paciente_id', 'pacientes.id')
            ->orderByDesc('data_receita')
            ->orderByDesc('id')
            ->limit(1);

        $pacientes = $query
            ->addSelect([
                'ultima_receita_id' => $ultimaReceitaSub,
            ])
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        // Opção 2: para o médico, exibe o Nº Registro/Indicado/Observações do SEU vínculo.
        // Para admin (e demais não-médicos), anexa a lista por médico (somente leitura no drawer).
        if ($user->isMedico() && $user->medico_id) {
            $ids = $pacientes->getCollection()->pluck('id')->all();
            if (! empty($ids)) {
                $pivots = \App\Models\MedicoPaciente::where('medico_id', $user->medico_id)
                    ->whereIn('paciente_id', $ids)->get()->keyBy('paciente_id');
                $pacientes->getCollection()->each(function ($p) use ($pivots) {
                    if ($pv = $pivots->get($p->id)) {
                        $p->setAttribute('anotacoes', $pv->anotacoes);
                        $p->setAttribute('codigo', $pv->codigo);
                        $p->setAttribute('indicado_por', $pv->indicado_por);
                    }
                });
            }
        } elseif ($user->isAdmin() || $user->isCallcenter()) {
            $pacientes->getCollection()->each(fn (Paciente $p) => $p->attachPrivadosPorMedico());
        }

        $medicos = $this->getMedicosForPacienteForm($request->user());

        return Inertia::render('Pacientes/Index', [
            'pacientes' => $pacientes,
            'medicos' => $medicos,
            'filters' => $request->only(['search', 'medico_id', 'ativo']),
            'tiposTelefone' => PacienteTelefone::getTipos(),
            'isAdmin' => $user->isAdmin(),
            'isSecretaria' => $user->isSecretaria(),
            'canSelectMedico' => ! $user->isMedico(),
            'canAccessAssistente' => $user->isAdmin() || $user->isMedico(),
        ]);
    }

    /**
     * Get medicos list for paciente form (filtered by role: all for admin/callcenter, one for medico, clinic for secretária).
     */
    private function getMedicosForPacienteForm($user): \Illuminate\Support\Collection
    {
        $query = Medico::ativo()
            ->leftJoin('users', 'users.medico_id', '=', 'medicos.id')
            ->orderByRaw('COALESCE(users.name, medicos.apelido, medicos.crm)')
            ->select('medicos.id', 'medicos.apelido', 'medicos.crm');

        if ($user->isSecretaria() && $user->clinica_id) {
            $ids = $user->getMedicoIdsDaClinica();
            $query->whereIn('medicos.id', $ids);
        }

        return $query->get()->load('linkedUser:id,name,medico_id');
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
            'cpf' => 'required|string|max:14',
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
            'codigo' => 'nullable|string|max:255',
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
            'email1.email' => 'Informe um e-mail válido.',
            'medico_id.required' => 'Selecione o médico responsável.',
        ]);

        $this->normalizePacienteCodigo($validated);

        // Validate CPF digits
        if (! $this->validateCpfDigits($validated['cpf'] ?? null)) {
            return $this->jsonOrBackForFieldError($request, 'cpf', 'CPF inválido. Por favor, verifique os números digitados.');
        }

        // Auto-assign medico if user is medico
        if ($user->isMedico()) {
            if (! $user->medico_id) {
                return $this->jsonOrBackForFieldError(
                    $request,
                    'medico_id',
                    'Sua conta de médico não está vinculada a um cadastro de médico. Peça ao administrador para vincular seu usuário.'
                );
            }
            $validated['medico_id'] = $user->medico_id;
        }
        // Secretária: medico deve pertencer à clínica (validado como required acima)
        if ($user->isSecretaria() && ! empty($validated['medico_id'])) {
            if (! in_array((int) $validated['medico_id'], $user->getMedicoIdsDaClinica(), true)) {
                return $this->jsonOrBackForFieldError($request, 'medico_id', 'O médico selecionado não pertence à sua clínica.');
            }
        }

        // Opção 2: os campos privados vão para o pivot medico_paciente, não para `pacientes`.
        $privados = $this->extractPrivados($validated);
        $medicoContexto = ! empty($validated['medico_id']) ? (int) $validated['medico_id'] : null;
        if (! $medicoContexto) {
            return $this->jsonOrBackForFieldError($request, 'medico_id', 'Selecione o médico responsável.');
        }
        if ($this->codigoDuplicadoNoMedico($medicoContexto, $privados['codigo'] ?? null)) {
            return $this->jsonOrBackForFieldError($request, 'codigo', 'Já existe um paciente com este Nº Registro para este médico.');
        }

        $telefones = $validated['telefones'] ?? [];
        unset($validated['telefones']);

        // Opção 2 (upsert por CPF): se já existe um paciente com este CPF, é o MESMO
        // paciente — atualiza os dados principais compartilhados e cria/garante o
        // vínculo com este médico (não bloqueia mais o 2º médico).
        $cpfLimpo = preg_replace('/\D/', '', (string) ($validated['cpf'] ?? ''));
        $existente = $cpfLimpo !== ''
            ? Paciente::whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(cpf,''),'.',''),'-',''),' ','') = ?", [$cpfLimpo])->first()
            : null;

        if ($existente) {
            // Se este médico já tem vínculo com o paciente, é duplicidade real.
            if ($medicoContexto && $existente->vinculoDoMedico($medicoContexto)) {
                return $this->jsonOrBackForFieldError($request, 'cpf', 'Este paciente já está cadastrado para este médico.');
            }
            $paciente = $existente;
            // FK legado `medico_id` é só origem/primeiro vínculo — não trocar no 2º médico.
            if ($paciente->medico_id !== null) {
                unset($validated['medico_id']);
            }
            $paciente->fill($validated);
            $paciente->forceFill(['updated_by_user_id' => $user->id])->save();
        } else {
            $paciente = Paciente::create($validated);
            $paciente->forceFill([
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ])->save();
        }

        // Opção 2: cria o vínculo médico↔paciente com os campos privados.
        if ($medicoContexto) {
            app(\App\Services\PacienteVinculoService::class)->garantir(
                $paciente,
                $medicoContexto,
                $privados + ['ativo' => true],
                $user->id,
                'form',
            );
        }

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
                'paciente' => $paciente->fresh(['telefones', 'medico', 'createdBy:id,name', 'updatedBy:id,name']),
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
        if (! $user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

        $paciente->load(['medico:id', 'medico.linkedUser:id,name,medico_id', 'createdBy:id,name', 'updatedBy:id,name', 'receitas' => function ($q) {
            $q->with('medico:id', 'medico.linkedUser:id,name,medico_id')->orderByDesc('data_receita')->limit(10);
        }]);

        if ($user->isAdmin() || $user->isCallcenter()) {
            $paciente->attachPrivadosPorMedico();
        } else {
            $this->injectPrivadosDoMedico($paciente, $user);
        }

        return Inertia::render('Pacientes/Show', [
            'paciente' => $paciente,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Paciente $paciente): Response
    {
        $user = $request->user();

        // Check if user can access this paciente
        if (! $user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

        $medicos = $this->getMedicosForPacienteForm($user);

        $paciente->loadMissing(['createdBy:id,name', 'updatedBy:id,name']);

        $this->injectPrivadosDoMedico($paciente, $user);

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
            'codigo' => ['nullable', 'string', 'max:255'],
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
            'email1.email' => 'Informe um e-mail válido.',
        ]);

        $this->normalizePacienteCodigo($validated);

        // Validate CPF digits
        if (! $this->validateCpfDigits($validated['cpf'] ?? null)) {
            return $this->jsonOrBackForFieldError($request, 'cpf', 'CPF inválido. Por favor, verifique os números digitados.');
        }

        $user = $request->user();
        // Check if user can access this paciente
        if (! $user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

        // Secretária: medico_id must be from her clinic
        if ($user->isSecretaria() && isset($validated['medico_id']) && $validated['medico_id'] !== null) {
            if (! in_array((int) $validated['medico_id'], $user->getMedicoIdsDaClinica(), true)) {
                return $this->jsonOrBackForFieldError($request, 'medico_id', 'O médico selecionado não pertence à sua clínica.');
            }
        }

        // Prevent medico from changing medico_id
        if ($user->isMedico()) {
            if (! $user->medico_id) {
                return $this->jsonOrBackForFieldError(
                    $request,
                    'medico_id',
                    'Sua conta de médico não está vinculada a um cadastro de médico. Peça ao administrador para vincular seu usuário.'
                );
            }
            $validated['medico_id'] = $user->medico_id;
        }

        // Opção 2: define o médico de contexto (de quem é o vínculo sendo editado) ANTES
        // de mexer no medico_id legado.
        $medicoContexto = null;
        if ($user->isMedico() && $user->medico_id) {
            $medicoContexto = $user->medico_id;
        } elseif (! empty($validated['medico_id'])) {
            $medicoContexto = (int) $validated['medico_id'];
        } else {
            $medicoContexto = $paciente->medico_id;
        }

        // Médico responsável (FK legado) não é trocado após já vinculado; só define se null.
        if ($paciente->medico_id !== null) {
            unset($validated['medico_id']);
        }

        // Opção 2: campos privados vão para o pivot do médico de contexto.
        // Admin/callcenter/secretária não editam esses campos no update (evita zerar notas alheias).
        $privados = $this->extractPrivados($validated);
        if (! $user->isMedico()) {
            $privados = [];
        }
        if ($this->codigoDuplicadoNoMedico($medicoContexto, $privados['codigo'] ?? null, $paciente->id)) {
            return $this->jsonOrBackForFieldError($request, 'codigo', 'Já existe um paciente com este Nº Registro para este médico.');
        }

        $telefones = $validated['telefones'] ?? [];
        unset($validated['telefones']);

        $paciente->update($validated);
        $paciente->forceFill(['updated_by_user_id' => $user->id])->save();

        if ($medicoContexto) {
            app(\App\Services\PacienteVinculoService::class)->garantir(
                $paciente,
                $medicoContexto,
                $privados,
                $user->id,
                'form',
            );
        }

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
                'paciente' => $paciente->fresh(['telefones', 'medico', 'createdBy:id,name', 'updatedBy:id,name']),
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
        if (! $user->canAccessPaciente($paciente)) {
            abort(403, 'Acesso não autorizado.');
        }

        // Opção 2: "arquivar" é por vínculo para médico/secretária (some só na visão dele,
        // sem afetar outros médicos). Admin/callcenter desativam o registro global.
        if ($user->isAdmin() || $user->isCallcenter()) {
            $paciente->update(['ativo' => false]);
        } else {
            $medicoId = ($user->isMedico() && $user->medico_id) ? $user->medico_id : $paciente->medico_id;
            $pivot = $medicoId ? $paciente->vinculoDoMedico((int) $medicoId) : null;
            if ($pivot) {
                $pivot->update(['ativo' => false, 'updated_by_user_id' => $user->id]);
            } else {
                $paciente->update(['ativo' => false]);
            }
        }

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
            ->where(function (Builder $q) use ($search) {
                $this->applyPacienteTextSearch($q, $search);
            });

        // Filter by user access — Opção 2: via pivot medico_paciente (vínculo ativo)
        $user = $request->user();
        if ($user->isMedico() && $user->medico_id) {
            $query->whereHas('medicos', fn ($mq) => $mq->where('medicos.id', $user->medico_id)->where('medico_paciente.ativo', true));
        } elseif ($user->isMedico()) {
            $query->whereRaw('1 = 0'); // médico sem vínculo (medico_id nulo) = sem resultados (fail-closed)
        }

        $pacientes = $query->orderBy('nome')
            ->limit(20)
            ->get(['id', 'nome', 'cpf', 'celular']);

        return response()->json($pacientes);
    }

    /**
     * Opção 2 — Lookup por CPF para o fluxo "Novo Paciente": localiza um paciente já
     * cadastrado (por qualquer médico) e devolve os dados principais para pré-preencher.
     * Retorna ja_vinculado=true se o médico logado já tem vínculo (evita duplicidade).
     */
    public function lookup(Request $request)
    {
        $cpf = preg_replace('/\D/', '', (string) $request->get('cpf', ''));
        if (strlen($cpf) !== 11) {
            return response()->json(['found' => false], 200);
        }

        $paciente = Paciente::whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(cpf,''),'.',''),'-',''),' ','') = ?", [$cpf])
            ->with('telefones')
            ->first();

        if (! $paciente) {
            return response()->json(['found' => false], 200);
        }

        $user = $request->user();
        $medicoId = ($user->isMedico() && $user->medico_id) ? $user->medico_id : (int) $request->get('medico_id', 0);
        $jaVinculado = $medicoId ? (bool) $paciente->vinculoDoMedico((int) $medicoId) : false;

        // Só dados principais compartilhados — nunca os campos privados de outro médico.
        return response()->json([
            'found' => true,
            'ja_vinculado' => $jaVinculado,
            'paciente' => [
                'id' => $paciente->id,
                'nome' => $paciente->nome,
                'cpf' => $paciente->cpf,
                'rg' => $paciente->rg,
                'data_nascimento' => optional($paciente->data_nascimento)->format('Y-m-d'),
                'sexo' => $paciente->sexo,
                'fototipo' => $paciente->fototipo,
                'email1' => $paciente->email1,
                'email2' => $paciente->email2,
                'telefone1' => $paciente->telefone1,
                'celular' => $paciente->celular,
                'telefone3' => $paciente->telefone3,
                'tipo_endereco' => $paciente->tipo_endereco,
                'endereco' => $paciente->endereco,
                'numero' => $paciente->numero,
                'complemento' => $paciente->complemento,
                'bairro' => $paciente->bairro,
                'cidade' => $paciente->cidade,
                'uf' => $paciente->uf,
                'pais' => $paciente->pais,
                'cep' => $paciente->cep,
            ],
        ]);
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

        // Opção 2: Nº Registro é único por médico (checado no pivot depois), não global.
        $codigoRule = ['nullable', 'string', 'max:255'];

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
            'codigo' => $codigoRule,
            'indicado_por' => 'nullable|string|max:255',
            'anotacoes' => 'nullable|string',
            'medico_id' => $medicoRules,
            'ativo' => 'boolean',
            'telefones' => 'nullable|array',
            'telefones.*.numero' => 'required|string|max:30',
            'telefones.*.tipo' => 'required|string|max:50',
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'email1.email' => 'Informe um e-mail válido.',
            'medico_id.required' => 'Selecione o médico responsável.',
        ]);

        $this->normalizePacienteCodigo($validated);

        // Validate CPF digits if provided
        if (! empty($validated['cpf']) && ! $this->validateCpfDigits($validated['cpf'])) {
            $msg = 'CPF inválido.';

            return response()->json([
                'message' => $msg,
                'errors' => ['cpf' => [$msg]],
            ], 422);
        }

        if ($user->isMedico()) {
            if (! $user->medico_id) {
                return response()->json([
                    'message' => 'Sua conta de médico não está vinculada a um cadastro de médico. Peça ao administrador para vincular seu usuário.',
                    'errors' => ['medico_id' => ['Sua conta de médico não está vinculada a um cadastro de médico. Peça ao administrador para vincular seu usuário.']],
                ], 422);
            }
            $validated['medico_id'] = $user->medico_id;
        }
        if ($user->isSecretaria() && ! empty($validated['medico_id'])) {
            if (! in_array((int) $validated['medico_id'], $user->getMedicoIdsDaClinica(), true)) {
                $msg = 'O médico selecionado não pertence à sua clínica.';

                return response()->json([
                    'message' => $msg,
                    'errors' => ['medico_id' => [$msg]],
                ], 422);
            }
        }

        $id = $validated['id'] ?? null;
        $telefones = $validated['telefones'] ?? [];
        unset($validated['id'], $validated['telefones']);

        // Opção 2: campos privados por médico vão para o pivot.
        // Em update, só o médico pode gravar Indicado por / Nº Registro / Observações.
        $privados = $this->extractPrivados($validated);

        if ($id) {
            $paciente = Paciente::findOrFail($id);

            // Check access
            if (! $user->canAccessPaciente($paciente)) {
                return response()->json(['error' => 'Acesso não autorizado'], 403);
            }

            if (! $user->isMedico()) {
                $privados = [];
            }

            $medicoContexto = ($user->isMedico() && $user->medico_id)
                ? $user->medico_id
                : (! empty($validated['medico_id']) ? (int) $validated['medico_id'] : $paciente->medico_id);

            if (! $medicoContexto) {
                return response()->json([
                    'message' => 'Selecione o médico responsável.',
                    'errors' => ['medico_id' => ['Selecione o médico responsável.']],
                ], 422);
            }

            if ($paciente->medico_id !== null) {
                unset($validated['medico_id']);
            }

            if ($this->codigoDuplicadoNoMedico($medicoContexto, $privados['codigo'] ?? null, $paciente->id)) {
                return response()->json(['message' => 'Nº Registro já usado para este médico.', 'errors' => ['codigo' => ['Já existe um paciente com este Nº Registro para este médico.']]], 422);
            }

            $paciente->update($validated);
            $paciente->forceFill(['updated_by_user_id' => $user->id])->save();
        } else {
            $medicoContexto = ! empty($validated['medico_id']) ? (int) $validated['medico_id'] : null;
            if (! $medicoContexto) {
                return response()->json([
                    'message' => 'Selecione o médico responsável.',
                    'errors' => ['medico_id' => ['Selecione o médico responsável.']],
                ], 422);
            }
            if ($this->codigoDuplicadoNoMedico($medicoContexto, $privados['codigo'] ?? null)) {
                return response()->json(['message' => 'Nº Registro já usado para este médico.', 'errors' => ['codigo' => ['Já existe um paciente com este Nº Registro para este médico.']]], 422);
            }

            $paciente = Paciente::create($validated);
            $paciente->forceFill([
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ])->save();
        }

        // Opção 2: cria/atualiza o vínculo com os campos privados.
        if (! empty($medicoContexto)) {
            app(\App\Services\PacienteVinculoService::class)->garantir(
                $paciente,
                (int) $medicoContexto,
                $privados,
                $user->id,
                'form',
            );
        }

        // Sync telefones
        if (! empty($telefones)) {
            $paciente->telefones()->delete();
            foreach ($telefones as $index => $telefone) {
                $paciente->telefones()->create([
                    'numero' => $telefone['numero'],
                    'tipo' => $telefone['tipo'],
                    'principal' => $index === 0,
                ]);
            }
        }

        $fresh = $paciente->fresh(['createdBy:id,name', 'updatedBy:id,name']);

        return response()->json([
            'success' => true,
            'id' => $paciente->id,
            'saved_at' => now()->toISOString(),
            'created_by_name' => $fresh->createdBy?->name,
            'updated_by_name' => $fresh->updatedBy?->name,
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
            'email1.email' => 'Informe um e-mail válido.',
        ]);

        // Validate CPF digits
        if (! $this->validateCpfDigits($validated['cpf'] ?? null)) {
            return response()->json(['error' => 'CPF inválido. Por favor, verifique os números digitados.'], 422);
        }

        $user = $request->user();
        if ($user->isMedico() && $user->medico_id) {
            $validated['medico_id'] = $user->medico_id;
        } elseif ($user->isSecretaria()) {
            $medicoIds = $user->getMedicoIdsDaClinica();
            if (! empty($medicoIds)) {
                $validated['medico_id'] = $medicoIds[0];
            }
        }

        $validated['ativo'] = true;

        $paciente = Paciente::create($validated);
        $paciente->forceFill([
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ])->save();

        // Opção 2: cria o vínculo com o médico determinado acima.
        if (! empty($validated['medico_id'])) {
            app(\App\Services\PacienteVinculoService::class)->garantir(
                $paciente,
                (int) $validated['medico_id'],
                ['ativo' => true],
                $user->id,
                'form',
            );
        }

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
            return response()->json(['error' => 'Erro ao consultar CEP: '.$e->getMessage()], 500);
        }
    }
}
