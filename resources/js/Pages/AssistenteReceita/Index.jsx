import { Link, router } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import MaskedInput from '@/Components/Form/MaskedInput';
import { validateCPF } from '@/utils/validations';
import debounce from 'lodash/debounce';

export default function AssistenteReceitaIndex({ 
    tipoPeleOptions, 
    intensidadeOptions, 
    fototipoOptions = [],
    medicos = [],
    currentMedicoId = null,
    isAdmin = false,
}) {
    const [step, setStep] = useState(1);
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
        cpf: '',
        data_nascimento: '',
        sexo: '',
        email1: '',
        telefone1: '',
        telefone2: '',
        cep: '',
        endereco: '',
        numero: '',
        complemento: '',
        bairro: '',
        cidade: '',
        uf: '',
        pais: 'Brasil',
    });
    
    // Médico selection (for admin)
    const [selectedMedicoId, setSelectedMedicoId] = useState(currentMedicoId || (medicos.length > 0 ? medicos[0].id : null));

    // Clinical conditions
    const [condicoes, setCondicoes] = useState({
        gravidez: '',
        rosacea: '',
        fototipo: '',
        tipo_pele: '',
        manchas: '',
        rugas: '',
        acne: '',
        flacidez: '',
    });

    // Suggested products
    const [produtosSugeridos, setProdutosSugeridos] = useState([]);
    const [produtosSelecionados, setProdutosSelecionados] = useState([]);
    const [codigoKarnaugh, setCodigoKarnaugh] = useState('');
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

    const selectPaciente = (paciente) => {
        setSelectedPaciente(paciente);
        setSearchPaciente('');
        setShowPacienteDropdown(false);
        setNoResults(false);
        setShowCreateForm(false);
    };

    const openCreateForm = () => {
        setShowCreateForm(true);
        setNovoPaciente(prev => ({ ...prev, nome: searchPaciente }));
        setSearchPaciente('');
        setShowPacienteDropdown(false);
        setNoResults(false);
    };

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
        const celularLimpo = novoPaciente.telefone2?.replace(/\D/g, '');
        if (!celularLimpo || celularLimpo.length < 10) {
            errors.telefone2 = 'O celular é obrigatório';
        }
        
        // CPF - validar apenas se preenchido
        const cpfLimpo = novoPaciente.cpf?.replace(/\D/g, '');
        if (cpfLimpo && cpfLimpo.length > 0) {
            if (cpfLimpo.length !== 11 || !validateCPF(novoPaciente.cpf)) {
                errors.cpf = 'CPF inválido';
            }
        }
        
        setFieldErrors(errors);
        return Object.keys(errors).length === 0;
    };

    const createPaciente = async () => {
        setCreatingPaciente(true);
        setCreateError('');

        try {
            const response = await fetch('/api/pacientes/quick-create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(novoPaciente),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                setSelectedPaciente(data.paciente);
                setShowCreateForm(false);
                setNovoPaciente({
                    nome: '', cpf: '', data_nascimento: '', sexo: '', email1: '',
                    telefone1: '', telefone2: '', cep: '', endereco: '', numero: '',
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
            setCreateError('Erro ao cadastrar paciente');
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

    const processarCondicoes = async () => {
        setLoading(true);
        setError('');
        try {
            const response = await fetch('/assistente-receita/processar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify(condicoes),
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Erro ${response.status}: ${errorText}`);
            }

            const data = await response.json();
            setCodigoKarnaugh(data.codigo_karnaugh || '');
            setProdutosSugeridos(data.produtos_sugeridos || []);
            
            // Usar o campo 'selecionado' que vem do backend
            // Produtos do primeiro grupo com flag 'marcar' vêm pré-selecionados
            // Produtos do segundo grupo ou adicionados por regras seguem suas configurações
            setProdutosSelecionados(
                (data.produtos_sugeridos || []).map(p => ({
                    ...p,
                    // Usar selecionado do backend, ou fallback para produtos válidos do primeiro grupo
                    selecionado: p.selecionado ?? (p.produto_id !== null && p.grupo === 'primeiro'),
                }))
            );
            setStep(3);
        } catch (err) {
            console.error('Erro ao processar:', err);
            setError(err.message || 'Erro ao processar condições');
        } finally {
            setLoading(false);
        }
    };

    const toggleProduto = (index) => {
        setProdutosSelecionados(prev => 
            prev.map((p, i) => 
                i === index ? { ...p, selecionado: !p.selecionado } : p
            )
        );
    };

    const gerarReceita = async () => {
        if (!selectedPaciente) return;

        // Filtrar apenas produtos selecionados E que existem no banco
        const itensSelecionados = produtosSelecionados.filter(p => p.selecionado && p.produto_id !== null);
        if (itensSelecionados.length === 0) return;

        setLoading(true);
        try {
            const response = await fetch('/assistente-receita/gerar-receita', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                    paciente_id: selectedPaciente.id,
                    medico_id: selectedMedicoId,
                    itens: itensSelecionados.map(p => ({
                        produto_id: p.produto_id,
                        local_uso: p.local_uso,
                        quantidade: p.quantidade || 1,
                        valor_unitario: p.produto?.preco_venda || 0,
                    })),
                }),
            });
            const data = await response.json();
            if (data.receita_id) {
                router.visit(`/receitas/${data.receita_id}/edit`);
            }
        } catch (error) {
            console.error('Erro ao gerar receita:', error);
        } finally {
            setLoading(false);
        }
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

    return (
        <DashboardLayout>
            <div className="p-6 max-w-4xl mx-auto">
                <div className="mb-6">
                    <Link
                        href="/receitas"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Receitas
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Assistente de Receitas</h1>
                    <p className="text-gray-600 mt-1">
                        Selecione as condições clínicas para gerar uma receita automaticamente
                    </p>
                </div>

                {/* Progress Steps */}
                <div className="flex items-center justify-center mb-8">
                    {[1, 2, 3].map((s) => (
                        <div key={s} className="flex items-center">
                            <div className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-all ${
                                step >= s ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-500'
                            }`}>
                                {step > s ? (
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                ) : s}
                            </div>
                            {s < 3 && (
                                <div className={`w-16 md:w-24 h-1 transition-all ${step > s ? 'bg-emerald-600' : 'bg-gray-200'}`} />
                            )}
                        </div>
                    ))}
                </div>

                <div className="flex justify-center gap-6 md:gap-12 text-sm text-gray-600 mb-8">
                    <span className={step === 1 ? 'text-emerald-600 font-medium' : ''}>1. Paciente</span>
                    <span className={step === 2 ? 'text-emerald-600 font-medium' : ''}>2. Avaliação</span>
                    <span className={step === 3 ? 'text-emerald-600 font-medium' : ''}>3. Produtos</span>
                </div>

                {/* Step 1: Selecionar Paciente */}
                {step === 1 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Selecione o Paciente</h2>
                        
                        {/* Seleção de Médico para Admin */}
                        {isAdmin && medicos.length > 0 && (
                            <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <label className="block text-sm font-medium text-blue-800 mb-2">
                                    Médico Responsável
                                </label>
                                <select
                                    value={selectedMedicoId || ''}
                                    onChange={(e) => setSelectedMedicoId(Number(e.target.value))}
                                    className="w-full px-4 py-3 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                                >
                                    {medicos.map((medico) => (
                                        <option key={medico.id} value={medico.id}>
                                            {medico.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        
                        {selectedPaciente ? (
                            <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6">
                                <div>
                                    <div className="font-medium text-gray-900">{selectedPaciente.nome}</div>
                                    <div className="text-sm text-gray-500">{selectedPaciente.cpf}</div>
                                </div>
                                <button
                                    onClick={() => setSelectedPaciente(null)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
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

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Nome Completo <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={novoPaciente.nome}
                                            onChange={(e) => {
                                                updateNovoPaciente('nome', e.target.value);
                                                setFieldErrors(prev => ({ ...prev, nome: null }));
                                            }}
                                            className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${
                                                fieldErrors.nome ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                            }`}
                                        />
                                        {fieldErrors.nome && (
                                            <p className="mt-1 text-sm text-red-600">{fieldErrors.nome}</p>
                                        )}
                                    </div>
                                    <div>
                                        <MaskedInput
                                            label="CPF"
                                            mask="000.000.000-00"
                                            value={novoPaciente.cpf}
                                            onAccept={(value) => {
                                                updateNovoPaciente('cpf', value);
                                                setFieldErrors(prev => ({ ...prev, cpf: null }));
                                            }}
                                            placeholder="000.000.000-00"
                                            error={fieldErrors.cpf}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Data de Nascimento <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="date"
                                            value={novoPaciente.data_nascimento}
                                            onChange={(e) => {
                                                updateNovoPaciente('data_nascimento', e.target.value);
                                                setFieldErrors(prev => ({ ...prev, data_nascimento: null }));
                                            }}
                                            className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${
                                                fieldErrors.data_nascimento ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                            }`}
                                        />
                                        {fieldErrors.data_nascimento && (
                                            <p className="mt-1 text-sm text-red-600">{fieldErrors.data_nascimento}</p>
                                        )}
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
                                        <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                        <input
                                            type="email"
                                            value={novoPaciente.email1}
                                            onChange={(e) => updateNovoPaciente('email1', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        />
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
                                            value={novoPaciente.telefone2}
                                            onAccept={(value) => {
                                                updateNovoPaciente('telefone2', value);
                                                setFieldErrors(prev => ({ ...prev, telefone2: null }));
                                            }}
                                            placeholder="(00) 00000-0000"
                                            error={fieldErrors.telefone2}
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
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                                <input
                                                    type="text"
                                                    value={novoPaciente.cidade}
                                                    onChange={(e) => updateNovoPaciente('cidade', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                                <select
                                                    value={novoPaciente.uf}
                                                    onChange={(e) => updateNovoPaciente('uf', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                >
                                                    <option value="">UF</option>
                                                    {['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => (
                                                        <option key={uf} value={uf}>{uf}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        ) : (
                            <div className="relative mb-6">
                                <div className="relative">
                                    <input
                                        type="text"
                                        placeholder="Digite o nome ou CPF do paciente..."
                                        value={searchPaciente}
                                        onChange={(e) => setSearchPaciente(e.target.value)}
                                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                    {loadingPacientes && (
                                        <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg className="animate-spin h-5 w-5 text-gray-400" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                        </div>
                                    )}
                                </div>
                                {showPacienteDropdown && pacienteResults.length > 0 && (
                                    <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                                        {pacienteResults.map((paciente) => (
                                            <button
                                                key={paciente.id}
                                                type="button"
                                                onClick={() => selectPaciente(paciente)}
                                                className="w-full text-left px-4 py-3 hover:bg-gray-50 border-b last:border-0"
                                            >
                                                <div className="font-medium text-gray-900">{paciente.nome}</div>
                                                <div className="text-sm text-gray-500">{paciente.cpf}</div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                
                                {/* Opção de incluir paciente quando não encontrado */}
                                {noResults && !loadingPacientes && (
                                    <div className="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm text-amber-800 font-medium">Nenhum paciente encontrado</p>
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
                                disabled={(!selectedPaciente && !showCreateForm) || (isAdmin && !selectedMedicoId) || creatingPaciente}
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
                        <p className="text-gray-500 mb-6">
                            Informe as condições clínicas do paciente para sugestão de tratamento
                        </p>

                        {error && (
                            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                                <p className="font-medium">Erro ao processar</p>
                                <p className="text-sm">{error}</p>
                            </div>
                        )}

                        <div className="space-y-6 mb-6">
                            {/* Gravidez */}
                            <div className="flex items-center justify-between py-3 border-b border-gray-100">
                                <label className="text-sm font-medium text-gray-700">
                                    Gravidez
                                </label>
                                <div className="flex gap-2">
                                    {['Sim', 'Não'].map((option) => (
                                        <button
                                            key={option}
                                            type="button"
                                            onClick={() => updateCondicao('gravidez', option)}
                                            className={`py-2 px-6 rounded-lg border text-sm font-medium transition-all cursor-pointer ${
                                                condicoes.gravidez === option
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                    : 'border-gray-200 hover:border-gray-300 text-gray-600'
                                            }`}
                                        >
                                            {option}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Rosácea */}
                            <div className="flex items-center justify-between py-3 border-b border-gray-100">
                                <label className="text-sm font-medium text-gray-700">
                                    Rosácea
                                </label>
                                <div className="flex gap-2">
                                    {['Sim', 'Não'].map((option) => (
                                        <button
                                            key={option}
                                            type="button"
                                            onClick={() => updateCondicao('rosacea', option)}
                                            className={`py-2 px-6 rounded-lg border text-sm font-medium transition-all cursor-pointer ${
                                                condicoes.rosacea === option
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                    : 'border-gray-200 hover:border-gray-300 text-gray-600'
                                            }`}
                                        >
                                            {option}
                                        </button>
                                    ))}
                                </div>
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
                                        <div key={field} className="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                                            <label className="text-sm font-medium text-gray-600 min-w-[100px]">
                                                {condicaoLabels[field]}
                                            </label>
                                            <div className="flex gap-2">
                                                {intensidadeOpcoes.map((value) => (
                                                    <button
                                                        key={value}
                                                        type="button"
                                                        onClick={() => updateCondicao(field, value)}
                                                        className={`py-2 px-4 text-sm rounded-lg border transition-all whitespace-nowrap cursor-pointer ${
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

                        <div className="flex justify-between pt-4 border-t border-gray-200">
                            <button
                                onClick={() => setStep(1)}
                                className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Voltar
                            </button>
                            <button
                                onClick={processarCondicoes}
                                disabled={!condicoes.tipo_pele || !condicoes.gravidez || !condicoes.rosacea || loading}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                {loading ? (
                                    <>
                                        <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Processando...
                                    </>
                                ) : (
                                    <>
                                        Buscar Tratamentos
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 3: Produtos Sugeridos */}
                {step === 3 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div className="flex justify-between items-start mb-4">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">Produtos Sugeridos</h2>
                                <p className="text-gray-500 mt-1">
                                    Selecione os produtos que deseja incluir na receita
                                </p>
                            </div>
                            {codigoKarnaugh && (
                                <div className="bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-lg text-sm">
                                    <span className="text-blue-600 font-medium">Caso: </span>
                                    <span className="text-blue-800 font-mono">{codigoKarnaugh}</span>
                                </div>
                            )}
                        </div>

                        {produtosSelecionados.length > 0 ? (
                            <div className="space-y-6 mb-6">
                                {/* Produtos Recomendados (primeiro grupo) */}
                                {produtosSelecionados.filter(p => p.grupo === 'primeiro').length > 0 && (
                                    <div>
                                        <div className="flex items-center gap-2 mb-3">
                                            <div className="w-3 h-3 bg-emerald-500 rounded-full"></div>
                                            <h3 className="font-medium text-gray-900">Recomendados para o Tratamento</h3>
                                            <span className="text-xs text-gray-500">({produtosSelecionados.filter(p => p.grupo === 'primeiro' && p.selecionado).length} selecionados)</span>
                                        </div>
                                        <div className="space-y-2">
                                            {produtosSelecionados.map((item, index) => item.grupo === 'primeiro' && (
                                                <label
                                                    key={index}
                                                    className={`flex items-start gap-4 p-3 border rounded-lg cursor-pointer transition-all ${
                                                        item.nao_encontrado
                                                            ? 'border-amber-300 bg-amber-50 opacity-60 cursor-not-allowed'
                                                            : item.selecionado
                                                                ? 'border-emerald-500 bg-emerald-50'
                                                                : 'border-gray-200 bg-gray-50 opacity-60'
                                                    }`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={item.selecionado && !item.nao_encontrado}
                                                        onChange={() => !item.nao_encontrado && toggleProduto(index)}
                                                        disabled={item.nao_encontrado}
                                                        className="mt-0.5 w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500 disabled:opacity-50"
                                                    />
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <span className="font-medium text-gray-900">
                                                                {item.produto?.codigo && (
                                                                    <span className="text-emerald-600 mr-1">[{item.produto.codigo}]</span>
                                                                )}
                                                                {item.produto?.nome || 'Produto'}
                                                            </span>
                                                            {item.nao_encontrado && (
                                                                <span className="px-2 py-0.5 text-xs bg-amber-100 text-amber-700 rounded-full">
                                                                    Não cadastrado
                                                                </span>
                                                            )}
                                                            {item.local_uso && (
                                                                <span className="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                                                    {item.local_uso}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {item.anotacoes && (
                                                            <div className="text-sm text-gray-600 mt-1 italic truncate">
                                                                {item.anotacoes}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="text-right flex-shrink-0">
                                                        <div className="text-sm text-gray-500">x{item.quantidade || 1}</div>
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Produtos Opcionais (segundo grupo) */}
                                {produtosSelecionados.filter(p => p.grupo !== 'primeiro').length > 0 && (
                                    <div>
                                        <div className="flex items-center gap-2 mb-3">
                                            <div className="w-3 h-3 bg-gray-400 rounded-full"></div>
                                            <h3 className="font-medium text-gray-700">Opcionais</h3>
                                            <span className="text-xs text-gray-500">({produtosSelecionados.filter(p => p.grupo !== 'primeiro' && p.selecionado).length} selecionados)</span>
                                        </div>
                                        <div className="space-y-2">
                                            {produtosSelecionados.map((item, index) => item.grupo !== 'primeiro' && (
                                                <label
                                                    key={index}
                                                    className={`flex items-start gap-4 p-3 border rounded-lg cursor-pointer transition-all ${
                                                        item.nao_encontrado
                                                            ? 'border-amber-300 bg-amber-50 opacity-60 cursor-not-allowed'
                                                            : item.selecionado
                                                                ? 'border-blue-400 bg-blue-50'
                                                                : 'border-gray-200 bg-gray-50 opacity-60'
                                                    }`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={item.selecionado && !item.nao_encontrado}
                                                        onChange={() => !item.nao_encontrado && toggleProduto(index)}
                                                        disabled={item.nao_encontrado}
                                                        className="mt-0.5 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 disabled:opacity-50"
                                                    />
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <span className="font-medium text-gray-900">
                                                                {item.produto?.codigo && (
                                                                    <span className="text-blue-600 mr-1">[{item.produto.codigo}]</span>
                                                                )}
                                                                {item.produto?.nome || 'Produto'}
                                                            </span>
                                                            {item.nao_encontrado && (
                                                                <span className="px-2 py-0.5 text-xs bg-amber-100 text-amber-700 rounded-full">
                                                                    Não cadastrado
                                                                </span>
                                                            )}
                                                            {item.local_uso && (
                                                                <span className="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                                                    {item.local_uso}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {item.anotacoes && (
                                                            <div className="text-sm text-gray-600 mt-1 italic truncate">
                                                                {item.anotacoes}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="text-right flex-shrink-0">
                                                        <div className="text-sm text-gray-500">x{item.quantidade || 1}</div>
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-12 text-gray-500 mb-6 bg-gray-50 rounded-lg">
                                <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <p className="font-medium">Nenhum produto sugerido</p>
                                <p className="text-sm mt-1">
                                    Não encontramos produtos para as condições informadas.
                                </p>
                                <button
                                    onClick={() => setStep(2)}
                                    className="mt-4 text-emerald-600 hover:text-emerald-700 text-sm font-medium"
                                >
                                    ← Alterar condições
                                </button>
                            </div>
                        )}

                        <div className="flex justify-between items-center pt-4 border-t border-gray-200">
                            <button
                                onClick={() => setStep(2)}
                                className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Voltar
                            </button>
                            <div className="flex items-center gap-4">
                                <span className="text-sm text-gray-500">
                                    {produtosSelecionados.filter(p => p.selecionado).length} produto(s) selecionado(s)
                                </span>
                                <button
                                    onClick={gerarReceita}
                                    disabled={produtosSelecionados.filter(p => p.selecionado).length === 0 || loading}
                                    className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                >
                                    {loading ? (
                                        <>
                                            <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Gerando...
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
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
