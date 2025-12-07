import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';

export default function MedicosIndex({ medicos, clinicas = [], filters }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingMedico, setEditingMedico] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nome: '',
        crm: '',
        uf_crm: '',
        especialidade: '',
        email: '',
        telefone: '',
        celular: '',
        clinica_id: '',
        comissao_percentual: '',
        assinatura: null,
        ativo: true,
    });

    const openCreateDrawer = () => {
        reset();
        setEditingMedico(null);
        setShowDeleteConfirm(false);
        setDrawerOpen(true);
    };

    const openEditDrawer = (medico) => {
        setEditingMedico(medico);
        setShowDeleteConfirm(false);
        setData({
            nome: medico.nome || '',
            crm: medico.crm || '',
            uf_crm: medico.uf_crm || '',
            especialidade: medico.especialidade || '',
            email: medico.email || '',
            telefone: medico.telefone || '',
            celular: medico.celular || '',
            clinica_id: medico.clinica_id || '',
            comissao_percentual: medico.comissao_percentual || '',
            assinatura: null,
            ativo: medico.ativo ?? true,
        });
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingMedico(null);
        setShowDeleteConfirm(false);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const formData = new FormData();
        Object.keys(data).forEach(key => {
            if (data[key] !== null && data[key] !== '') {
                formData.append(key, data[key]);
            }
        });

        if (editingMedico) {
            formData.append('_method', 'PUT');
            router.post(`/medicos/${editingMedico.id}`, formData, {
                onSuccess: () => {
                    closeDrawer();
                    setToast({ message: 'Medico atualizado com sucesso!', type: 'success' });
                },
            });
        } else {
            router.post('/medicos', formData, {
                onSuccess: () => {
                    closeDrawer();
                    setToast({ message: 'Medico cadastrado com sucesso!', type: 'success' });
                },
            });
        }
    };

    const handleDelete = () => {
        if (editingMedico) {
            router.delete(`/medicos/${editingMedico.id}`, {
                onSuccess: () => {
                    closeDrawer();
                    setToast({ message: 'Medico excluido com sucesso!', type: 'success' });
                },
            });
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/medicos', { search }, { preserveState: true });
    };

    const medicosList = medicos?.data || medicos || [];

    return (
        <DashboardLayout>
            <Head title="Medicos" />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Medicos</h1>
                        <p className="text-gray-600 mt-1">Gerencie os medicos cadastrados</p>
                    </div>
                    <button
                        onClick={openCreateDrawer}
                        className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Novo Medico
                    </button>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex gap-4">
                        <input
                            type="text"
                            placeholder="Buscar por nome ou CRM..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        />
                        <button type="submit" className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            Buscar
                        </button>
                    </form>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CRM</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Especialidade</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clinica</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {medicosList.length > 0 ? medicosList.map((medico) => (
                                <tr key={medico.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="flex items-center">
                                            <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                <span className="text-sm font-medium text-blue-700">{medico.nome?.charAt(0)}</span>
                                            </div>
                                            <div className="text-sm font-medium text-gray-900">{medico.nome}</div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{medico.crm ? `${medico.crm}/${medico.uf_crm}` : '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{medico.especialidade || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{medico.clinica?.nome || '-'}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 text-xs rounded-full ${medico.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {medico.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button onClick={() => openEditDrawer(medico)} className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg">
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan="6" className="px-6 py-12 text-center text-gray-500">Nenhum medico encontrado</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Drawer isOpen={drawerOpen} onClose={closeDrawer} title={editingMedico ? 'Editar Medico' : 'Novo Medico'} size="lg">
                <form onSubmit={handleSubmit} className="flex flex-col h-full">
                    <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                        <Input label="Nome Completo" value={data.nome} onChange={(e) => setData('nome', e.target.value)} error={errors.nome} required />
                        <div className="grid grid-cols-3 gap-4">
                            <div className="col-span-2">
                                <Input label="CRM" value={data.crm} onChange={(e) => setData('crm', e.target.value)} error={errors.crm} required />
                            </div>
                            <Select label="UF" value={data.uf_crm} onChange={(e) => setData('uf_crm', e.target.value)} options={[{ value: '', label: 'UF' }, ...['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => ({ value: uf, label: uf }))]} />
                        </div>
                        <Input label="Especialidade" value={data.especialidade} onChange={(e) => setData('especialidade', e.target.value)} placeholder="Ex: Dermatologia" />
                        <Input label="E-mail" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                        <div className="grid grid-cols-2 gap-4">
                            <Input label="Telefone" value={data.telefone} onChange={(e) => setData('telefone', e.target.value)} placeholder="(00) 0000-0000" />
                            <Input label="Celular" value={data.celular} onChange={(e) => setData('celular', e.target.value)} placeholder="(00) 00000-0000" />
                        </div>
                        <Select label="Clinica" value={data.clinica_id} onChange={(e) => setData('clinica_id', e.target.value)} options={[{ value: '', label: 'Selecione uma clinica' }, ...clinicas.map(c => ({ value: c.id, label: c.nome }))]} />
                        <Input label="Comissao (%)" type="number" value={data.comissao_percentual} onChange={(e) => setData('comissao_percentual', e.target.value)} step="0.01" min="0" max="100" />
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Assinatura (imagem)</label>
                            <input type="file" accept="image/*" onChange={(e) => setData('assinatura', e.target.files[0])} className="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                            {editingMedico?.assinatura_url && <img src={editingMedico.assinatura_url} alt="Assinatura" className="h-16 border rounded mt-2" />}
                        </div>
                        {editingMedico && <Select label="Status" value={data.ativo ? '1' : '0'} onChange={(e) => setData('ativo', e.target.value === '1')} options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />}
                    </div>
                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div>
                                {editingMedico && !showDeleteConfirm && <button type="button" onClick={() => setShowDeleteConfirm(true)} className="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Excluir</button>}
                                {showDeleteConfirm && <div className="flex items-center gap-2"><span className="text-sm">Confirmar exclusao?</span><button type="button" onClick={handleDelete} className="px-3 py-1 bg-red-600 text-white rounded">Sim</button><button type="button" onClick={() => setShowDeleteConfirm(false)} className="px-3 py-1 bg-gray-200 rounded">Nao</button></div>}
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
