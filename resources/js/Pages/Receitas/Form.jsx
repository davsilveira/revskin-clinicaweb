import { useForm, Link, router } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import debounce from 'lodash/debounce';
import useAutoSave from '@/hooks/useAutoSave';

export default function ReceitaForm({ receita, paciente: initialPaciente, produtos, medicos, defaultMedicoId }) {
    const isEditing = !!receita;
    const [currentReceitaId, setCurrentReceitaId] = useState(receita?.id || null);
    const isFirstRender = useRef(true);
    
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
    }, [data, canAutoSave]);

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
        
        if (confirm('Deseja finalizar esta receita? Após finalizada, será enviada para o Call Center.')) {
            router.put(`/receitas/${receitaId}`, {
                ...data,
                status: 'finalizada',
            });
        }
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

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Dados Basicos */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados da Receita</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Paciente */}
                            <div className="relative">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Paciente *
                                </label>
                                {selectedPaciente ? (
                                    <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
                                        <div>
                                            <div className="font-medium text-gray-900">{selectedPaciente.nome}</div>
                                            <div className="text-sm text-gray-500">{selectedPaciente.cpf}</div>
                                        </div>
                                        {!isEditing && (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setSelectedPaciente(null);
                                                    setData('paciente_id', '');
                                                }}
                                                className="text-gray-400 hover:text-gray-600"
                                            >
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        )}
                                    </div>
                                ) : (
                                    <>
                                        <div className="relative">
                                            <input
                                                type="text"
                                                placeholder="Digite o nome ou CPF do paciente..."
                                                value={searchPaciente}
                                                onChange={(e) => setSearchPaciente(e.target.value)}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
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
                                                        className="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                                    >
                                                        <div className="font-medium text-gray-900">{paciente.nome}</div>
                                                        <div className="text-sm text-gray-500">{paciente.cpf}</div>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </>
                                )}
                                {errors.paciente_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.paciente_id}</p>
                                )}
                            </div>

                            {/* Data */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Data da Receita *
                                </label>
                                <input
                                    type="date"
                                    value={data.data_receita}
                                    onChange={(e) => setData('data_receita', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {errors.data_receita && (
                                    <p className="mt-1 text-sm text-red-600">{errors.data_receita}</p>
                                )}
                            </div>

                            {/* Medico */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Medico *
                                </label>
                                <select
                                    value={data.medico_id}
                                    onChange={(e) => setData('medico_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    disabled={medicos?.length === 1}
                                >
                                    <option value="">Selecione o medico</option>
                                    {medicos?.map((medico) => (
                                        <option key={medico.id} value={medico.id}>
                                            {medico.nome}
                                        </option>
                                    ))}
                                </select>
                                {errors.medico_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.medico_id}</p>
                                )}
                            </div>

                            {/* Status (only show for admin on edit) */}
                            {isEditing && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Status
                                    </label>
                                    <div className={`px-4 py-2 rounded-lg font-medium ${
                                        data.status === 'finalizada' ? 'bg-green-100 text-green-800' :
                                        data.status === 'cancelada' ? 'bg-red-100 text-red-800' :
                                        'bg-gray-100 text-gray-800'
                                    }`}>
                                        {data.status === 'finalizada' ? 'Finalizada' :
                                         data.status === 'cancelada' ? 'Cancelada' : 'Rascunho'}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Anotacoes */}
                        <div className="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Anotacoes Internas
                                </label>
                                <textarea
                                    value={data.anotacoes}
                                    onChange={(e) => setData('anotacoes', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Observacoes internas (nao aparecem no PDF)..."
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Anotacoes para o Paciente
                                </label>
                                <textarea
                                    value={data.anotacoes_paciente}
                                    onChange={(e) => setData('anotacoes_paciente', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Instrucoes que aparecerao no PDF..."
                                />
                            </div>
                        </div>
                    </div>

                    {/* Itens da Receita */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-semibold text-gray-900">Produtos</h2>
                            <button
                                type="button"
                                onClick={addItem}
                                className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Adicionar Produto
                            </button>
                        </div>

                        {errors.itens && (
                            <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                                {errors.itens}
                            </div>
                        )}

                        {data.itens.length > 0 ? (
                            <div className="space-y-4">
                                {data.itens.map((item, index) => (
                                    <div 
                                        key={index} 
                                        ref={index === data.itens.length - 1 ? lastItemRef : null}
                                        className="border border-gray-200 rounded-lg p-4 hover:border-emerald-300 transition-colors"
                                    >
                                        <div className="grid grid-cols-12 gap-4">
                                            {/* Produto */}
                                            <div className="col-span-12 md:col-span-4">
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Produto *
                                                </label>
                                                <select
                                                    value={item.produto_id}
                                                    onChange={(e) => updateItem(index, 'produto_id', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                >
                                                    <option value="">Selecione...</option>
                                                    {produtos?.map((produto) => (
                                                        <option key={produto.id} value={produto.id}>
                                                            {produto.codigo} - {produto.nome}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            {/* Local de Uso */}
                                            <div className="col-span-6 md:col-span-2">
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Local de Uso
                                                </label>
                                                <input
                                                    type="text"
                                                    value={item.local_uso}
                                                    onChange={(e) => updateItem(index, 'local_uso', e.target.value)}
                                                    placeholder="Ex: Rosto"
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>

                                            {/* Quantidade */}
                                            <div className="col-span-3 md:col-span-1">
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Qtd *
                                                </label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={item.quantidade}
                                                    onChange={(e) => updateItem(index, 'quantidade', parseInt(e.target.value) || 1)}
                                                    disabled={!item.imprimir}
                                                    className={`w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${!item.imprimir ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : ''}`}
                                                />
                                            </div>

                                            {/* Valor Unitario */}
                                            <div className="col-span-3 md:col-span-2">
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Valor Unit.
                                                </label>
                                                <div className={`w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 ${!item.imprimir ? 'text-gray-400' : 'text-gray-700'}`}>
                                                    {new Intl.NumberFormat('pt-BR', {
                                                        style: 'currency',
                                                        currency: 'BRL',
                                                    }).format(item.valor_unitario)}
                                                </div>
                                            </div>

                                            {/* Subtotal + Imprimir + Delete */}
                                            <div className="col-span-12 md:col-span-3 flex items-end justify-between gap-2">
                                                <div>
                                                    <label className="block text-xs font-medium text-gray-500 mb-1">
                                                        Subtotal
                                                    </label>
                                                    <div className={`text-lg font-semibold ${item.imprimir ? 'text-gray-900' : 'text-gray-400'}`}>
                                                        {item.imprimir 
                                                            ? new Intl.NumberFormat('pt-BR', {
                                                                style: 'currency',
                                                                currency: 'BRL',
                                                            }).format(calcularSubtotalItem(item))
                                                            : '-'
                                                        }
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <label className="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={item.imprimir}
                                                            onChange={(e) => updateItem(index, 'imprimir', e.target.checked)}
                                                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                        />
                                                        Incluir
                                                    </label>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(index)}
                                                        className="p-2 text-red-600 hover:bg-red-50 rounded-lg"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Anotacoes do item */}
                                        <div className="mt-3">
                                            <input
                                                type="text"
                                                placeholder="Anotacoes do produto (modo de uso, observacoes)..."
                                                value={item.anotacoes || ''}
                                                onChange={(e) => updateItem(index, 'anotacoes', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-gray-50"
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-12 text-gray-500 border-2 border-dashed border-gray-200 rounded-lg">
                                <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <p className="font-medium">Nenhum produto adicionado</p>
                                <p className="text-sm">Clique em "Adicionar Produto" para comecar</p>
                            </div>
                        )}

                        {/* Totais */}
                        {data.itens.length > 0 && (
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
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500"
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
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500"
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
                                                    placeholder="Ex: Primeira compra, fidelidade..."
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500"
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

                    {/* Actions */}
                    <div className="flex justify-between items-center bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <Link
                            href="/receitas"
                            className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
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
                            <div className="flex gap-3">
                                <button
                                    type="submit"
                                    disabled={processing || data.itens.length === 0}
                                    className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 flex items-center gap-2"
                                >
                                    {processing ? (
                                        <>
                                            <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Salvando...
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            Salvar Rascunho
                                        </>
                                    )}
                                </button>
                                {(isEditing || currentReceitaId) && data.status === 'rascunho' && (
                                    <button
                                        type="button"
                                        onClick={finalizarReceita}
                                        disabled={processing || data.itens.length === 0}
                                        className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center gap-2"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Finalizar e Enviar
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}
