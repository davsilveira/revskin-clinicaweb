import { Link, router } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import debounce from 'lodash/debounce';

export default function AssistenteReceitaIndex({ tipoPeleOptions, intensidadeOptions, faixaEtariaOptions }) {
    const [step, setStep] = useState(1);
    const [loading, setLoading] = useState(false);

    // Patient search
    const [searchPaciente, setSearchPaciente] = useState('');
    const [pacienteResults, setPacienteResults] = useState([]);
    const [showPacienteDropdown, setShowPacienteDropdown] = useState(false);
    const [selectedPaciente, setSelectedPaciente] = useState(null);
    const [loadingPacientes, setLoadingPacientes] = useState(false);

    // Clinical conditions
    const [condicoes, setCondicoes] = useState({
        tipo_pele: '',
        manchas: '',
        rugas: '',
        acne: '',
        flacidez: '',
        faixa_etaria: '',
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
                return;
            }
            setLoadingPacientes(true);
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
                    setShowPacienteDropdown(true);
                } else {
                    console.error('Search failed:', response.status);
                    setPacienteResults([]);
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
            
            // Incluir todos os produtos, marcando os válidos como selecionados
            setProdutosSelecionados(
                (data.produtos_sugeridos || []).map(p => ({
                    ...p,
                    selecionado: p.produto_id !== null, // Apenas válidos ficam selecionados
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
        tipo_pele: 'Tipo de Pele',
        manchas: 'Manchas',
        rugas: 'Rugas',
        acne: 'Acne',
        flacidez: 'Flacidez',
        faixa_etaria: 'Faixa Etária',
    };

    // Labels para intensidade
    const intensidadeLabelsDefault = ['Não', 'Leve', 'Moderado', 'Intenso'];

    // Normalizar opções (podem vir como array ou objeto do backend)
    const normalizeOptions = (options, fallback) => {
        if (!options) return fallback;
        if (Array.isArray(options)) return options;
        return Object.keys(options);
    };

    // Opções normalizadas
    const tipoPeleOpcoes = normalizeOptions(tipoPeleOptions, ['Normal', 'Oleosa', 'Seca', 'Mista']);
    const intensidadeOpcoes = normalizeOptions(intensidadeOptions, intensidadeLabelsDefault);
    const faixaEtariaOpcoes = normalizeOptions(faixaEtariaOptions, ['Até 30', '30-40', '40-50', '50-60', 'Acima de 60']);

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
                            </div>
                        )}

                        <div className="flex justify-end">
                            <button
                                onClick={() => setStep(2)}
                                disabled={!selectedPaciente}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                Próximo
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 2: Avaliação Clínica */}
                {step === 2 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Avaliação Clínica</h2>
                        <p className="text-gray-500 mb-6">
                            Informe as condições clínicas do paciente para sugestão de tratamento
                        </p>

                        {error && (
                            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                                <p className="font-medium">Erro ao processar</p>
                                <p className="text-sm">{error}</p>
                            </div>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            {/* Tipo de Pele */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Pele
                                </label>
                                <div className="space-y-2">
                                    {tipoPeleOpcoes.map((option) => (
                                        <label key={option} className={`flex items-center p-3 border rounded-lg cursor-pointer transition-all ${
                                            condicoes.tipo_pele === option
                                                ? 'border-emerald-500 bg-emerald-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        }`}>
                                            <input
                                                type="radio"
                                                name="tipo_pele"
                                                value={option}
                                                checked={condicoes.tipo_pele === option}
                                                onChange={(e) => updateCondicao('tipo_pele', e.target.value)}
                                                className="sr-only"
                                            />
                                            <span className={`w-4 h-4 rounded-full border-2 mr-3 flex items-center justify-center ${
                                                condicoes.tipo_pele === option
                                                    ? 'border-emerald-600'
                                                    : 'border-gray-300'
                                            }`}>
                                                {condicoes.tipo_pele === option && (
                                                    <span className="w-2 h-2 bg-emerald-600 rounded-full" />
                                                )}
                                            </span>
                                            {option}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Faixa Etária */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Faixa Etária
                                </label>
                                <select
                                    value={condicoes.faixa_etaria}
                                    onChange={(e) => updateCondicao('faixa_etaria', e.target.value)}
                                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="">Selecione...</option>
                                    {faixaEtariaOpcoes.map((option) => (
                                        <option key={option} value={option}>{option}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Intensidades */}
                            {['manchas', 'rugas', 'acne', 'flacidez'].map((field) => (
                                <div key={field}>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        {condicaoLabels[field]}
                                    </label>
                                    <div className="flex gap-2">
                                        {intensidadeOpcoes.map((value) => (
                                            <button
                                                key={value}
                                                type="button"
                                                onClick={() => updateCondicao(field, value)}
                                                className={`flex-1 py-3 px-2 text-sm rounded-lg border transition-all ${
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

                        <div className="flex justify-between">
                            <button
                                onClick={() => setStep(1)}
                                className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Voltar
                            </button>
                            <button
                                onClick={processarCondicoes}
                                disabled={!condicoes.tipo_pele || loading}
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
                            <div className="space-y-3 mb-6">
                                {produtosSelecionados.map((item, index) => (
                                    <label
                                        key={index}
                                        className={`flex items-start gap-4 p-4 border rounded-lg cursor-pointer transition-all ${
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
                                            className="mt-1 w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500 disabled:opacity-50"
                                        />
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
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
                                            </div>
                                            {item.local_uso && (
                                                <div className="text-sm text-gray-500 mt-1">
                                                    Local de uso: {item.local_uso}
                                                </div>
                                            )}
                                            {item.anotacoes && (
                                                <div className="text-sm text-gray-600 mt-1 italic">
                                                    {item.anotacoes}
                                                </div>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <div className="text-sm text-gray-500">Qtd: {item.quantidade || 1}</div>
                                        </div>
                                    </label>
                                ))}
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
