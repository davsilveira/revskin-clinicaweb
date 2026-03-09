import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';
import Pagination from '@/Components/Pagination';

export default function ProdutosIndex({ produtos, filters, lastSync }) {
    const { auth } = usePage().props;
    const isAdmin = auth.user.role === 'admin';
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingProduto, setEditingProduto] = useState(null);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');
    const [syncing, setSyncing] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        nome: '',
        descricao: '',
        anotacoes: '',
        modo_uso: '',
        ativo: true,
    });

    const openEditDrawer = (produto) => {
        setEditingProduto(produto);
        setData({
            nome: produto.nome || '',
            descricao: produto.descricao || '',
            anotacoes: produto.anotacoes || '',
            modo_uso: produto.modo_uso || '',
            ativo: produto.ativo ?? true,
        });
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingProduto(null);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingProduto) {
            put(`/produtos/${editingProduto.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Produto atualizado!', type: 'success' }); },
            });
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/produtos', { search }, { preserveState: true });
    };

    const handleSync = async () => {
        setSyncing(true);
        try {
            const response = await window.axios.post('/integracoes/tiny/sync-produtos');
            setToast({ message: response.data?.message || 'Sincronização concluída!', type: 'success' });
            if (response.data?.success) {
                router.reload();
            }
        } catch (error) {
            setToast({ message: error.response?.data?.message || 'Erro ao sincronizar.', type: 'error' });
        } finally {
            setSyncing(false);
        }
    };

    const produtosList = produtos?.data || produtos || [];

    return (
        <DashboardLayout>
            <Head title="Produtos" />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Produtos</h1>
                        <p className="text-gray-600 mt-1">Produtos sincronizados do Tiny ERP</p>
                        {lastSync && (
                            <p className="text-xs text-gray-400 mt-1">
                                Ultima sincronizacao: {new Date(lastSync).toLocaleString('pt-BR')}
                            </p>
                        )}
                    </div>
                    {isAdmin && (
                        <button
                            onClick={handleSync}
                            disabled={syncing}
                            className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-60 flex items-center gap-2"
                        >
                            <svg className={`w-5 h-5 ${syncing ? 'animate-spin' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {syncing ? 'Sincronizando...' : 'Sincronizar Produtos'}
                        </button>
                    )}
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
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preco</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {produtosList.length > 0 ? produtosList.map((produto) => (
                                <tr key={produto.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 text-sm font-mono text-gray-900">{produto.codigo}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                        {produto.nome}
                                        {!produto.descricao && !produto.modo_uso && (
                                            <span className="ml-2 px-1.5 py-0.5 text-[10px] font-medium rounded bg-amber-100 text-amber-700">Pendente</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{produto.unidade || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500">
                                        {produto.preco ? `R$ ${parseFloat(produto.preco).toFixed(2)}` : '-'}
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
                    {produtos?.links && (
                        <Pagination links={produtos.links} preserveScroll />
                    )}
                </div>
            </div>

            <Drawer isOpen={drawerOpen} onClose={closeDrawer} title="Editar Produto" size="lg">
                <form onSubmit={handleSubmit} className="flex flex-col h-full">
                    <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                        <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                            <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Dados do Tiny ERP (somente leitura)</h3>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <span className="block text-xs text-gray-500">Codigo</span>
                                    <span className="text-sm font-mono text-gray-900">{editingProduto?.codigo || '-'}</span>
                                </div>
                                <div>
                                    <span className="block text-xs text-gray-500">Unidade</span>
                                    <span className="text-sm text-gray-900">{editingProduto?.unidade || '-'}</span>
                                </div>
                                <div>
                                    <span className="block text-xs text-gray-500">Preco Venda</span>
                                    <span className="text-sm text-gray-900">
                                        {editingProduto?.preco ? `R$ ${parseFloat(editingProduto.preco).toFixed(2)}` : '-'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="border-t pt-6">
                            <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Dados ClinicaWeb (editaveis)</h3>
                            <Input label="Nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} error={errors.nome} />
                            <div className="mt-4">
                                <Input label="Descricao / Formula" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)} multiline rows={3} placeholder="Formula ou descricao detalhada do produto..." />
                            </div>
                            <div className="mt-4">
                                <Input label="Modo de Uso" value={data.modo_uso} onChange={(e) => setData('modo_uso', e.target.value)} multiline rows={3} placeholder="Ex: Aplicar a noite apos limpeza" />
                            </div>
                            <div className="mt-4">
                                <Input label="Anotacoes dos Especialistas" value={data.anotacoes} onChange={(e) => setData('anotacoes', e.target.value)} multiline rows={3} placeholder="Dicas sobre o creme: para quem e indicado, outras formas de uso..." />
                            </div>
                        </div>

                        <Select label="Status" value={data.ativo ? '1' : '0'} onChange={(e) => setData('ativo', e.target.value === '1')} options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />
                    </div>
                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-end gap-3">
                            <button type="button" onClick={closeDrawer} className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                            <button type="submit" disabled={processing} className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">{processing ? 'Salvando...' : 'Salvar'}</button>
                        </div>
                    </div>
                </form>
            </Drawer>
            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
