import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';

export default function ProdutosIndex({ produtos, categorias = [], filters }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingProduto, setEditingProduto] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');

    const { data, setData, post, put, processing, errors, reset } = useForm({
        codigo: '',
        nome: '',
        descricao: '',
        categoria: '',
        unidade: 'UN',
        preco_custo: '',
        preco_venda: '',
        estoque_minimo: '',
        local_uso: '',
        modo_uso: '',
        tiny_id: '',
        ativo: true,
    });

    const openCreateDrawer = () => {
        reset();
        setEditingProduto(null);
        setShowDeleteConfirm(false);
        setDrawerOpen(true);
    };

    const openEditDrawer = (produto) => {
        setEditingProduto(produto);
        setShowDeleteConfirm(false);
        setData({
            codigo: produto.codigo || '',
            nome: produto.nome || '',
            descricao: produto.descricao || '',
            categoria: produto.categoria || '',
            unidade: produto.unidade || 'UN',
            preco_custo: produto.preco_custo || '',
            preco_venda: produto.preco || produto.preco_venda || '',
            estoque_minimo: produto.estoque_minimo || '',
            local_uso: produto.local_uso || '',
            modo_uso: produto.anotacoes || produto.modo_uso || '',
            tiny_id: produto.tiny_id || '',
            ativo: produto.ativo ?? true,
        });
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingProduto(null);
        setShowDeleteConfirm(false);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingProduto) {
            put(`/produtos/${editingProduto.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Produto atualizado!', type: 'success' }); },
            });
        } else {
            post('/produtos', {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Produto cadastrado!', type: 'success' }); },
            });
        }
    };

    const handleDelete = () => {
        if (editingProduto) {
            router.delete(`/produtos/${editingProduto.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Produto excluido!', type: 'success' }); },
            });
        }
    };

    const handleToggleStatus = (produto) => {
        router.put(`/produtos/${produto.id}`, {
            ...produto,
            ativo: !produto.ativo,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setToast({ 
                    message: produto.ativo ? 'Produto desativado!' : 'Produto ativado!', 
                    type: 'success' 
                });
            },
        });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/produtos', { search }, { preserveState: true });
    };

    const produtosList = produtos?.data || produtos || [];
    const categoriasUnicas = [...new Set([...categorias, 'Cremes', 'Seruns', 'Limpeza', 'Protetor Solar', 'Tratamento', 'Outros'])];

    return (
        <DashboardLayout>
            <Head title="Produtos" />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Produtos</h1>
                        <p className="text-gray-600 mt-1">Gerencie os produtos cadastrados</p>
                    </div>
                    <button onClick={openCreateDrawer} className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 flex items-center gap-2">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Novo Produto
                    </button>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex gap-4">
                        <input type="text" placeholder="Buscar por codigo ou nome..." value={search} onChange={(e) => setSearch(e.target.value)} className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500" />
                        <button type="submit" className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Buscar</button>
                    </form>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Codigo</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preco</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {produtosList.length > 0 ? produtosList.map((produto) => (
                                <tr key={produto.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 text-sm font-mono text-gray-900">{produto.codigo}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{produto.nome}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{produto.categoria || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500">
                                        {(produto.preco || produto.preco_venda) ? `R$ ${parseFloat(produto.preco || produto.preco_venda).toFixed(2)}` : '-'}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 text-xs rounded-full ${produto.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {produto.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button
                                            onClick={() => openEditDrawer(produto)}
                                            className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                            title="Editar"
                                        >
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            )) : <tr><td colSpan="6" className="px-6 py-12 text-center text-gray-500">Nenhum produto encontrado</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>

            <Drawer isOpen={drawerOpen} onClose={closeDrawer} title={editingProduto ? 'Editar Produto' : 'Novo Produto'} size="lg">
                <form onSubmit={handleSubmit} className="flex flex-col h-full">
                    <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                        <div className="grid grid-cols-3 gap-4">
                            <Input label="Codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} error={errors.codigo} required />
                            <div className="col-span-2">
                                <Input label="Nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} error={errors.nome} required />
                            </div>
                        </div>
                        <Input label="Descricao" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)} multiline rows={2} />
                        <div className="grid grid-cols-2 gap-4">
                            <Select label="Categoria" value={data.categoria} onChange={(e) => setData('categoria', e.target.value)} options={[{ value: '', label: 'Selecione' }, ...categoriasUnicas.map(c => ({ value: c, label: c }))]} />
                            <Select label="Unidade" value={data.unidade} onChange={(e) => setData('unidade', e.target.value)} options={[{ value: 'UN', label: 'Unidade' }, { value: 'KG', label: 'Quilograma' }, { value: 'L', label: 'Litro' }, { value: 'ML', label: 'Mililitro' }, { value: 'G', label: 'Grama' }]} />
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <Input label="Preco Custo" type="number" step="0.01" value={data.preco_custo} onChange={(e) => setData('preco_custo', e.target.value)} />
                            <Input label="Preco Venda" type="number" step="0.01" value={data.preco_venda} onChange={(e) => setData('preco_venda', e.target.value)} />
                            <Input label="Estoque Minimo" type="number" value={data.estoque_minimo} onChange={(e) => setData('estoque_minimo', e.target.value)} />
                        </div>
                        <div className="border-t pt-6">
                            <h3 className="text-sm font-medium text-gray-900 mb-4">Informacoes para Receita</h3>
                            <Input label="Local de Uso" value={data.local_uso} onChange={(e) => setData('local_uso', e.target.value)} placeholder="Ex: Rosto, Corpo, Maos" />
                            <div className="mt-4">
                                <Input label="Modo de Uso" value={data.modo_uso} onChange={(e) => setData('modo_uso', e.target.value)} multiline rows={2} placeholder="Ex: Aplicar a noite apos limpeza" />
                            </div>
                        </div>
                        <div className="border-t pt-6">
                            <Input label="ID Tiny ERP" value={data.tiny_id} onChange={(e) => setData('tiny_id', e.target.value)} placeholder="ID do produto no Tiny" />
                        </div>
                        {editingProduto && <Select label="Status" value={data.ativo ? '1' : '0'} onChange={(e) => setData('ativo', e.target.value === '1')} options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />}
                    </div>
                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div>
                                {editingProduto && !showDeleteConfirm && (
                                    <button type="button" onClick={() => setShowDeleteConfirm(true)} className="flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Excluir
                                    </button>
                                )}
                                {showDeleteConfirm && <div className="flex items-center gap-2"><span className="text-sm">Confirmar?</span><button type="button" onClick={handleDelete} className="px-3 py-1 bg-red-600 text-white rounded">Sim</button><button type="button" onClick={() => setShowDeleteConfirm(false)} className="px-3 py-1 bg-gray-200 rounded">Nao</button></div>}
                            </div>
                            <div className="flex gap-3">
                                <button type="button" onClick={closeDrawer} className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                                <button type="submit" disabled={processing} className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">{processing ? 'Salvando...' : 'Salvar'}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </Drawer>
            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
