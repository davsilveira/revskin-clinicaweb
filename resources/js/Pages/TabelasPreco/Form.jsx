import { useForm, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function TabelaPrecoForm({ tabela, clinicas, produtos }) {
    const isEditing = !!tabela;

    const { data, setData, post, put, processing, errors } = useForm({
        nome: tabela?.nome || '',
        descricao: tabela?.descricao || '',
        clinica_id: tabela?.clinica_id || '',
        ativa: tabela?.ativa ?? true,
        itens: tabela?.itens || [],
    });

    const [searchProduto, setSearchProduto] = useState('');

    const addItem = (produto) => {
        if (data.itens.find(i => i.produto_id === produto.id)) {
            return; // Already added
        }
        setData('itens', [
            ...data.itens,
            {
                produto_id: produto.id,
                produto: produto,
                preco_personalizado: produto.preco_venda,
                desconto_percentual: 0,
            },
        ]);
        setSearchProduto('');
    };

    const removeItem = (produtoId) => {
        setData('itens', data.itens.filter(i => i.produto_id !== produtoId));
    };

    const updateItem = (produtoId, field, value) => {
        setData('itens', data.itens.map(item => 
            item.produto_id === produtoId 
                ? { ...item, [field]: value }
                : item
        ));
    };

    const filteredProdutos = searchProduto.length >= 2
        ? produtos?.filter(p => 
            p.nome.toLowerCase().includes(searchProduto.toLowerCase()) &&
            !data.itens.find(i => i.produto_id === p.id)
          ).slice(0, 10)
        : [];

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/tabelas-preco/${tabela.id}`);
        } else {
            post('/tabelas-preco');
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-6xl mx-auto">
                <div className="mb-6">
                    <Link
                        href="/tabelas-preco"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Tabelas de Preço
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">
                        {isEditing ? 'Editar Tabela de Preço' : 'Nova Tabela de Preço'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Dados Básicos */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Informações da Tabela</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Nome da Tabela *
                                </label>
                                <input
                                    type="text"
                                    value={data.nome}
                                    onChange={(e) => setData('nome', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Ex: Tabela Especial Clínica X"
                                />
                                {errors.nome && <p className="mt-1 text-sm text-red-600">{errors.nome}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Clínica (opcional)
                                </label>
                                <select
                                    value={data.clinica_id}
                                    onChange={(e) => setData('clinica_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="">Sem vínculo com clínica</option>
                                    {clinicas?.map((clinica) => (
                                        <option key={clinica.id} value={clinica.id}>
                                            {clinica.nome}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Descrição
                                </label>
                                <textarea
                                    value={data.descricao}
                                    onChange={(e) => setData('descricao', e.target.value)}
                                    rows={2}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.ativa}
                                        onChange={(e) => setData('ativa', e.target.checked)}
                                        className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                    />
                                    <span className="text-sm font-medium text-gray-700">Tabela ativa</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Itens da Tabela */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Produtos com Preço Diferenciado</h2>
                        
                        {/* Buscar Produto */}
                        <div className="relative mb-6">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Adicionar Produto
                            </label>
                            <input
                                type="text"
                                value={searchProduto}
                                onChange={(e) => setSearchProduto(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                placeholder="Digite para buscar produtos..."
                            />
                            {filteredProdutos.length > 0 && (
                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                                    {filteredProdutos.map((produto) => (
                                        <button
                                            key={produto.id}
                                            type="button"
                                            onClick={() => addItem(produto)}
                                            className="w-full text-left px-4 py-2 hover:bg-gray-50"
                                        >
                                            <div className="font-medium text-gray-900">{produto.nome}</div>
                                            <div className="text-sm text-gray-500">
                                                {new Intl.NumberFormat('pt-BR', {
                                                    style: 'currency',
                                                    currency: 'BRL',
                                                }).format(produto.preco_venda)}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Lista de Itens */}
                        {data.itens.length > 0 ? (
                            <div className="space-y-3">
                                {data.itens.map((item) => (
                                    <div key={item.produto_id} className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                                        <div className="flex-1">
                                            <div className="font-medium text-gray-900">
                                                {item.produto?.nome}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                Preço original: {new Intl.NumberFormat('pt-BR', {
                                                    style: 'currency',
                                                    currency: 'BRL',
                                                }).format(item.produto?.preco_venda || 0)}
                                            </div>
                                        </div>
                                        
                                        <div className="w-40">
                                            <label className="block text-xs text-gray-500 mb-1">Preço Personalizado</label>
                                            <div className="relative">
                                                <span className="absolute left-3 top-2 text-gray-500 text-sm">R$</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={item.preco_personalizado}
                                                    onChange={(e) => updateItem(item.produto_id, 'preco_personalizado', parseFloat(e.target.value) || 0)}
                                                    className="w-full pl-10 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                            </div>
                                        </div>

                                        <div className="w-32">
                                            <label className="block text-xs text-gray-500 mb-1">Desconto %</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                value={item.desconto_percentual}
                                                onChange={(e) => updateItem(item.produto_id, 'desconto_percentual', parseFloat(e.target.value) || 0)}
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            />
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => removeItem(item.produto_id)}
                                            className="p-2 text-red-600 hover:bg-red-50 rounded-lg"
                                        >
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <p>Nenhum produto adicionado</p>
                                <p className="text-sm">Busque e adicione produtos para personalizar os preços</p>
                            </div>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-4">
                        <Link
                            href="/tabelas-preco"
                            className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                        >
                            {processing ? 'Salvando...' : (isEditing ? 'Atualizar' : 'Cadastrar')}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}

