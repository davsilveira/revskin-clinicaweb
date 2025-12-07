import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';

export default function TabelasPrecoIndex({ tabelas, filters }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingTabela, setEditingTabela] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nome: '',
        descricao: '',
        desconto_percentual: '',
        data_inicio: '',
        data_fim: '',
        ativo: true,
    });

    const openCreateDrawer = () => {
        reset();
        setEditingTabela(null);
        setShowDeleteConfirm(false);
        setDrawerOpen(true);
    };

    const openEditDrawer = (tabela) => {
        setEditingTabela(tabela);
        setShowDeleteConfirm(false);
        setData({
            nome: tabela.nome || '',
            descricao: tabela.descricao || '',
            desconto_percentual: tabela.desconto_percentual || '',
            data_inicio: tabela.data_inicio || '',
            data_fim: tabela.data_fim || '',
            ativo: tabela.ativo ?? true,
        });
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingTabela(null);
        setShowDeleteConfirm(false);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingTabela) {
            put(`/tabelas-preco/${editingTabela.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Tabela atualizada!', type: 'success' }); },
            });
        } else {
            post('/tabelas-preco', {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Tabela cadastrada!', type: 'success' }); },
            });
        }
    };

    const handleDelete = () => {
        if (editingTabela) {
            router.delete(`/tabelas-preco/${editingTabela.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Tabela excluida!', type: 'success' }); },
            });
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/tabelas-preco', { search }, { preserveState: true });
    };

    const tabelasList = tabelas?.data || tabelas || [];

    return (
        <DashboardLayout>
            <Head title="Tabelas de Preco" />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Tabelas de Preco</h1>
                        <p className="text-gray-600 mt-1">Gerencie as tabelas de preco</p>
                    </div>
                    <button onClick={openCreateDrawer} className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 flex items-center gap-2">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Nova Tabela
                    </button>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex gap-4">
                        <input type="text" placeholder="Buscar por nome..." value={search} onChange={(e) => setSearch(e.target.value)} className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500" />
                        <button type="submit" className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Buscar</button>
                    </form>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Desconto</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periodo</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {tabelasList.length > 0 ? tabelasList.map((tabela) => (
                                <tr key={tabela.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4">
                                        <div className="text-sm font-medium text-gray-900">{tabela.nome}</div>
                                        {tabela.descricao && <div className="text-sm text-gray-500">{tabela.descricao}</div>}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">
                                        {tabela.desconto_percentual ? `${tabela.desconto_percentual}%` : '-'}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">
                                        {tabela.data_inicio && tabela.data_fim 
                                            ? `${new Date(tabela.data_inicio).toLocaleDateString('pt-BR')} a ${new Date(tabela.data_fim).toLocaleDateString('pt-BR')}`
                                            : tabela.data_inicio 
                                                ? `A partir de ${new Date(tabela.data_inicio).toLocaleDateString('pt-BR')}`
                                                : 'Sem periodo definido'
                                        }
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 text-xs rounded-full ${tabela.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {tabela.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button onClick={() => openEditDrawer(tabela)} className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg">Editar</button>
                                    </td>
                                </tr>
                            )) : <tr><td colSpan="5" className="px-6 py-12 text-center text-gray-500">Nenhuma tabela encontrada</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>

            <Drawer isOpen={drawerOpen} onClose={closeDrawer} title={editingTabela ? 'Editar Tabela' : 'Nova Tabela'}>
                <form onSubmit={handleSubmit} className="flex flex-col h-full">
                    <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                        <Input label="Nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} error={errors.nome} required />
                        <Input label="Descricao" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)} multiline rows={2} />
                        <Input label="Desconto Percentual" type="number" step="0.01" value={data.desconto_percentual} onChange={(e) => setData('desconto_percentual', e.target.value)} placeholder="0.00" />
                        <div className="grid grid-cols-2 gap-4">
                            <Input label="Data Inicio" type="date" value={data.data_inicio} onChange={(e) => setData('data_inicio', e.target.value)} />
                            <Input label="Data Fim" type="date" value={data.data_fim} onChange={(e) => setData('data_fim', e.target.value)} />
                        </div>
                        {editingTabela && <Select label="Status" value={data.ativo ? '1' : '0'} onChange={(e) => setData('ativo', e.target.value === '1')} options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />}
                    </div>
                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div>
                                {editingTabela && !showDeleteConfirm && <button type="button" onClick={() => setShowDeleteConfirm(true)} className="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Excluir</button>}
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
