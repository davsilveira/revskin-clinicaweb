<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\PacienteTelefone;
use App\Models\Receita;
use App\Support\EmailPlaceholder;
use App\Support\NomePaciente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
     * País vazio conta como Brasil (default histórico da coluna `pacientes.pais`).
     */
    private function paisEhBrasil(mixed $pais): bool
    {
        $pais = trim((string) $pais);

        return $pais === '' || mb_strtolower($pais) === 'brasil';
    }

    /**
     * Documento vazio precisa virar NULL: com string vazia, dois pacientes sem CPF colidem
     * na regra `unique` e o segundo cadastro recebe um "já existe" que não faz sentido.
     * Fora do Brasil o CPF nem é oferecido — o identificador é `outro_documento`.
     */
    private function normalizeDocumentos(array &$validated): void
    {
        foreach (['cpf', 'outro_documento'] as $campo) {
            if (! array_key_exists($campo, $validated)) {
                continue;
            }
            $valor = trim((string) $validated[$campo]);
            $validated[$campo] = $valor === '' ? null : $valor;
        }
    }

    /**
     * E-mail vazio grava NULL, não string vazia — senão "sem e-mail" fica com duas
     * representações e o filtro/relatório de quem falta e-mail perde metade dos casos.
     * Placeholder herdado do oList (`@cadastrar_email.com`, com underline) é normalizado
     * para o domínio válido; sem isso o próprio `email` do validate() rejeita e o cadastro
     * não salva — que é o bug dos 150 pacientes travados hoje. Por isso roda ANTES do
     * validate(), e não sobre o `$validated`.
     */
    private function normalizeEmails(Request $request): void
    {
        foreach (['email1', 'email2'] as $campo) {
            if (! $request->has($campo)) {
                continue;
            }
            $request->merge([$campo => EmailPlaceholder::normalizar($request->input($campo))]);
        }
    }

    /**
     * Upsert da Opção 2: o formulário pode chegar sem e-mail (agora é opcional) e o cadastro
     * de destino já ter um endereço de verdade — gravar vazio por cima seria perder o dado
     * de outro médico. Só o `update()` do próprio cadastro apaga o e-mail, porque ali o
     * usuário está editando aquele registro e limpou o campo de propósito.
     */
    private function preservarEmailExistente(array &$validated, ?Paciente $existente): void
    {
        if ($existente === null) {
            return;
        }
        foreach (['email1', 'email2'] as $campo) {
            if (array_key_exists($campo, $validated)
                && $validated[$campo] === null
                && trim((string) $existente->{$campo}) !== '') {
                unset($validated[$campo]);
            }
        }
    }

    /**
     * UF é sigla de 2 letras só no Brasil; fora dele o campo é "Estado/Província" livre
     * (o drawer já mostra um input de texto). A coluna aceita 255.
     */
    private function regraUf(mixed $pais): string
    {
        return $this->paisEhBrasil($pais) ? 'nullable|string|max:2' : 'nullable|string|max:100';
    }

    public const MSG_CPF_OBRIGATORIO = 'O CPF é obrigatório para pacientes no Brasil.';

    /**
     * Feedback do cliente: no Brasil o CPF é obrigatório; fora do Brasil o documento é opcional.
     *
     * Exceção deliberada (cadastro que já existe sem CPF): não travamos a edição nem o
     * vínculo de um cadastro que JÁ está salvo sem CPF. Sem isso, os pacientes legados sem
     * CPF (hoje 1.094 de 1.157 no banco) e os clientes importados do oList (1 em cada 4 vem
     * sem CPF) travariam em toda edição — inclusive no autosave do drawer, que salva a cada
     * 2 s. Cadastro NOVO no Brasil, sim, exige CPF.
     *
     * @return bool true quando falta CPF e ele era obrigatório
     */
    private function faltaCpfObrigatorio(array $validated, ?Paciente $existente = null): bool
    {
        if (! $this->paisEhBrasil($validated['pais'] ?? ($existente?->pais ?? 'Brasil'))) {
            return false;
        }

        if (trim((string) ($validated['cpf'] ?? '')) !== '') {
            return false;
        }

        // Cadastro já salvo sem CPF: segue editável/vinculável sem CPF.
        return ! ($existente !== null && trim((string) $existente->cpf) === '');
    }

    public const MSG_EXISTENTE_NAO_CORRESPONDE = 'O cadastro selecionado não corresponde ao nome informado. Refaça a busca pelo nome do paciente.';

    /**
     * Alvo do upsert (Opção 2). `paciente_existente_id` vem da busca por nome: o usuário
     * escolheu explicitamente um cadastro na lista, então ele manda — sem isso, um e-mail
     * digitado diferente faria o sistema criar um segundo cadastro do mesmo paciente.
     *
     * Travessa: o nome enviado tem de ser compatível com o do cadastro escolhido. Sem isso, um
     * `paciente_existente_id` chutado (id é sequencial) sobrescreveria os dados compartilhados
     * de qualquer paciente do sistema. O drawer preenche o nome a partir do próprio cadastro,
     * então o fluxo real passa sempre.
     *
     * @throws \Illuminate\Validation\ValidationException quando a escolha não corresponde
     */
    private function resolverPacienteAlvo(Request $request, array $validated): ?Paciente
    {
        $id = (int) $request->input('paciente_existente_id', 0);
        if ($id <= 0) {
            return $this->localizarPacienteExistente($validated);
        }

        $escolhido = Paciente::find($id);
        if ($escolhido === null) {
            return null;
        }

        if (! NomePaciente::compativeis((string) ($validated['nome'] ?? ''), (string) $escolhido->nome)) {
            throw ValidationException::withMessages(['nome' => self::MSG_EXISTENTE_NAO_CORRESPONDE]);
        }

        return $escolhido;
    }

    /**
     * Opção 2 — identidade do paciente entre médicos, agora que o CPF é opcional:
     *   1. CPF (identificador forte) quando informado;
     *   2. senão e-mail + data de nascimento — os dois são obrigatórios no cadastro.
     *      E-mail sozinho não serve: família compartilhando um e-mail (mãe e filha) seria
     *      fundida num único paciente. A data de nascimento separa as pessoas.
     *   3. senão nada: cria um paciente novo.
     */
    private function localizarPacienteExistente(array $validated): ?Paciente
    {
        $cpfLimpo = preg_replace('/\D/', '', (string) ($validated['cpf'] ?? ''));
        if ($cpfLimpo !== '') {
            return Paciente::whereRaw(
                "REPLACE(REPLACE(REPLACE(COALESCE(cpf,''),'.',''),'-',''),' ','') = ?",
                [$cpfLimpo]
            )->first();
        }

        $email = trim((string) ($validated['email1'] ?? ''));
        $nascimento = $validated['data_nascimento'] ?? null;
        if ($email === '' || ! $nascimento) {
            return null;
        }

        return Paciente::whereRaw('LOWER(TRIM(COALESCE(email1, ""))) = ?', [mb_strtolower($email)])
            ->whereDate('data_nascimento', $nascimento)
            ->first();
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
            ->orWhere('outro_documento', 'like', "%{$search}%")
            ->orWhere('codigo', 'like', "%{$search}%")
            ->orWhere('telefone1', 'like', "%{$search}%")
            ->orWhere('celular', 'like', "%{$search}%")
            ->orWhere('email1', 'like', "%{$search}%")
            ->orWhere('email2', 'like', "%{$search}%")
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

        $this->normalizeEmails($request);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'required|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:14',
            'outro_documento' => 'nullable|string|max:50',
            'rg' => 'nullable|string|max:20',
            'telefone1' => 'nullable|string|max:20',
            'celular' => 'required|string|max:20',
            'telefone3' => 'nullable|string|max:20',
            // E-mail opcional (decisão do cliente): 2 em cada 3 pacientes da base já estão sem
            // e-mail e exigi-lo travava o cadastro. Quando preenchido, continua validado.
            'email1' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'tipo_endereco' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => $this->regraUf($request->input('pais')),
            'pais' => 'nullable|string|max:100',
            'cep' => 'nullable|string|max:10',
            'codigo' => 'nullable|string|max:255',
            'indicado_por' => 'nullable|string|max:255',
            'anotacoes' => 'nullable|string',
            'medico_id' => $medicoRules,
            'ativo' => 'boolean',
            'paciente_existente_id' => 'nullable|exists:pacientes,id',
            'telefones' => 'nullable|array',
            'telefones.*.numero' => 'required|string|max:30',
            'telefones.*.tipo' => 'required|string|max:50',
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
            'email1.email' => 'Informe um e-mail válido.',
            'medico_id.required' => 'Selecione o médico responsável.',
        ]);

        $this->normalizePacienteCodigo($validated);
        $this->normalizeDocumentos($validated);
        unset($validated['paciente_existente_id']);

        // Opção 2 (upsert): se já existe um paciente com esta identidade (escolhido na busca
        // por nome, ou CPF, ou e-mail + data de nascimento), é o MESMO paciente.
        $existente = $this->resolverPacienteAlvo($request, $validated);

        if ($this->faltaCpfObrigatorio($validated, $existente)) {
            return $this->jsonOrBackForFieldError($request, 'cpf', self::MSG_CPF_OBRIGATORIO);
        }

        // CPF opcional fora do Brasil, mas quando informado tem de ser um CPF de verdade.
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

        // O alvo do upsert foi resolvido acima (antes da regra de CPF): atualiza os dados
        // compartilhados e garante o vínculo com este médico (não bloqueia o 2º médico).
        if ($existente) {
            // Se este médico já tem vínculo com o paciente, é duplicidade real — EXCETO quando
            // ele escolheu o cadastro na busca por nome: aí a intenção é atualizar os dados
            // desse paciente, e barrar com 422 seria um beco sem saída (a única saída visível
            // no drawer seria criar o duplicado que a busca existe para evitar).
            if ($medicoContexto
                && ! $request->filled('paciente_existente_id')
                && $existente->vinculoDoMedico($medicoContexto)) {
                $campoErro = ! empty($validated['cpf']) ? 'cpf' : 'email1';

                return $this->jsonOrBackForFieldError($request, $campoErro, 'Este paciente já está cadastrado para este médico.');
            }
            $paciente = $existente;
            $this->preservarEmailExistente($validated, $existente);
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
        $this->normalizeEmails($request);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'required|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('pacientes', 'cpf')->ignore($paciente->id)],
            'outro_documento' => 'nullable|string|max:50',
            'rg' => 'nullable|string|max:20',
            'telefone1' => 'nullable|string|max:20',
            'celular' => 'required|string|max:20',
            'telefone3' => 'nullable|string|max:20',
            // E-mail opcional (ver store): quando preenchido, continua validado.
            'email1' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'tipo_endereco' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => $this->regraUf($request->input('pais')),
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
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
            'email1.email' => 'Informe um e-mail válido.',
        ]);

        $this->normalizePacienteCodigo($validated);
        $this->normalizeDocumentos($validated);

        if ($this->faltaCpfObrigatorio($validated, $paciente)) {
            return $this->jsonOrBackForFieldError($request, 'cpf', self::MSG_CPF_OBRIGATORIO);
        }

        // CPF opcional fora do Brasil, mas quando informado tem de ser um CPF de verdade.
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

        // data_nascimento/e-mail vão no payload para o dropdown diferenciar homônimos
        // (a lista de "João da Silva" é longa).
        $pacientes = $query->orderBy('nome')
            ->limit(20)
            ->get(['id', 'nome', 'cpf', 'outro_documento', 'celular', 'data_nascimento', 'email1']);

        return response()->json($pacientes);
    }

    /**
     * Opção 2 — Lookup para o fluxo "Novo Paciente": localiza um paciente já cadastrado
     * (por qualquer médico) e devolve os dados principais para pré-preencher.
     * Aceita `cpf`, `outro_documento` ou `email` — nesta ordem de confiança.
     * Retorna ja_vinculado=true se o médico logado já tem vínculo (evita duplicidade).
     */
    public function lookup(Request $request)
    {
        $paciente = null;
        $matchPor = null;

        // `id` vem da busca por nome: o usuário escolheu um cadastro específico na lista.
        $id = (int) $request->get('id', 0);
        if ($id > 0) {
            $paciente = Paciente::with('telefones')->find($id);
            $matchPor = $paciente ? 'id' : null;
        }

        $cpf = preg_replace('/\D/', '', (string) $request->get('cpf', ''));
        if (! $paciente && strlen($cpf) === 11) {
            $paciente = Paciente::whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(cpf,''),'.',''),'-',''),' ','') = ?", [$cpf])
                ->with('telefones')
                ->first();
            $matchPor = 'cpf';
        }

        $documento = trim((string) $request->get('outro_documento', ''));
        if (! $paciente && $documento !== '') {
            $paciente = Paciente::whereRaw('LOWER(TRIM(COALESCE(outro_documento, ""))) = ?', [mb_strtolower($documento)])
                ->with('telefones')
                ->first();
            $matchPor = 'outro_documento';
        }

        // E-mail é a rede de segurança quando não há documento nenhum. Sozinho ele não
        // funde cadastros (ver localizarPacienteExistente) — aqui só avisa e pré-preenche.
        $email = trim((string) $request->get('email', ''));
        if (! $paciente && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $paciente = Paciente::whereRaw('LOWER(TRIM(COALESCE(email1, ""))) = ?', [mb_strtolower($email)])
                ->with('telefones')
                ->first();
            $matchPor = 'email';
        }

        if (! $paciente) {
            return response()->json(['found' => false], 200);
        }

        $user = $request->user();
        $medicoId = ($user->isMedico() && $user->medico_id) ? $user->medico_id : (int) $request->get('medico_id', 0);
        $jaVinculado = $medicoId ? (bool) $paciente->vinculoDoMedico((int) $medicoId) : false;

        // Só dados principais compartilhados — nunca os campos privados de outro médico.
        return response()->json([
            'found' => true,
            'match_por' => $matchPor,
            // E-mail é indício fraco (e-mail de família): quem consome deve apenas COMPLETAR
            // campos vazios, nunca substituir o nome/nascimento que o usuário já digitou.
            'match_forte' => $matchPor !== 'email',
            'ja_vinculado' => $jaVinculado,
            'paciente' => [
                'id' => $paciente->id,
                'nome' => $paciente->nome,
                'cpf' => $paciente->cpf,
                'outro_documento' => $paciente->outro_documento,
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
     * Busca por NOME no fluxo "Novo Paciente" (substitui a busca por CPF como caminho
     * principal): o CPF nunca ia achar os clientes que vêm do oList sem CPF.
     *
     * Procura em TODOS os pacientes do sistema (não só nos do médico logado) porque é
     * justamente esse o ponto: o paciente pode ter vindo do oList, ou ser de outro médico.
     * Devolve só os campos que servem para diferenciar homônimos — celular, e-mail, data de
     * nascimento e documento (parcialmente mascarado) —, nunca os campos privados de outro
     * médico (Nº Registro, Indicado por, Observações).
     */
    /**
     * A lista de candidatos é global (mostra paciente de outro médico), então o documento sai
     * com os primeiros caracteres mascarados: o final basta para diferenciar homônimos, que é
     * o que o médico precisa, sem expor o documento inteiro de quem não é paciente dele.
     */
    private static function mascararDocumento(?string $documento): ?string
    {
        $documento = trim((string) $documento);
        if ($documento === '') {
            return null;
        }

        if (mb_strlen($documento) <= 4) {
            return $documento;
        }

        // Mantém a pontuação (o formato ajuda a reconhecer) e mostra os 6 últimos caracteres:
        // "123.456.838-22" → "•••.•••.838-22".
        $chars = mb_str_split($documento);
        $ultimoMascarado = count($chars) - 7;
        foreach ($chars as $i => $char) {
            if ($i > $ultimoMascarado) {
                break;
            }
            if (preg_match('/[\p{L}\p{N}]/u', $char)) {
                $chars[$i] = '•';
            }
        }

        return implode('', $chars);
    }

    public function candidatos(Request $request)
    {
        $termo = trim((string) $request->get('nome', ''));
        $limite = 8;
        $vazio = response()->json(['candidatos' => [], 'total' => 0, 'limite' => $limite]);

        $user = $request->user();
        // Mesma regra fail-closed de index()/search(): médico sem cadastro vinculado não
        // enxerga paciente nenhum.
        if ($user->isMedico() && ! $user->medico_id) {
            return $vazio;
        }

        if (mb_strlen($termo) < 3) {
            return $vazio;
        }

        // `%` e `_` são curingas de LIKE: sem escapar, "___" ou "%" listaria a base inteira
        // 8 registros por vez (o endpoint é global de propósito).
        $escapar = fn (string $t): string => str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $t);

        $tokens = array_map(
            $escapar,
            array_slice(
                array_values(array_filter(preg_split('/\s+/', $termo) ?: [], fn ($t) => $t !== '')),
                0,
                5
            )
        );
        $digitos = preg_replace('/\D/', '', $termo);

        // Termo só de curingas/pontuação não é busca.
        if (implode('', $tokens) === '' || (mb_strlen(str_replace(['\%', '\_', '\\\\'], '', implode('', $tokens))) < 3 && strlen($digitos) < 3)) {
            return $vazio;
        }

        // Ficha arquivada ENTRA aqui (marcada, e depois das ativas). Esconder era o que fazia o
        // sistema jurar "nenhum paciente encontrado" para quem tinha receita do mês passado, e o
        // médico recadastrar a mesma pessoa (job f8b5e9c5). Este painel é o único lugar da tela
        // que pode contar a verdade sem afrouxar a busca do dia a dia.
        $query = Paciente::query()
            ->where(function (Builder $q) use ($tokens, $digitos) {
                // "joao silva" precisa achar "João Pedro da Silva": todos os pedaços casam.
                $q->where(function (Builder $nomeQ) use ($tokens) {
                    foreach ($tokens as $token) {
                        $nomeQ->where('nome', 'like', '%'.$token.'%');
                    }
                });

                // Quem cola um CPF ou um celular no campo Nome também acha.
                if (strlen($digitos) >= 3) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cpf, ''), '.', ''), '-', ''), ' ', ''), '/', '') LIKE ?",
                        ['%'.$digitos.'%']
                    );
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(celular, ''), '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?",
                        ['%'.$digitos.'%']
                    );
                }
            });

        // Busca limite+1: quando cabe tudo na página, o próprio resultado já dá o total e o
        // COUNT extra (uma varredura a mais por tecla digitada) some.
        $pacientes = $query->orderByDesc('ativo')->orderBy('nome')->limit($limite + 1)->get([
            'id', 'nome', 'ativo', 'cpf', 'outro_documento', 'data_nascimento', 'sexo',
            'celular', 'telefone1', 'email1', 'cidade', 'uf', 'pais', 'tiny_id',
        ]);

        $total = $pacientes->count() > $limite
            ? (clone $query)->count()
            : $pacientes->count();

        $pacientes = $pacientes->take($limite);

        $medicoId = ($user->isMedico() && $user->medico_id)
            ? (int) $user->medico_id
            : (int) $request->get('medico_id', 0);

        $vinculadosAoMedico = [];
        if ($medicoId && $pacientes->isNotEmpty()) {
            // Vínculo ARQUIVADO não é "já é seu paciente": a ficha não aparece na busca dele, e
            // tratá-la como vinculada era o que escondia a linha do painel (o médico via
            // "nenhum paciente encontrado" com a ficha logo ali).
            $vinculadosAoMedico = \App\Models\MedicoPaciente::where('medico_id', $medicoId)
                ->whereIn('paciente_id', $pacientes->pluck('id'))
                ->where('ativo', true)
                ->pluck('paciente_id')
                ->all();
        }

        $candidatos = $pacientes->map(fn (Paciente $p) => [
            'id' => $p->id,
            'nome' => $p->nome,
            'documento' => self::mascararDocumento($p->cpf ?: $p->outro_documento),
            'documento_label' => $p->cpf ? 'CPF' : ($p->outro_documento ? 'Documento' : null),
            'data_nascimento' => optional($p->data_nascimento)->format('Y-m-d'),
            'data_nascimento_br' => optional($p->data_nascimento)->format('d/m/Y'),
            'idade' => $p->idade,
            'celular' => $p->celular ?: $p->telefone1,
            'email1' => $p->email1,
            'cidade' => $p->cidade,
            'uf' => $p->uf,
            'pais' => $p->pais,
            'do_olist' => ! empty($p->tiny_id),
            'ja_vinculado' => in_array($p->id, $vinculadosAoMedico, true),
            // Selecionar reativa (vincular()): quem escolhe a ficha é gente, não o dump.
            'arquivado' => ! $p->ativo,
        ])->values();

        return response()->json([
            'candidatos' => $candidatos,
            'total' => $total,
            'limite' => $limite,
        ]);
    }

    /**
     * Vincula ao médico logado um paciente que já existe no sistema (escolhido na busca por
     * nome). É o que permite usar, sem recadastrar, os clientes trazidos do oList: eles
     * entram sem vínculo nenhum e só aparecem na lista do médico depois disto.
     */
    public function vincular(Request $request, Paciente $paciente)
    {
        $user = $request->user();

        $request->validate(['medico_id' => 'nullable|exists:medicos,id']);

        // Médico só vincula a si mesmo (nunca aceita medico_id do corpo) e, sem cadastro
        // vinculado, não vincula ninguém — mesmo fail-closed de index()/store().
        if ($user->isMedico()) {
            $medicoId = (int) ($user->medico_id ?? 0);
        } else {
            $medicoId = (int) $request->input('medico_id', 0);
        }

        if ($user->isSecretaria() && (! $medicoId || ! in_array($medicoId, $user->getMedicoIdsDaClinica(), true))) {
            $msg = 'O médico selecionado não pertence à sua clínica.';

            return response()->json(['message' => $msg, 'errors' => ['medico_id' => [$msg]]], 422);
        }

        if (! $medicoId) {
            $msg = $user->isMedico()
                ? 'Sua conta de médico não está vinculada a um cadastro de médico. Peça ao administrador para vincular seu usuário.'
                : 'Selecione o médico responsável antes de escolher o paciente.';

            return response()->json(['message' => $msg, 'errors' => ['medico_id' => [$msg]]], 422);
        }

        app(\App\Services\PacienteVinculoService::class)->garantir(
            $paciente,
            $medicoId,
            ['ativo' => true],
            $user->id,
            'busca-nome',
        );

        // Ficha arquivada escolhida na busca: quem clicou disse que a pessoa é paciente dele. Sem
        // isto o vínculo volta ativo mas `pacientes.ativo=0` continua escondendo a ficha da busca —
        // e o médico cai no mesmo "não existe" de antes.
        if (! $paciente->ativo) {
            $paciente->forceFill(['ativo' => true, 'updated_by_user_id' => $user->id])->save();
            Log::info('Paciente arquivado reativado ao ser escolhido na busca por nome', [
                'paciente_id' => $paciente->id,
                'medico_id' => $medicoId,
                'user_id' => $user->id,
            ]);
        }

        // Devolve o cadastro COMPLETO: quem chama entrega este objeto ao PatientDrawer, e um
        // paciente parcial faria o autosave do drawer gravar vazio sobre endereço/país/sexo.
        return response()->json([
            'success' => true,
            'paciente' => $paciente->fresh(['telefones', 'medico:id', 'medico.linkedUser:id,name,medico_id']),
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

        $this->normalizeEmails($request);

        $validated = $request->validate([
            'id' => 'nullable|exists:pacientes,id',
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'nullable|date',
            'sexo' => 'nullable|string|max:20',
            'fototipo' => 'nullable|string|max:50',
            'cpf' => $cpfRule,
            'outro_documento' => 'nullable|string|max:50',
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
            'uf' => $this->regraUf($request->input('pais')),
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
        $this->normalizeDocumentos($validated);

        // Rascunho de um cadastro que já existe sem CPF continua salvando (ver faltaCpfObrigatorio).
        $pacienteRascunho = ! empty($validated['id']) ? Paciente::find($validated['id']) : null;
        if ($this->faltaCpfObrigatorio($validated, $pacienteRascunho)) {
            return response()->json([
                'message' => self::MSG_CPF_OBRIGATORIO,
                'errors' => ['cpf' => [self::MSG_CPF_OBRIGATORIO]],
            ], 422);
        }

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
        $this->normalizeEmails($request);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            // Sem `unique` de propósito: mesma identidade = mesmo paciente (upsert Opção 2 abaixo).
            'cpf' => 'nullable|string|max:14',
            'outro_documento' => 'nullable|string|max:50',
            'data_nascimento' => 'required|date',
            'sexo' => 'nullable|string|max:20',
            // E-mail opcional (ver store): quando preenchido, continua validado.
            'email1' => 'nullable|email|max:255',
            'telefone1' => 'nullable|string|max:20',
            'celular' => 'required|string|max:20',
            'cep' => 'nullable|string|max:10',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => $this->regraUf($request->input('pais')),
            'pais' => 'nullable|string|max:100',
            'paciente_existente_id' => 'nullable|exists:pacientes,id',
        ], [
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'celular.required' => 'O celular é obrigatório.',
            'email1.email' => 'Informe um e-mail válido.',
        ]);

        $this->normalizeDocumentos($validated);
        unset($validated['paciente_existente_id']);

        // Opção 2: mesma identidade (escolha explícita na busca por nome, CPF, ou
        // e-mail + nascimento) = mesmo paciente.
        $existente = $this->resolverPacienteAlvo($request, $validated);

        if ($this->faltaCpfObrigatorio($validated, $existente)) {
            return response()->json([
                'error' => self::MSG_CPF_OBRIGATORIO,
                'message' => self::MSG_CPF_OBRIGATORIO,
                'errors' => ['cpf' => [self::MSG_CPF_OBRIGATORIO]],
            ], 422);
        }

        // CPF opcional fora do Brasil, mas quando informado tem de ser um CPF de verdade.
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

        // O vínculo é criado com este médico mesmo quando o paciente já existia — a FK
        // legado `medico_id` abaixo pode ser removida do fill, mas o contexto não.
        $medicoContexto = ! empty($validated['medico_id']) ? (int) $validated['medico_id'] : null;

        if ($existente) {
            $paciente = $existente;
            $this->preservarEmailExistente($validated, $existente);
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

        // Opção 2: cria o vínculo com o médico determinado acima.
        if ($medicoContexto) {
            app(\App\Services\PacienteVinculoService::class)->garantir(
                $paciente,
                $medicoContexto,
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
                'outro_documento' => $paciente->outro_documento,
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
