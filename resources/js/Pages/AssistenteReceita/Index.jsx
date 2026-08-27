import { router } from '@inertiajs/react';
import { useState, useCallback, useEffect, useRef } from 'react';
import ReceitasIndexBackLink from '@/Components/ReceitasIndexBackLink';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import MaskedInput from '@/Components/Form/MaskedInput';
import { validateCPF } from '@/utils/validations';
import { documentoPaciente } from '@/utils/documentoPaciente';
import countries from '@/utils/countries';
import debounce from 'lodash/debounce';
import ClinicalToggleSwitch from '@/Components/AssistenteReceita/ClinicalToggleSwitch';
import DatePickerField from '@/Components/Form/DatePickerField';
import PacientesEncontrados from '@/Components/PacientesEncontrados';
import usePacientesCandidatos from '@/hooks/usePacientesCandidatos';
import { vincularPacienteAoMedico } from '@/utils/vincularPaciente';

const MSG_CPF_OBRIGATORIO = 'O CPF é obrigatório para pacientes no Brasil.';

export default function AssistenteReceitaIndex({ 
    tipoPeleOptions, 
    intensidadeOptions, 
    fototipoOptions = [],
    medicos = [],
    currentMedicoId = null,
    isAdmin = false,
    initialPaciente = null,
    initialPacienteMedicoId = null,
    initialPacienteMedicoLabel = null,
    initialPacienteFromQuery = false,
}) {
    // Com paciente vindo de ?paciente_id=: ir direto à avaliação, salvo quando falta médico no paciente
    // e o utilizador não é admin nem tem medico_id (precisa do passo 1).
    const canSkipStep1 =
        !!initialPaciente &&
        (!!initialPacienteMedicoId || !!currentMedicoId || isAdmin);
    const [step, setStep] = useState(canSkipStep1 ? 2 : 1);
    const [loading, setLoading] = useState(false);

    // Patient search
    const [searchPaciente, setSearchPaciente] = useState('');
    const [pacienteResults, setPacienteResults] = useState([]);
    const [showPacienteDropdown, setShowPacienteDropdown] = useState(false);
    const [selectedPaciente, setSelectedPaciente] = useState(null);
    const [loadingPacientes, setLoadingPacientes] = useState(false);
    const [noResults, setNoResults] = useState(false);
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [creatingPaciente, setCreatingPaciente] = useState(false);
    const [createError, setCreateError] = useState('');
    const [loadingCep, setLoadingCep] = useState(false);
    const [fieldErrors, setFieldErrors] = useState({});
    
    // New patient form
    const [novoPaciente, setNovoPaciente] = useState({
        nome: '',
        pais: 'Brasil',
        cpf: '',
        outro_documento: '',
        data_nascimento: '',
        sexo: '',
        email1: '',
        telefone1: '',
        celular: '',
        cep: '',
        endereco: '',
        numero: '',
        complemento: '',
        bairro: '',
        cidade: '',
        uf: '',
    });
    
    // Médico: use patient's linked medico if available, otherwise user's own medico_id.
    // For admin without linked medico on patient: don't pre-select (force explicit choice).
    const resolvedMedicoId = initialPacienteMedicoId || currentMedicoId || null;
    const [selectedMedicoId, setSelectedMedicoId] = useState(resolvedMedicoId);
    const medicoIsLocked = !!initialPacienteMedicoId || !isAdmin;

    // Clinical conditions
    const [condicoes, setCondicoes] = useState({
        gravidez: false,
        rosacea: false,
        fototipo: '',
        tipo_pele: '',
        manchas: '',
        rugas: '',
        acne: '',
        flacidez: '',
    });

    // Error state
    const [error, setError] = useState('');

    // Debounced search for patients
    const searchPacientes = useCallback(
        debounce(async (term) => {
            if (term.length < 2) {
                setPacienteResults([]);
                setShowPacienteDropdown(false);
                setNoResults(false);
                return;
            }
            setLoadingPacientes(true);
            setNoResults(false);
            try {
                const response = await fetch(`/api/pacientes/search?q=${encodeURIComponent(term)}`, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (response.ok) {
                    const results = await response.json();
                    setPacienteResults(results);
                    setShowPacienteDropdown(results.length > 0);
                    setNoResults(results.length === 0 && term.length >= 2);
                } else {
                    console.error('Search failed:', response.status);
                    setPacienteResults([]);
                    setNoResults(true);
                }
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingPacientes(false);
            }
        }, 300),
        []
    );

    useEffect(() => {
        searchPacientes(searchPaciente);
    }, [searchPaciente, searchPacientes]);

    useEffect(() => {
        if (initialPaciente) {
            setSelectedPaciente(initialPaciente);
        }
    }, [initialPaciente]);

    const selectPaciente = (paciente) => {
        setSelectedPaciente(paciente);
        setSearchPaciente('');
        setShowPacienteDropdown(false);
        setNoResults(false);
        setShowCreateForm(false);
    };

    /**
     * A busca acima só devolve "meus pacientes" — não acha quem existe no sistema mas ainda
     * não é meu, caso de todos os clientes importados do oList. Esta busca é por nome em toda
     * a base; escolher um cria o vínculo na hora, sem recadastrar.
     */
    const {
        candidatos: candidatosGlobais,
        total: totalCandidatosGlobais,
        buscando: buscandoCandidatosGlobais,
        limpar: limparCandidatosGlobais,
    } = usePacientesCandidatos({
        termo: showCreateForm ? novoPaciente.nome : searchPaciente,
        habilitado: !selectedPaciente,
        medicoId: selectedMedicoId,
    });

    /** Há cadastro do sistema listado como opção (fora os que já são meus e já apareceram acima). */
    const temCandidatosGlobais = candidatosGlobais.some(
        (c) => !c.ja_vinculado && !pacienteResults.some((p) => p.id === c.id)
    );

    const vinculandoRef = useRef(false);

    const usarPacienteDoSistema = async (candidato) => {
        if (vinculandoRef.current) return;
        vinculandoRef.current = true;
        try {
            const { paciente: vinculado, erro } = await vincularPacienteAoMedico(candidato.id, {
                medicoId: selectedMedicoId,
            });
            if (vinculado) {
                limparCandidatosGlobais();
                setShowCreateForm(false);
                setCreateError('');
                setFieldErrors({});
                selectPaciente(vinculado);
            } else {
                setCreateError(erro);
                setError(erro);
            }
        } finally {
            vinculandoRef.current = false;
        }
    };

    const openCreateForm = () => {
        setShowCreateForm(true);
        const trimmed = searchPaciente.trim();
        const digitsOnly = trimmed.replace(/\D/g, '');
        // Se o texto digitado contém 11+ dígitos e é composto apenas por números/pontos/hífens, tratar como CPF
        const isCpf = digitsOnly.length >= 11 && /^[\d.\-/]+$/.test(trimmed);
        if (isCpf) {
            // Formatar como CPF: 000.000.000-00
            const formatted = digitsOnly.substring(0, 11).replace(
                /(\d{3})(\d{3})(\d{3})(\d{2})/,
                '$1.$2.$3-$4'
            );
            setNovoPaciente(prev => ({ ...prev, cpf: formatted }));
        } else {
            setNovoPaciente(prev => ({ ...prev, nome: trimmed }));
        }
        setSearchPaciente('');
        setShowPacienteDropdown(false);
        setNoResults(false);
    };

    /** País vazio conta como Brasil (default do cadastro). */
    const novoPacienteBrasil = !novoPaciente.pais || novoPaciente.pais === 'Brasil';

    const updateNovoPaciente = (field, value) => {
        setNovoPaciente(prev => ({ ...prev, [field]: value }));
    };

    const buscarCep = async () => {
        const cepLimpo = novoPaciente.cep?.replace(/\D/g, '');
        if (!cepLimpo || cepLimpo.length < 8) return;

        setLoadingCep(true);
        try {
            const response = await fetch(`/api/cep/${cepLimpo}`);
            const result = await response.json();
            if (result.success) {
                setNovoPaciente(prev => ({
                    ...prev,
                    endereco: result.data.logradouro || '',
                    bairro: result.data.bairro || '',
                    cidade: result.data.localidade || '',
                    uf: result.data.uf || '',
                }));
            }
        } catch (error) {
            console.error('Erro ao buscar CEP:', error);
        } finally {
            setLoadingCep(false);
        }
    };

    const validateNovoPaciente = () => {
        const errors = {};
        
        // Nome é obrigatório
        if (!novoPaciente.nome || novoPaciente.nome.trim().length < 2) {
            errors.nome = 'O nome é obrigatório';
        }
        
        // Data de nascimento é obrigatória
        if (!novoPaciente.data_nascimento) {
            errors.data_nascimento = 'A data de nascimento é obrigatória';
        }
        
        // Celular é obrigatório
        const celularLimpo = novoPaciente.celular?.replace(/\D/g, '');
        if (!celularLimpo || celularLimpo.length < 10) {
            errors.celular = 'O celular é obrigatório';
        }
        
        // CPF: obrigatório no Brasil; fora do Brasil o documento é opcional.
        const cpfLimpo = novoPaciente.cpf?.replace(/\D/g, '');
        if (cpfLimpo && cpfLimpo.length > 0) {
            if (cpfLimpo.length !== 11 || !validateCPF(novoPaciente.cpf)) {
                errors.cpf = 'CPF inválido';
            }
        } else if (novoPacienteBrasil) {
            errors.cpf = MSG_CPF_OBRIGATORIO;
        }

        // E-mail é opcional (decisão do cliente): em branco é válido. Só um endereço mal
        // formado é erro — inclusive underline no domínio, que o backend rejeita.
        const email = novoPaciente.email1?.trim();
        if (email && !/^[^\s@]+@[^\s@_]+\.[^\s@_]+$/.test(email)) {
            errors.email1 = 'Informe um e-mail válido';
        }

        if (!String(novoPaciente.cidade || '').trim()) {
            errors.cidade = 'A cidade é obrigatória';
        }
        if (!String(novoPaciente.uf || '').trim()) {
            errors.uf = 'O estado é obrigatório';
        }

        setFieldErrors(errors);
        return Object.keys(errors).length === 0;
    };

    const createPaciente = async () => {
        setCreatingPaciente(true);
        setCreateError('');

        try {
            // Obter token CSRF dinamicamente do meta tag (atualizado pelo Inertia)
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (!csrfToken) {
                throw new Error('Token CSRF não encontrado. Por favor, recarregue a página.');
            }
            
            const response = await fetch('/api/pacientes/quick-create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(novoPaciente),
            });

            if (!response.ok) {
                // Se for erro 419, recarregar a página para obter novo token CSRF
                if (response.status === 419) {
                    window.location.reload();
                    return false;
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || errorData.message || 'Erro ao cadastrar paciente');
            }

            const data = await response.json();

            if (data.success && data.paciente) {
                setSelectedPaciente(data.paciente);
                setShowCreateForm(false);
                setNovoPaciente({
                    nome: '', cpf: '', outro_documento: '', data_nascimento: '', sexo: '',
                    email1: '', telefone1: '', celular: '', cep: '', endereco: '', numero: '',
                    complemento: '', bairro: '', cidade: '', uf: '', pais: 'Brasil',
                });
                setFieldErrors({});
                return true;
            } else {
                setCreateError(data.error || data.message || 'Erro ao cadastrar paciente');
                return false;
            }
        } catch (error) {
            console.error('Erro ao criar paciente:', error);
            setCreateError(error.message || 'Erro ao cadastrar paciente');
            return false;
        } finally {
            setCreatingPaciente(false);
        }
    };
    
    const handleProximo = async () => {
        // Se já tem paciente selecionado, apenas avançar
        if (selectedPaciente) {
            setStep(2);
            return;
        }
        
        // Se está criando novo paciente, validar e criar
        if (showCreateForm) {
            if (!validateNovoPaciente()) {
                return;
            }
            
            const success = await createPaciente();
            if (success) {
                setStep(2);
            }
        }
    };

    const updateCondicao = (field, value) => {
        setCondicoes(prev => ({ ...prev, [field]: value }));
    };

    const processarCondicoes = () => {
        if (!selectedPaciente) return;

        setLoading(true);
        setError('');
        
        const { gravidez, rosacea, ...outrasCondicoes } = condicoes;

        // Motor de regras e legado esperam "Sim" / "Não" para estes campos
        router.post('/assistente-receita/processar', {
            ...outrasCondicoes,
            gravidez: gravidez ? 'Sim' : 'Não',
            rosacea: rosacea ? 'Sim' : 'Não',
            paciente_id: selectedPaciente.id,
            medico_id: selectedMedicoId,
        }, {
            preserveState: false,
            preserveScroll: false,
            onError: (errors) => {
                console.error('Erro ao processar:', errors);
                // Tratar erros de validação ou outros erros
                if (errors.error) {
                    setError(errors.error);
                } else if (typeof errors === 'string') {
                    setError(errors);
                } else if (errors.message) {
                    setError(errors.message);
                } else {
                    setError('Erro ao processar condições');
                }
                setLoading(false);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    const condicaoLabels = {
        gravidez: 'Gravidez',
        rosacea: 'Rosácea',
        fototipo: 'Fototipo',
        tipo_pele: 'Tipo de Pele',
        manchas: 'Manchas',
        rugas: 'Rugas',
        acne: 'Acne',
        flacidez: 'Flacidez',
    };

    // Labels para intensidade
    const intensidadeLabelsDefault = ['Pouca ou Nenhuma', 'Moderado', 'Intenso'];

    // Normalizar opções (podem vir como array ou objeto do backend)
    const normalizeOptions = (options, fallback) => {
        if (!options) return fallback;
        if (Array.isArray(options)) return options;
        return Object.keys(options);
    };

    // Opções normalizadas
    const tipoPeleOpcoes = normalizeOptions(tipoPeleOptions, ['Seca', 'Normal', 'Mista Ressecada', 'Mista', 'Oleosa']);
    const intensidadeOpcoes = normalizeOptions(intensidadeOptions, intensidadeLabelsDefault);
    const fototipoOpcoes = normalizeOptions(fototipoOptions, ['1', '1.5', '2', '2.5', '3', '3.5', '4', '4.5']);

    const pageDescription =
        step === 1
            ? 'Busque um paciente ou confirme os dados para seguir para a avaliação clínica.'
            : 'Selecione as condições clínicas para gerar uma receita automaticamente.';

    const lockPatientClear =
        initialPacienteFromQuery &&
        !!selectedPaciente &&
        !!initialPaciente &&
        String(selectedPaciente.id) === String(initialPaciente.id);

    return (
        <DashboardLayout>
            <div className="py-4 lg:py-6 px-0 max-w-4xl mx-auto">
                <ReceitasIndexBackLink className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm mb-4">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar para Receitas
                </ReceitasIndexBackLink>
                <PageHeader title="Assistente de Receitas" description={pageDescription} />

                {/* Step 1: Selecionar Paciente */}
                {step === 1 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Selecione o Paciente</h2>

                        {selectedPaciente ? (
                            <div className="mb-6 space-y-3">
                                <div className="rounded-xl border-2 border-emerald-200 bg-emerald-50/80 p-5 shadow-sm">
                                    <div className="text-xs font-semibold uppercase tracking-wide text-emerald-800 mb-1">
                                        Paciente
                                    </div>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="text-xl font-semibold text-gray-900 leading-tight break-words">
                                                {selectedPaciente.nome}
                                            </div>
                                            {documentoPaciente(selectedPaciente) ? (
                                                <div className="text-sm text-gray-600 mt-1 tabular-nums">{documentoPaciente(selectedPaciente)}</div>
                                            ) : null}
                                        </div>
                                        {!lockPatientClear && (
                                            <button
                                                type="button"
                                                onClick={() => setSelectedPaciente(null)}
                                                className="flex-shrink-0 text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-white/80"
                                                aria-label="Remover paciente"
                                            >
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        )}
                                    </div>
                                </div>
                                {!isAdmin && initialPacienteMedicoLabel ? (
                                    <p className="text-sm text-gray-600 pl-1">
                                        Médico vinculado ao paciente:{' '}
                                        <span className="text-gray-800">{initialPacienteMedicoLabel}</span>
                                    </p>
                                ) : null}
                                {isAdmin && medicos.length > 0 && (
                                    <div className="p-4 rounded-lg border border-gray-200 bg-gray-50 text-sm">
                                        <div className="text-xs font-medium text-gray-500 mb-2">
                                            Médico responsável nesta receita
                                            {!medicoIsLocked && <span className="text-red-500"> *</span>}
                                        </div>
                                        {medicoIsLocked ? (
                                            <div className="text-gray-800">
                                                {medicos.find((m) => m.id === selectedMedicoId)?.label ||
                                                    initialPacienteMedicoLabel ||
                                                    'Médico vinculado'}
                                            </div>
                                        ) : (
                                            <select
                                                value={selectedMedicoId || ''}
                                                onChange={(e) =>
                                                    setSelectedMedicoId(e.target.value ? Number(e.target.value) : null)
                                                }
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                                            >
                                                <option value="">Selecione um médico...</option>
                                                {medicos.map((medico) => (
                                                    <option key={medico.id} value={medico.id}>
                                                        {medico.label}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                )}
                            </div>
                        ) : showCreateForm ? (
                            /* Formulário de Cadastro de Novo Paciente */
                            <div className="mb-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-md font-medium text-gray-900">Novo Paciente</h3>
                                    <button
                                        onClick={() => setShowCreateForm(false)}
                                        className="text-gray-400 hover:text-gray-600"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                {createError && (
                                    <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                                        {createError}
                                    </div>
                                )}

                                {/* Mesma legenda do drawer de paciente: o `*` só comunica se
                                    estiver explicado em algum lugar antes dos campos. */}
                                <p className="mb-3 text-xs text-gray-500">
                                    Campos marcados com <span className="text-red-500">*</span> são obrigatórios.
                                </p>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Nome Completo <span className="text-red-500">*</span>
                                        </label>
                                        <div className="relative">
                                            <input
                                                type="text"
                                                value={novoPaciente.nome}
                                                onChange={(e) => {
                                                    updateNovoPaciente('nome', e.target.value);
                                                    setFieldErrors(prev => ({ ...prev, nome: null }));
                                                }}
                                                className={`w-full px-3 py-2 pr-9 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${
                                                    fieldErrors.nome ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                                }`}
                                            />
                                            {buscandoCandidatosGlobais && (
                                                <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                                                    <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                    </svg>
                                                </span>
                                            )}
                                        </div>
                                        {fieldErrors.nome && (
                                            <p className="mt-1 text-sm text-red-600">{fieldErrors.nome}</p>
                                        )}
                                        {/* Avisa de cadastro já existente ANTES de duplicar */}
                                        <PacientesEncontrados
                                            candidatos={candidatosGlobais}
                                            total={totalCandidatosGlobais}
                                            nomeDigitado={String(novoPaciente.nome || '').trim()}
                                            onSelecionar={usarPacienteDoSistema}
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">País</label>
                                        <select
                                            value={novoPaciente.pais}
                                            onChange={(e) => updateNovoPaciente('pais', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            {countries.map((c) => (
                                                <option key={c.value} value={c.value}>{c.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        {novoPacienteBrasil ? (
                                            <MaskedInput
                                                label="CPF"
                                                mask="000.000.000-00"
                                                value={novoPaciente.cpf}
                                                required
                                                onAccept={(value) => {
                                                    updateNovoPaciente('cpf', value);
                                                    setFieldErrors(prev => ({ ...prev, cpf: null }));
                                                }}
                                                placeholder="000.000.000-00"
                                                error={fieldErrors.cpf}
                                            />
                                        ) : (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Documento</label>
                                                <input
                                                    type="text"
                                                    value={novoPaciente.outro_documento}
                                                    onChange={(e) => updateNovoPaciente('outro_documento', e.target.value)}
                                                    placeholder="Passaporte ou documento local"
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <DatePickerField
                                            label="Data de Nascimento"
                                            value={novoPaciente.data_nascimento}
                                            onChange={(v) => {
                                                updateNovoPaciente('data_nascimento', v);
                                                setFieldErrors((prev) => ({ ...prev, data_nascimento: null }));
                                            }}
                                            required
                                            error={fieldErrors.data_nascimento}
                                            allowType
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                                        <select
                                            value={novoPaciente.sexo}
                                            onChange={(e) => updateNovoPaciente('sexo', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="">Selecione</option>
                                            <option value="M">Masculino</option>
                                            <option value="F">Feminino</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            E-mail
                                        </label>
                                        <input
                                            type="email"
                                            value={novoPaciente.email1}
                                            onChange={(e) => {
                                                updateNovoPaciente('email1', e.target.value);
                                                setFieldErrors(prev => ({ ...prev, email1: null }));
                                            }}
                                            className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${
                                                fieldErrors.email1 ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                            }`}
                                        />
                                        {fieldErrors.email1 && (
                                            <p className="mt-1 text-sm text-red-600">{fieldErrors.email1}</p>
                                        )}
                                    </div>
                                    <div>
                                        <MaskedInput
                                            label="Telefone"
                                            mask="(00) 0000-0000"
                                            value={novoPaciente.telefone1}
                                            onAccept={(value) => updateNovoPaciente('telefone1', value)}
                                            placeholder="(00) 0000-0000"
                                        />
                                    </div>
                                    <div>
                                        <MaskedInput
                                            label="Celular"
                                            mask="(00) 00000-0000"
                                            value={novoPaciente.celular}
                                            onAccept={(value) => {
                                                updateNovoPaciente('celular', value);
                                                setFieldErrors(prev => ({ ...prev, celular: null }));
                                            }}
                                            placeholder="(00) 00000-0000"
                                            error={fieldErrors.celular}
                                            required
                                        />
                                    </div>
                                    
                                    {/* Endereço */}
                                    <div className="md:col-span-2 border-t pt-4 mt-2">
                                        <h4 className="text-sm font-medium text-gray-700 mb-3">Endereço</h4>
                                        <div className="grid grid-cols-1 md:grid-cols-6 gap-4">
                                            <div className="md:col-span-2">
                                                <div className="relative">
                                                    <MaskedInput
                                                        label="CEP"
                                                        mask="00000-000"
                                                        value={novoPaciente.cep}
                                                        onAccept={(value) => updateNovoPaciente('cep', value)}
                                                        onBlur={buscarCep}
                                                        placeholder="00000-000"
                                                    />
                                                    {loadingCep && <span className="absolute right-3 top-10 text-xs text-gray-400">Buscando...</span>}
                                                </div>
                                            </div>
                                            <div className="md:col-span-4">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
                                                <input
                                                    type="text"
                                                    value={novoPaciente.endereco}
                                                    onChange={(e) => updateNovoPaciente('endereco', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>
                                            <div className="md:col-span-1">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                                <input
                                                    type="text"
                                                    value={novoPaciente.numero}
                                                    onChange={(e) => updateNovoPaciente('numero', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                                <input
                                                    type="text"
                                                    value={novoPaciente.complemento}
                                                    onChange={(e) => updateNovoPaciente('complemento', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>
                                            <div className="md:col-span-3">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                                <input
                                                    type="text"
                                                    value={novoPaciente.bairro}
                                                    onChange={(e) => updateNovoPaciente('bairro', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>
                                            <div className="md:col-span-4">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Cidade <span className="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    value={novoPaciente.cidade}
                                                    onChange={(e) => {
                                                        updateNovoPaciente('cidade', e.target.value);
                                                        setFieldErrors((prev) => ({ ...prev, cidade: null }));
                                                    }}
                                                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${
                                                        fieldErrors.cidade ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                                    }`}
                                                />
                                                {fieldErrors.cidade && (
                                                    <p className="mt-1 text-sm text-red-600">{fieldErrors.cidade}</p>
                                                )}
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    {novoPacienteBrasil ? 'UF' : 'Estado/Província'} <span className="text-red-500">*</span>
                                                </label>
                                                {novoPacienteBrasil ? (
                                                    <select
                                                        value={novoPaciente.uf}
                                                        onChange={(e) => {
                                                            updateNovoPaciente('uf', e.target.value);
                                                            setFieldErrors((prev) => ({ ...prev, uf: null }));
                                                        }}
                                                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${
                                                            fieldErrors.uf ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                                        }`}
                                                    >
                                                        <option value="">UF</option>
                                                        {['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => (
                                                            <option key={uf} value={uf}>{uf}</option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <input
                                                        type="text"
                                                        value={novoPaciente.uf}
                                                        onChange={(e) => {
                                                            updateNovoPaciente('uf', e.target.value);
                                                            setFieldErrors((prev) => ({ ...prev, uf: null }));
                                                        }}
                                                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${
                                                            fieldErrors.uf ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                                        }`}
                                                    />
                                                )}
                                                {fieldErrors.uf && (
                                                    <p className="mt-1 text-sm text-red-600">{fieldErrors.uf}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        ) : (
                            <>
                                {isAdmin && medicos.length > 0 && (
                                    <div className="mb-6 p-4 rounded-lg border border-gray-200 bg-gray-50 text-sm">
                                        <div className="text-xs font-medium text-gray-500 mb-2">
                                            Médico responsável nesta receita
                                            {!medicoIsLocked && <span className="text-red-500"> *</span>}
                                        </div>
                                        {medicoIsLocked ? (
                                            <div className="text-gray-800">
                                                {medicos.find((m) => m.id === selectedMedicoId)?.label ||
                                                    initialPacienteMedicoLabel ||
                                                    '—'}
                                            </div>
                                        ) : (
                                            <select
                                                value={selectedMedicoId || ''}
                                                onChange={(e) =>
                                                    setSelectedMedicoId(e.target.value ? Number(e.target.value) : null)
                                                }
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                                            >
                                                <option value="">Selecione um médico...</option>
                                                {medicos.map((medico) => (
                                                    <option key={medico.id} value={medico.id}>
                                                        {medico.label}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                )}
                            <div className="relative mb-6">
                                <div className="relative">
                                    <input
                                        type="text"
                                        placeholder="Digite o nome ou CPF do paciente..."
                                        value={searchPaciente}
                                        onChange={(e) => setSearchPaciente(e.target.value)}
                                        className="w-full px-4 py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                    {/* Gira também durante a busca no sistema todo, senão some antes do resultado. */}
                                    {(loadingPacientes || buscandoCandidatosGlobais) && (
                                        <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg className="animate-spin h-5 w-5 text-gray-400" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                        </div>
                                    )}
                                </div>
                                {showPacienteDropdown && pacienteResults.length > 0 && (
                                    <div className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto">
                                        {pacienteResults.map((paciente) => (
                                            <button
                                                key={paciente.id}
                                                type="button"
                                                onClick={() => selectPaciente(paciente)}
                                                className="w-full text-left px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                            >
                                                <div className="font-medium text-gray-900">{paciente.nome}</div>
                                                <div className="text-sm text-gray-500">
                                                    {[
                                                        documentoPaciente(paciente),
                                                        paciente.data_nascimento
                                                            ? String(paciente.data_nascimento).split('T')[0].split('-').reverse().join('/')
                                                            : null,
                                                        paciente.celular,
                                                    ].filter(Boolean).join(' · ')}
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {/* Existe no sistema (outro médico ou vindo do oList), mas ainda não é meu */}
                                <PacientesEncontrados
                                    candidatos={candidatosGlobais}
                                    total={totalCandidatosGlobais}
                                    ocultarIds={pacienteResults.map((p) => p.id)}
                                    ocultarVinculados
                                    titulo="Já cadastrados no sistema, ainda não são seus pacientes"
                                    rotuloAcao="Selecionar"
                                    onSelecionar={usarPacienteDoSistema}
                                />

                                {/* Opção de incluir paciente quando não encontrado */}
                                {noResults && !loadingPacientes && (
                                    <div className="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                {/* Com candidatos listados acima, "nenhum encontrado" se contradiz. */}
                                                <p className="text-sm text-amber-800 font-medium">
                                                    {temCandidatosGlobais ? 'Não é nenhum dos acima?' : 'Nenhum paciente encontrado'}
                                                </p>
                                                <p className="text-sm text-amber-700">Deseja cadastrar um novo paciente?</p>
                                            </div>
                                            <button
                                                onClick={openCreateForm}
                                                className="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors flex items-center gap-2"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                                </svg>
                                                Incluir Paciente
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                            </>
                        )}

                        <div className={`flex ${showCreateForm ? 'justify-between' : 'justify-end'}`}>
                            {showCreateForm && (
                                <button
                                    onClick={() => {
                                        setShowCreateForm(false);
                                        setFieldErrors({});
                                    }}
                                    className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    Cancelar
                                </button>
                            )}
                            <button
                                onClick={handleProximo}
                                disabled={(!selectedPaciente && !showCreateForm) || !selectedMedicoId || creatingPaciente}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                {creatingPaciente ? (
                                    <>
                                        <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Salvando...
                                    </>
                                ) : (
                                    <>
                                        Próximo
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 2: Avaliação Clínica */}
                {step === 2 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-2">Avaliação Clínica</h2>
                        <p className="text-gray-500 mb-4">
                            Informe as condições clínicas do paciente para sugestão de tratamento
                        </p>

                        {selectedPaciente && (
                            <div className="mb-6 rounded-xl border-2 border-emerald-200 bg-emerald-50/80 p-4">
                                <div className="text-xs font-semibold uppercase tracking-wide text-emerald-800 mb-1">
                                    Paciente
                                </div>
                                <div className="text-lg font-semibold text-gray-900 break-words">{selectedPaciente.nome}</div>
                                {selectedPaciente.cpf ? (
                                    <div className="text-sm text-gray-600 mt-1 tabular-nums">{selectedPaciente.cpf}</div>
                                ) : null}
                            </div>
                        )}

                        {!isAdmin && initialPacienteMedicoLabel ? (
                            <p className="mb-4 text-sm text-gray-600">
                                Médico vinculado ao paciente:{' '}
                                <span className="text-gray-900 font-medium">{initialPacienteMedicoLabel}</span>
                            </p>
                        ) : null}

                        {isAdmin && medicos.length > 0 && !initialPacienteMedicoId && (
                            <div className="mb-6 p-4 rounded-lg border border-gray-200 bg-gray-50 text-sm">
                                <label className="block text-xs font-medium text-gray-500 mb-2">
                                    Médico responsável <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={selectedMedicoId || ''}
                                    onChange={(e) =>
                                        setSelectedMedicoId(e.target.value ? Number(e.target.value) : null)
                                    }
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 bg-white"
                                >
                                    <option value="">Selecione um médico...</option>
                                    {medicos.map((medico) => (
                                        <option key={medico.id} value={medico.id}>
                                            {medico.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {error && (
                            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                                <p className="font-medium">Erro ao processar</p>
                                <p className="text-sm">{error}</p>
                            </div>
                        )}

                        <div className="space-y-6 mb-6">
                            {/* Gravidez */}
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between py-3 border-b border-gray-100">
                                <label className="text-sm font-medium text-gray-700" id="label-gravidez">
                                    Gravidez
                                </label>
                                <ClinicalToggleSwitch
                                    checked={!!condicoes.gravidez}
                                    onChange={(v) => updateCondicao('gravidez', v)}
                                    aria-labelledby="label-gravidez"
                                />
                            </div>

                            {/* Rosácea */}
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between py-3 border-b border-gray-100">
                                <label className="text-sm font-medium text-gray-700" id="label-rosacea">
                                    Rosácea
                                </label>
                                <ClinicalToggleSwitch
                                    checked={!!condicoes.rosacea}
                                    onChange={(v) => updateCondicao('rosacea', v)}
                                    aria-labelledby="label-rosacea"
                                />
                            </div>

                            {/* Fototipo - Range Slider */}
                            <div className="py-3 border-b border-gray-100">
                                <div className="flex items-center justify-between mb-3">
                                    <label className="text-sm font-medium text-gray-700">
                                        Fototipo
                                    </label>
                                    {condicoes.fototipo && (
                                        <span className="text-sm font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full">
                                            {condicoes.fototipo}
                                        </span>
                                    )}
                                </div>
                                <div className="px-2">
                                    <div className="flex justify-between text-xs text-gray-500 mb-2">
                                        {fototipoOpcoes.map((val) => (
                                            <span 
                                                key={val} 
                                                className={`cursor-pointer hover:text-emerald-600 transition-colors ${
                                                    condicoes.fototipo === val ? 'text-emerald-600 font-semibold' : ''
                                                }`}
                                                onClick={() => updateCondicao('fototipo', val)}
                                            >
                                                {val}
                                            </span>
                                        ))}
                                    </div>
                                    <input
                                        type="range"
                                        min="0"
                                        max={fototipoOpcoes.length - 1}
                                        step="1"
                                        value={condicoes.fototipo ? fototipoOpcoes.indexOf(condicoes.fototipo) : 0}
                                        onChange={(e) => updateCondicao('fototipo', fototipoOpcoes[parseInt(e.target.value)])}
                                        className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-emerald-600"
                                    />
                                </div>
                            </div>

                            {/* Tipo de Pele - Horizontal buttons */}
                            <div className="py-3 border-b border-gray-100">
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    Tipo de Pele
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    {tipoPeleOpcoes.map((option) => (
                                        <button
                                            key={option}
                                            type="button"
                                            onClick={() => updateCondicao('tipo_pele', option)}
                                            className={`py-2 px-4 rounded-lg border text-sm font-medium transition-all cursor-pointer ${
                                                condicoes.tipo_pele === option
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                    : 'border-gray-200 hover:border-gray-300 text-gray-600'
                                            }`}
                                        >
                                            {option}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Condições da Pele - Header */}
                            <div className="pt-2">
                                <h3 className="text-sm font-medium text-gray-700 mb-4">Condições da Pele</h3>
                                
                                {/* Intensidades em lista */}
                                <div className="space-y-3">
                                    {['manchas', 'rugas', 'acne', 'flacidez'].map((field) => (
                                        <div key={field} className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between py-3 border-b border-gray-100 last:border-0">
                                            <label className="text-sm font-medium text-gray-600 min-w-0 sm:min-w-[100px]">
                                                {condicaoLabels[field]}
                                            </label>
                                            <div className="flex flex-wrap gap-2 w-full sm:w-auto sm:justify-end">
                                                {intensidadeOpcoes.map((value) => (
                                                    <button
                                                        key={value}
                                                        type="button"
                                                        onClick={() => updateCondicao(field, value)}
                                                        className={`min-h-[44px] py-2 px-3 sm:px-4 text-sm rounded-lg border transition-all whitespace-nowrap cursor-pointer ${
                                                            condicoes[field] === value
                                                                ? 'border-emerald-500 bg-emerald-50 text-emerald-700 font-medium'
                                                                : 'border-gray-200 hover:border-gray-300 text-gray-600'
                                                        }`}
                                                    >
                                                        {value}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between sm:items-stretch pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                onClick={() => setStep(1)}
                                className="min-h-[44px] w-full sm:w-auto px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Voltar
                            </button>
                            <button
                                type="button"
                                onClick={processarCondicoes}
                                disabled={
                                    !condicoes.tipo_pele ||
                                    !selectedMedicoId ||
                                    loading
                                }
                                className="min-h-[44px] w-full sm:w-auto px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                {loading ? (
                                    <>
                                        <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Gerando Receita...
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Gerar Receita
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                )}

            </div>
        </DashboardLayout>
    );
}
