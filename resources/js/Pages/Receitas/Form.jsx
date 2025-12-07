import { useForm, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function ReceitaForm({ receita, pacientes, produtos, medicos, tabelasPreco }) {
    const isEditing = !!receita;
    
    const { data, setData, post, put, processing, errors } = useForm({
        paciente_id: receita?.paciente_id || '',
        medico_id: receita?.medico_id || '',
        tabela_preco_id: receita?.tabela_preco_id || '',
        data_receita: receita?.data_receita || new Date().toISOString().split('T')[0],
        observacoes: receita?.observacoes || '',
        status: receita?.status || 'rascunho',
        itens: receita?.itens || [],
    });

    const [searchPaciente, setSearchPaciente] = useState('');
    const [filteredPacientes, setFilteredPacientes] = useState([]);
    const [showPacienteDropdown, setShowPacienteDropdown] = useState(false);
    const [selectedPaciente, setSelectedPaciente] = useState(receita?.paciente || null);

    useEffect(() => {
        if (searchPaciente.length >= 2) {
            const filtered = pacientes.filter(p => 
                p.nome.toLowerCase().includes(searchPaciente.toLowerCase()) ||
                p.cpf.includes(searchPaciente)
            ).slice(0, 10);
            setFilteredPacientes(filtered);
            setShowPacienteDropdown(true);
        } else {
            setShowPacienteDropdown(false);
        }
    }, [searchPaciente, pacientes]);

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
                quantidade: 1,
                preco_unitario: 0,
                desconto: 0,
                comentario: '',
            },
        ]);
    };

    const removeItem = (index) => {
        const newItens = [...data.itens];
        newItens.splice(index, 1);
        setData('itens', newItens);
    };

    const updateItem = (index, field, value) => {
        const newItens = [...data.itens];
        newItens[index] = { ...newItens[index], [field]: value };

        // Se mudou o produto, atualiza o preço
        if (field === 'produto_id') {
            const produto = produtos.find(p => p.id === parseInt(value));
            if (produto) {
                newItens[index].preco_unitario = produto.preco_venda || 0;
            }
        }

        setData('itens', newItens);
    };

    const calcularSubtotal = (item) => {
        const subtotal = item.quantidade * item.preco_unitario;
        const desconto = item.desconto || 0;
        return subtotal - (subtotal * desconto / 100);
    };

    const calcularTotal = () => {
        return data.itens.reduce((total, item) => total + calcularSubtotal(item), 0);
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
        if (confirm('Deseja finalizar esta receita? Após finalizada, não poderá mais ser editada.')) {
            router.put(`/receitas/${receita.id}`, { ...data, status: 'finalizada' });
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-6xl mx-auto">
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
                        {isEditing ? `Editar Receita #${receita.id.toString().padStart(5, '0')}` : 'Nova Receita'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Dados Básicos */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados da Receita</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Paciente */}
                            <div className="relative">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Paciente *
                                </label>
                                {selectedPaciente ? (
                                    <div className="flex items-center justify-between bg-gray-50 border border-gray-300 rounded-lg px-4 py-2">
                                        <div>
                                            <div className="font-medium text-gray-900">{selectedPaciente.nome}</div>
                                            <div className="text-sm text-gray-500">{selectedPaciente.cpf}</div>
                                        </div>
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
                                    </div>
                                ) : (
                                    <>
                                        <input
                                            type="text"
                                            placeholder="Digite o nome ou CPF do paciente..."
                                            value={searchPaciente}
                                            onChange={(e) => setSearchPaciente(e.target.value)}
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        />
                                        {showPacienteDropdown && filteredPacientes.length > 0 && (
                                            <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                                                {filteredPacientes.map((paciente) => (
                                                    <button
                                                        key={paciente.id}
                                                        type="button"
                                                        onClick={() => selectPaciente(paciente)}
                                                        className="w-full text-left px-4 py-2 hover:bg-gray-50"
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

                            {/* Médico */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Médico *
                                </label>
                                <select
                                    value={data.medico_id}
                                    onChange={(e) => setData('medico_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="">Selecione o médico</option>
                                    {medicos?.map((medico) => (
                                        <option key={medico.id} value={medico.id}>
                                            {medico.nome} - CRM {medico.crm}
                                        </option>
                                    ))}
                                </select>
                                {errors.medico_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.medico_id}</p>
                                )}
                            </div>

                            {/* Tabela de Preço */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Tabela de Preço
                                </label>
                                <select
                                    value={data.tabela_preco_id}
                                    onChange={(e) => setData('tabela_preco_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="">Tabela Padrão</option>
                                    {tabelasPreco?.map((tabela) => (
                                        <option key={tabela.id} value={tabela.id}>
                                            {tabela.nome}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Observações */}
                        <div className="mt-4">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Observações
                            </label>
                            <textarea
                                value={data.observacoes}
                                onChange={(e) => setData('observacoes', e.target.value)}
                                rows={3}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                placeholder="Observações gerais da receita..."
                            />
                        </div>
                    </div>

                    {/* Itens da Receita */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-semibold text-gray-900">Itens da Receita</h2>
                            <button
                                type="button"
                                onClick={addItem}
                                className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Adicionar Item
                            </button>
                        </div>

                        {data.itens.length > 0 ? (
                            <div className="space-y-4">
                                {data.itens.map((item, index) => (
                                    <div key={index} className="border border-gray-200 rounded-lg p-4">
                                        <div className="grid grid-cols-1 md:grid-cols-6 gap-4">
                                            {/* Produto */}
                                            <div className="md:col-span-2">
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Produto
                                                </label>
                                                <select
                                                    value={item.produto_id}
                                                    onChange={(e) => updateItem(index, 'produto_id', e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                >
                                                    <option value="">Selecione...</option>
                                                    {produtos?.map((produto) => (
                                                        <option key={produto.id} value={produto.id}>
                                                            {produto.nome}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            {/* Quantidade */}
                                            <div>
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Qtd
                                                </label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={item.quantidade}
                                                    onChange={(e) => updateItem(index, 'quantidade', parseInt(e.target.value) || 1)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>

                                            {/* Preço Unitário */}
                                            <div>
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Preço Unit.
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={item.preco_unitario}
                                                    onChange={(e) => updateItem(index, 'preco_unitario', parseFloat(e.target.value) || 0)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>

                                            {/* Desconto */}
                                            <div>
                                                <label className="block text-xs font-medium text-gray-500 mb-1">
                                                    Desconto %
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={item.desconto}
                                                    onChange={(e) => updateItem(index, 'desconto', parseFloat(e.target.value) || 0)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>

                                            {/* Subtotal + Delete */}
                                            <div className="flex items-end justify-between">
                                                <div>
                                                    <label className="block text-xs font-medium text-gray-500 mb-1">
                                                        Subtotal
                                                    </label>
                                                    <div className="text-lg font-semibold text-gray-900">
                                                        {new Intl.NumberFormat('pt-BR', {
                                                            style: 'currency',
                                                            currency: 'BRL',
                                                        }).format(calcularSubtotal(item))}
                                                    </div>
                                                </div>
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

                                        {/* Comentário do item */}
                                        <div className="mt-3">
                                            <input
                                                type="text"
                                                placeholder="Comentário do item (opcional)..."
                                                value={item.comentario || ''}
                                                onChange={(e) => updateItem(index, 'comentario', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            />
                                        </div>
                                    </div>
                                ))}

                                {/* Total */}
                                <div className="flex justify-end pt-4 border-t border-gray-200">
                                    <div className="text-right">
                                        <span className="text-sm text-gray-500">Total da Receita</span>
                                        <div className="text-2xl font-bold text-emerald-600">
                                            {new Intl.NumberFormat('pt-BR', {
                                                style: 'currency',
                                                currency: 'BRL',
                                            }).format(calcularTotal())}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <p>Nenhum item adicionado</p>
                                <p className="text-sm">Clique em "Adicionar Item" para começar</p>
                            </div>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="flex justify-between">
                        <Link
                            href="/receitas"
                            className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                        <div className="flex gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Salvando...' : 'Salvar Receita'}
                            </button>
                            {isEditing && data.status === 'rascunho' && (
                                <button
                                    type="button"
                                    onClick={finalizarReceita}
                                    className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                    Finalizar Receita
                                </button>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}

