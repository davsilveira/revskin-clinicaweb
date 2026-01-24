import { useForm, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import debounce from 'lodash/debounce';
import useAutoSave from '@/hooks/useAutoSave';

// Mapeamento de local_uso para nomes mais descritivos
const localUsoLabels = {
    'face': 'Creme Facial',
    'rosto': 'Creme Facial',
    'olhos': 'Creme dos Olhos',
    'corpo': 'Creme Corpo',
    'maos': 'Creme Mãos',
    'pes': 'Creme Pés',
    'cabelo': 'Capilar',
    'solar': 'Protetor Solar',
    'limpeza': 'Limpeza',
    'serum': 'Sérum',
    'mascara': 'Máscara',
    'tonalite': 'Base Tonalité',
};

const formatLocalUso = (localUso) => {
    if (!localUso) return '-';
    // Se já tem um nome descritivo (mais de uma palavra ou começa com maiúscula), usar como está
    if (localUso.includes(' ') || /^[A-Z]/.test(localUso)) {
        return localUso;
    }
    // Caso contrário, tentar mapear
    const key = localUso.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return localUsoLabels[key] || localUso;
};

export default function ReceitaForm({ receita, paciente: initialPaciente, produtos, medicos, defaultMedicoId, receitasAnteriores = [], bloqueadaParaEdicao = false }) {
    const { auth } = usePage().props;
    const isMedico = auth.user.role === 'medico';
    const isEditing = !!receita;
    const isReadOnly = bloqueadaParaEdicao;
    const [currentReceitaId, setCurrentReceitaId] = useState(receita?.id || null);
    const isFirstRender = useRef(true);
    const [showFinalizarModal, setShowFinalizarModal] = useState(false);
    const [expandedReceitas, setExpandedReceitas] = useState({});
    
    const { data, setData, post, put, processing, errors } = useForm({
        paciente_id: receita?.paciente_id || initialPaciente?.id || '',
        medico_id: receita?.medico_id || defaultMedicoId || '',
        data_receita: receita?.data_receita ? receita.data_receita.split('T')[0] : new Date().toISOString().split('T')[0],
        anotacoes: receita?.anotacoes || '',
        anotacoes_paciente: receita?.anotacoes_paciente || '',
        desconto_percentual: receita?.desconto_percentual || 0,
        desconto_motivo: receita?.desconto_motivo || '',
        valor_caixa: receita?.valor_caixa || 0,
        valor_frete: receita?.valor_frete || 0,
        status: receita?.status || 'rascunho',
        itens: receita?.itens?.map(item => ({
            produto_id: item.produto_id,
            local_uso: item.local_uso || '',
            anotacoes: item.anotacoes || '',
            quantidade: item.quantidade,
            valor_unitario: parseFloat(item.valor_unitario) || 0,
            imprimir: item.imprimir ?? true,
            grupo: item.grupo || 'recomendado',
        })) || [],
    });

    const [searchPaciente, setSearchPaciente] = useState('');
    const [pacienteResults, setPacienteResults] = useState([]);
    const [showPacienteDropdown, setShowPacienteDropdown] = useState(false);
    const [selectedPaciente, setSelectedPaciente] = useState(receita?.paciente || initialPaciente || null);
    const [loadingPacientes, setLoadingPacientes] = useState(false);
    const lastItemRef = useRef(null);

    // Autosave function
    const performAutoSave = useCallback(async () => {
        if (!data.paciente_id || !data.medico_id) return;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch('/api/receitas/autosave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                id: currentReceitaId,
                ...data,
            }),
        });
        
        if (!response.ok) throw new Error('Autosave failed');
        
        const result = await response.json();
        if (result.id && !currentReceitaId) {
            setCurrentReceitaId(result.id);
            // Update URL to edit mode without full page reload
            window.history.replaceState({}, '', `/receitas/${result.id}/edit`);
        }
        return result;
    }, [data, currentReceitaId]);

    const canAutoSave = data.paciente_id && data.medico_id;
    const { 
        lastSavedText, 
        isSaving: isAutoSaving, 
        triggerAutoSave,
    } = useAutoSave(performAutoSave, 2000, canAutoSave);

    // Trigger autosave when data changes
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (canAutoSave) {
            triggerAutoSave();
        }
    }, [data, canAutoSave, triggerAutoSave]);

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
                const response = await fetch(`/api/pacientes/search?q=${encodeURIComponent(term)}`);
                const results = await response.json();
                setPacienteResults(results);
                setShowPacienteDropdown(true);
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
        setData('paciente_id', paciente.id);
        setSearchPaciente('');
        setShowPacienteDropdown(false);
    };

    const addItem = () => {
        setData('itens', [
            ...data.itens,
            {
                produto_id: '',
                local_uso: '',
                anotacoes: '',
                quantidade: 1,
                valor_unitario: 0,
                imprimir: true,
                grupo: 'recomendado',
            },
        ]);
        // Scroll para o novo item após o DOM atualizar
        setTimeout(() => {
            lastItemRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    };

    const removeItem = (index) => {
        const newItens = [...data.itens];
        newItens.splice(index, 1);
        setData('itens', newItens);
    };

    const updateItem = (index, field, value) => {
        const newItens = [...data.itens];
        newItens[index] = { ...newItens[index], [field]: value };

        // Se mudou o produto, atualiza o preco e local_uso padrao
        if (field === 'produto_id') {
            const produto = produtos.find(p => p.id === parseInt(value));
            if (produto) {
                newItens[index].valor_unitario = parseFloat(produto.preco_venda) || parseFloat(produto.preco) || 0;
                newItens[index].local_uso = produto.local_uso || '';
            }
        }

        setData('itens', newItens);
    };

    const calcularSubtotalItem = (item) => {
        return item.quantidade * item.valor_unitario;
    };

    const calcularSubtotal = () => {
        return data.itens
            .filter(item => item.imprimir)
            .reduce((total, item) => total + calcularSubtotalItem(item), 0);
    };

    const calcularDesconto = () => {
        const subtotal = calcularSubtotal();
        return subtotal * (data.desconto_percentual / 100);
    };

    const calcularTotal = () => {
        const subtotal = calcularSubtotal();
        const desconto = calcularDesconto();
        const frete = parseFloat(data.valor_frete) || 0;
        return subtotal - desconto + frete;
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/receitas/${receita.id}`);
        } else {
            post('/receitas');
        }
    };

    const finalizarReceita = () => {
        const receitaId = currentReceitaId || receita?.id;
        if (!receitaId) return;
        
        router.put(`/receitas/${receitaId}`, {
            ...data,
            status: 'finalizada',
        });
        setShowFinalizarModal(false);
    };

    const toggleReceitaExpanded = (id) => {
        setExpandedReceitas(prev => ({
            ...prev,
            [id]: !prev[id]
        }));
    };

    return (
        <DashboardLayout>
            <div className="p-6">
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
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">
                        {isEditing ? `Editar Receita #${receita.numero || receita.id}` : 'Nova Receita'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Dados Básicos - Compacto para Edição */}
                    {isEditing && selectedPaciente ? (
                        <div className="bg-white rounded-lg shadow-sm border border-gray-200 px-4 py-3">
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                                <div className="flex items-center gap-2">
                                    <span className="text-gray-500">Paciente:</span>
                                    <span className="font-medium text-gray-900">{selectedPaciente.nome}</span>
                                    <span className="text-gray-400">({selectedPaciente.cpf})</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-gray-500">Data:</span>
                                    <input
                                        type="date"
                                        value={data.data_receita}
                                        onChange={(e) => setData('data_receita', e.target.value)}
                                        className="px-2 py-1 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-gray-500">Médico:</span>
                                    {medicos?.length === 1 ? (
                                        <span className="font-medium text-gray-900">{medicos[0].nome}</span>
                                    ) : (
                                        <select
                                            value={data.medico_id}
                                            onChange={(e) => setData('medico_id', e.target.value)}
                                            className="px-2 py-1 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            {medicos?.map((medico) => (
                                                <option key={medico.id} value={medico.id}>{medico.nome}</option>
                                            ))}
                                        </select>
                                    )}
                                </div>
                                <div className={`px-2 py-0.5 rounded text-xs font-medium ${
                                    data.status === 'finalizada' ? 'bg-green-100 text-green-700' :
                                    data.status === 'cancelada' ? 'bg-red-100 text-red-700' :
                                    'bg-gray-100 text-gray-600'
                                }`}>
                                    {data.status === 'finalizada' ? 'Finalizada' :
                                     data.status === 'cancelada' ? 'Cancelada' : 'Rascunho'}
                                </div>
                            </div>
                        </div>
                    ) : (
                        /* Form completo para Nova Receita */
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados da Receita</h2>
                            
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {/* Paciente */}
                                <div className="relative md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Paciente *</label>
                                    {selectedPaciente ? (
                                        <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                                            <div>
                                                <span className="font-medium text-gray-900">{selectedPaciente.nome}</span>
                                                <span className="text-sm text-gray-500 ml-2">{selectedPaciente.cpf}</span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => { setSelectedPaciente(null); setData('paciente_id', ''); }}
                                                className="text-gray-400 hover:text-gray-600"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    placeholder="Digite o nome ou CPF do paciente..."
                                                    value={searchPaciente}
                                                    onChange={(e) => setSearchPaciente(e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                                {loadingPacientes && (
                                                    <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                                        <svg className="animate-spin h-4 w-4 text-gray-400" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                        </svg>
                                                    </div>
                                                )}
                                            </div>
                                            {showPacienteDropdown && pacienteResults.length > 0 && (
                                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-auto">
                                                    {pacienteResults.map((paciente) => (
                                                        <button key={paciente.id} type="button" onClick={() => selectPaciente(paciente)}
                                                            className="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                                            <span className="font-medium text-gray-900">{paciente.nome}</span>
                                                            <span className="text-sm text-gray-500 ml-2">{paciente.cpf}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </>
                                    )}
                                    {errors.paciente_id && <p className="mt-1 text-sm text-red-600">{errors.paciente_id}</p>}
                                </div>

                                {/* Data */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Data *</label>
                                    <input type="date" value={data.data_receita} onChange={(e) => setData('data_receita', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                                </div>

                                {/* Medico */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Médico *</label>
                                    <select value={data.medico_id} onChange={(e) => setData('medico_id', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        disabled={medicos?.length === 1}>
                                        <option value="">Selecione</option>
                                        {medicos?.map((medico) => (<option key={medico.id} value={medico.id}>{medico.nome}</option>))}
                                    </select>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Itens da Receita */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div className="flex justify-between items-center mb-2">
                            <h2 className="text-base font-semibold text-gray-900">Produtos</h2>
                        </div>

                        {isReadOnly && (
                            <div className="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-2">
                                <svg className="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span className="text-sm text-amber-800">
                                    Esta receita não pode ser editada pois o atendimento já está em produção ou finalizado.
                                </span>
                            </div>
                        )}

                        {errors.itens && (
                            <div className="mb-3 p-2 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                                {errors.itens}
                            </div>
                        )}

                        <div className="space-y-4">
                            {/* Produtos Recomendados */}
                            <div>
                                {data.itens.some(item => item.grupo === 'recomendado') && (
                                    <>
                                        <div className="flex items-center gap-2 mb-2 pb-1 border-b border-emerald-200">
                                            <div className="w-2.5 h-2.5 bg-emerald-500 rounded-full"></div>
                                            <span className="text-xs font-semibold text-emerald-700 uppercase tracking-wide">
                                                Recomendados para o Tratamento
                                            </span>
                                            <span className="text-xs text-gray-500">
                                                ({data.itens.filter(i => i.grupo === 'recomendado' && i.imprimir).length})
                                            </span>
                                        </div>
                                        <div className="space-y-1 mb-2">
                                            {data.itens.map((item, index) => item.grupo === 'recomendado' && (
                                                <div 
                                                    key={index} 
                                                    ref={index === data.itens.length - 1 ? lastItemRef : null}
                                                    className={`flex items-center gap-2 py-1.5 px-2 rounded transition-colors ${item.imprimir ? 'hover:bg-emerald-50/50' : 'bg-gray-50 opacity-50'}`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={item.imprimir}
                                                        onChange={(e) => updateItem(index, 'imprimir', e.target.checked)}
                                                        disabled={isReadOnly}
                                                        className="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 flex-shrink-0"
                                                    />
                                                    <div className="w-36 flex-shrink-0" title={item.local_uso || '-'}>
                                                        <span className="text-xs text-gray-600 bg-gray-100 px-2 py-0.5 rounded block truncate">
                                                            {formatLocalUso(item.local_uso)}
                                                        </span>
                                                    </div>
                                                    <select
                                                        value={item.produto_id}
                                                        onChange={(e) => updateItem(index, 'produto_id', e.target.value)}
                                                        disabled={isReadOnly}
                                                        className="flex-[2] min-w-0 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                                    >
                                                        <option value="">Produto...</option>
                                                        {produtos?.map((p) => (
                                                            <option key={p.id} value={p.id}>{p.codigo} - {p.nome}</option>
                                                        ))}
                                                    </select>
                                                    <input
                                                        type="text"
                                                        placeholder="Anotações..."
                                                        value={item.anotacoes || ''}
                                                        onChange={(e) => updateItem(index, 'anotacoes', e.target.value)}
                                                        disabled={isReadOnly}
                                                        className="flex-1 min-w-0 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-emerald-500 bg-gray-50"
                                                    />
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={item.quantidade}
                                                        onChange={(e) => updateItem(index, 'quantidade', parseInt(e.target.value) || 1)}
                                                        disabled={isReadOnly || !item.imprimir}
                                                        className={`w-14 flex-shrink-0 px-1 py-1 border border-gray-300 rounded text-sm text-center focus:ring-1 focus:ring-emerald-500 ${!item.imprimir ? 'bg-gray-100 text-gray-400' : ''}`}
                                                    />
                                                    {!isMedico && (
                                                        <span className={`w-20 flex-shrink-0 text-right text-sm font-medium ${item.imprimir ? 'text-gray-900' : 'text-gray-400'}`}>
                                                            {item.imprimir ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularSubtotalItem(item)) : '-'}
                                                        </span>
                                                    )}
                                                    {!isReadOnly && (
                                                        <button type="button" onClick={() => removeItem(index)} className="flex-shrink-0 p-1 text-red-500 hover:bg-red-50 rounded">
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                )}
                                
                                {/* Add Product Button - Always in Recomendados section */}
                                {!isReadOnly && (
                                    <button
                                        type="button"
                                        onClick={addItem}
                                        className="w-full px-3 py-2 border border-dashed border-emerald-300 text-emerald-600 rounded hover:bg-emerald-50 hover:border-emerald-400 transition-colors flex items-center justify-center gap-2 text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                        </svg>
                                        Adicionar Produto
                                    </button>
                                )}
                            </div>

                                {/* Produtos Opcionais */}
                                {data.itens.some(item => item.grupo === 'opcional') && (
                                    <div>
                                        <div className="flex items-center gap-2 mb-2 pb-1 border-b border-gray-300">
                                            <div className="w-2.5 h-2.5 bg-gray-400 rounded-full"></div>
                                            <span className="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                                Opcionais
                                            </span>
                                            <span className="text-xs text-gray-500">
                                                ({data.itens.filter(i => i.grupo === 'opcional' && i.imprimir).length})
                                            </span>
                                        </div>
                                        <div className="space-y-1">
                                            {data.itens.map((item, index) => item.grupo === 'opcional' && (
                                                <div 
                                                    key={index} 
                                                    ref={index === data.itens.length - 1 ? lastItemRef : null}
                                                    className={`flex items-center gap-2 py-1.5 px-2 rounded transition-colors ${item.imprimir ? 'hover:bg-gray-50' : 'bg-gray-50 opacity-50'}`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={item.imprimir}
                                                        onChange={(e) => updateItem(index, 'imprimir', e.target.checked)}
                                                        disabled={isReadOnly}
                                                        className="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 flex-shrink-0"
                                                    />
                                                    <div className="w-36 flex-shrink-0" title={item.local_uso || '-'}>
                                                        <span className="text-xs text-gray-600 bg-gray-100 px-2 py-0.5 rounded block truncate">
                                                            {formatLocalUso(item.local_uso)}
                                                        </span>
                                                    </div>
                                                    <select
                                                        value={item.produto_id}
                                                        onChange={(e) => updateItem(index, 'produto_id', e.target.value)}
                                                        disabled={isReadOnly}
                                                        className="flex-[2] min-w-0 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                                    >
                                                        <option value="">Produto...</option>
                                                        {produtos?.map((p) => (
                                                            <option key={p.id} value={p.id}>{p.codigo} - {p.nome}</option>
                                                        ))}
                                                    </select>
                                                    <input
                                                        type="text"
                                                        placeholder="Anotações..."
                                                        value={item.anotacoes || ''}
                                                        onChange={(e) => updateItem(index, 'anotacoes', e.target.value)}
                                                        disabled={isReadOnly}
                                                        className="flex-1 min-w-0 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-emerald-500 bg-gray-50"
                                                    />
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={item.quantidade}
                                                        onChange={(e) => updateItem(index, 'quantidade', parseInt(e.target.value) || 1)}
                                                        disabled={isReadOnly || !item.imprimir}
                                                        className={`w-14 flex-shrink-0 px-1 py-1 border border-gray-300 rounded text-sm text-center focus:ring-1 focus:ring-emerald-500 ${!item.imprimir ? 'bg-gray-100 text-gray-400' : ''}`}
                                                    />
                                                    {!isMedico && (
                                                        <span className={`w-20 flex-shrink-0 text-right text-sm font-medium ${item.imprimir ? 'text-gray-900' : 'text-gray-400'}`}>
                                                            {item.imprimir ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularSubtotalItem(item)) : '-'}
                                                        </span>
                                                    )}
                                                    {!isReadOnly && (
                                                        <button type="button" onClick={() => removeItem(index)} className="flex-shrink-0 p-1 text-red-500 hover:bg-red-50 rounded">
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                        </div>

                        {/* Totais - Hidden for medico users */}
                        {data.itens.length > 0 && !isMedico && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Desconto e Frete */}
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Desconto (%)
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={data.desconto_percentual}
                                                    onChange={(e) => setData('desconto_percentual', parseFloat(e.target.value) || 0)}
                                                    disabled={isReadOnly}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Valor Frete
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={data.valor_frete}
                                                    onChange={(e) => setData('valor_frete', parseFloat(e.target.value) || 0)}
                                                    disabled={isReadOnly}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                        </div>
                                        {data.desconto_percentual > 0 && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Motivo do Desconto
                                                </label>
                                                <input
                                                    type="text"
                                                    value={data.desconto_motivo}
                                                    onChange={(e) => setData('desconto_motivo', e.target.value)}
                                                    disabled={isReadOnly}
                                                    placeholder="Ex: Primeira compra, fidelidade..."
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    {/* Resumo */}
                                    <div className="bg-gray-50 rounded-lg p-4">
                                        <div className="space-y-2">
                                            <div className="flex justify-between text-sm">
                                                <span className="text-gray-600">Subtotal:</span>
                                                <span className="font-medium">
                                                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularSubtotal())}
                                                </span>
                                            </div>
                                            {data.desconto_percentual > 0 && (
                                                <div className="flex justify-between text-sm text-red-600">
                                                    <span>Desconto ({data.desconto_percentual}%):</span>
                                                    <span>- {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularDesconto())}</span>
                                                </div>
                                            )}
                                            {data.valor_frete > 0 && (
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600">Frete:</span>
                                                    <span>+ {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(data.valor_frete)}</span>
                                                </div>
                                            )}
                                            <div className="flex justify-between pt-2 border-t border-gray-300">
                                                <span className="font-semibold text-gray-900">Total:</span>
                                                <span className="text-xl font-bold text-emerald-600">
                                                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularTotal())}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Anotações - movido para antes das ações */}
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Anotações Internas</label>
                                <textarea
                                    value={data.anotacoes}
                                    onChange={(e) => setData('anotacoes', e.target.value)}
                                    rows={2}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Observações internas (não aparecem no PDF)..."
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Anotações para o Paciente</label>
                                <textarea
                                    value={data.anotacoes_paciente}
                                    onChange={(e) => setData('anotacoes_paciente', e.target.value)}
                                    rows={2}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Instruções que aparecerão no PDF..."
                                />
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end items-center bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                        <div className="flex items-center gap-4">
                            {/* Autosave indicator */}
                            {(isAutoSaving || lastSavedText) && (
                                <div className="text-xs text-gray-500 flex items-center gap-1">
                                    {isAutoSaving ? (
                                        <>
                                            <svg className="animate-spin h-3 w-3" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            <span>Salvando...</span>
                                        </>
                                    ) : lastSavedText ? (
                                        <>
                                            <svg className="h-3 w-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Salvo às {lastSavedText}</span>
                                        </>
                                    ) : null}
                                </div>
                            )}
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing || data.itens.length === 0}
                                    className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 flex items-center gap-2 text-sm"
                                >
                                    {processing ? (
                                        <>
                                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Salvando...
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            Salvar
                                        </>
                                    )}
                                </button>
                                {(isEditing || currentReceitaId) && data.status === 'rascunho' && (
                                    <button
                                        type="button"
                                        onClick={() => setShowFinalizarModal(true)}
                                        disabled={processing || data.itens.length === 0}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center gap-2 text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Finalizar
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </form>

                {/* Receitas Anteriores - Accordion */}
                {receitasAnteriores.length > 0 && (
                    <div className="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3">Outras Receitas do Paciente</h2>
                        <div className="space-y-2">
                            {receitasAnteriores.map((r) => (
                                <div key={r.id} className="border border-gray-200 rounded-lg overflow-hidden">
                                    {/* Accordion Header */}
                                    <div 
                                        className="flex items-center justify-between p-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors"
                                        onClick={() => toggleReceitaExpanded(r.id)}
                                    >
                                        <div className="flex items-center gap-3">
                                            <svg 
                                                className={`w-4 h-4 text-gray-500 transition-transform ${expandedReceitas[r.id] ? 'rotate-90' : ''}`} 
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            >
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                            </svg>
                                            <span className="text-sm font-medium text-gray-900">
                                                #{r.numero || r.id.toString().padStart(5, '0')}
                                            </span>
                                            <span className="text-xs text-gray-500">
                                                {new Date(r.data_receita).toLocaleDateString('pt-BR')}
                                            </span>
                                            {r.medico && (
                                                <span className="text-xs text-gray-500">• {r.medico.nome}</span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {!isMedico && r.valor_total > 0 && (
                                                <span className="text-xs font-medium text-gray-700">
                                                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(r.valor_total)}
                                                </span>
                                            )}
                                            <span className={`px-2 py-0.5 text-xs rounded ${
                                                r.status === 'finalizada' ? 'bg-green-100 text-green-700' :
                                                r.status === 'cancelada' ? 'bg-red-100 text-red-700' :
                                                'bg-gray-100 text-gray-600'
                                            }`}>
                                                {r.status === 'finalizada' ? 'Finalizada' : 
                                                 r.status === 'cancelada' ? 'Cancelada' : 'Rascunho'}
                                            </span>
                                            <Link
                                                href={`/receitas/${r.id}`}
                                                onClick={(e) => e.stopPropagation()}
                                                className="p-1 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded"
                                                title="Ver receita completa"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>
                                        </div>
                                    </div>
                                    
                                    {/* Accordion Content */}
                                    {expandedReceitas[r.id] && (
                                        <div className="p-3 border-t border-gray-200 bg-white">
                                            {/* Anotações */}
                                            {(r.anotacoes || r.anotacoes_paciente) && (
                                                <div className="mb-3 text-xs">
                                                    {r.anotacoes && (
                                                        <div className="mb-1">
                                                            <span className="font-medium text-gray-700">Anotações Internas:</span>
                                                            <span className="text-gray-600 ml-1">{r.anotacoes}</span>
                                                        </div>
                                                    )}
                                                    {r.anotacoes_paciente && (
                                                        <div>
                                                            <span className="font-medium text-gray-700">Anotações Paciente:</span>
                                                            <span className="text-gray-600 ml-1">{r.anotacoes_paciente}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                            
                                            {/* Produtos */}
                                            {r.itens && r.itens.length > 0 ? (
                                                <div className="space-y-1">
                                                    <div className="text-xs font-medium text-gray-700 mb-2">Produtos ({r.itens.length})</div>
                                                    <div className="overflow-x-auto">
                                                        <table className="w-full text-xs">
                                                            <thead className="bg-gray-50">
                                                                <tr>
                                                                    <th className="text-left px-2 py-1 font-medium text-gray-600">Tipo</th>
                                                                    <th className="text-left px-2 py-1 font-medium text-gray-600">Produto</th>
                                                                    <th className="text-center px-2 py-1 font-medium text-gray-600">Qtd</th>
                                                                    {!isMedico && <th className="text-right px-2 py-1 font-medium text-gray-600">Valor</th>}
                                                                    <th className="text-left px-2 py-1 font-medium text-gray-600">Última Aquisição</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {r.itens.map((item, idx) => (
                                                                    <tr key={idx} className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                                                                        <td className="px-2 py-1 text-gray-600">
                                                                            {formatLocalUso(item.local_uso || item.produto?.local_uso)}
                                                                        </td>
                                                                        <td className="px-2 py-1 text-gray-900">
                                                                            {item.produto?.nome || 'Produto não encontrado'}
                                                                        </td>
                                                                        <td className="px-2 py-1 text-center text-gray-600">{item.quantidade}</td>
                                                                        {!isMedico && (
                                                                            <td className="px-2 py-1 text-right text-gray-600">
                                                                                {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.valor_unitario * item.quantidade)}
                                                                            </td>
                                                                        )}
                                                                        <td className="px-2 py-1 text-gray-500">
                                                                            {item.aquisicoes && item.aquisicoes.length > 0 
                                                                                ? new Date(item.aquisicoes[item.aquisicoes.length - 1].data_aquisicao).toLocaleDateString('pt-BR')
                                                                                : '-'
                                                                            }
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            ) : (
                                                <p className="text-xs text-gray-500">Nenhum produto nesta receita.</p>
                                            )}
                                            
                                            {/* Data de criação */}
                                            <div className="mt-3 pt-2 border-t border-gray-100 text-xs text-gray-500">
                                                Criado em: {new Date(r.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Modal de Confirmação - Finalizar */}
                {showFinalizarModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div className="flex min-h-full items-center justify-center p-4">
                            {/* Backdrop */}
                            <div 
                                className="fixed inset-0 bg-black/50 transition-opacity"
                                onClick={() => setShowFinalizarModal(false)}
                            />
                            
                            {/* Modal */}
                            <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 transform transition-all">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900">Finalizar Receita</h3>
                                </div>
                                
                                <p className="text-gray-600 mb-6">
                                    Deseja finalizar esta receita? Após finalizada, ela será enviada para o Call Center e não poderá mais ser editada.
                                </p>
                                
                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setShowFinalizarModal(false)}
                                        className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={finalizarReceita}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        Confirmar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
