import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PatientDrawer from '@/Components/PatientDrawer';
import Toast from '@/Components/Toast';

export default function PacientesIndex({ pacientes, medicos = [], tiposTelefone = {}, isAdmin = false, filters }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingPaciente, setEditingPaciente] = useState(null);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.ativo ?? '1');

    const openCreateDrawer = () => {
        setEditingPaciente(null);
        setDrawerOpen(true);
    };

    const openEditDrawer = (paciente) => {
        setEditingPaciente(paciente);
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingPaciente(null);
    };

    const handleSave = () => {
        closeDrawer();
        setToast({ 
            message: editingPaciente ? 'Paciente atualizado com sucesso!' : 'Paciente cadastrado com sucesso!', 
            type: 'success' 
        });
        router.reload({ only: ['pacientes'] });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        const params = { search, ativo: status };
        router.get('/pacientes', params, { preserveState: true });
    };

    const pacientesList = pacientes?.data || pacientes || [];

    return (
        <DashboardLayout>
            <Head title="Pacientes" />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Pacientes</h1>
                        <p className="text-gray-600 mt-1">Gerencie os pacientes cadastrados</p>
                    </div>
                    <button
                        onClick={openCreateDrawer}
                        className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Novo Paciente
                    </button>
                </div>

                {/* Search */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex gap-4">
                        <input
                            type="text"
                            placeholder="Buscar por nome ou CPF..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        />
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="">Todos</option>
                            <option value="1">Ativos</option>
                            <option value="0">Inativos</option>
                        </select>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Buscar
                        </button>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefone</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cidade</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {pacientesList.length > 0 ? (
                                pacientesList.map((paciente) => (
                                    <tr key={paciente.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mr-3">
                                                    <span className="text-sm font-medium text-emerald-700">
                                                        {paciente.nome?.charAt(0).toUpperCase()}
                                                    </span>
                                                </div>
                                                <div className="text-sm font-medium text-gray-900">{paciente.nome}</div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.cpf || '-'}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.telefone2 || paciente.telefone1 || '-'}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.cidade ? `${paciente.cidade}/${paciente.uf}` : '-'}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-1 text-xs font-medium rounded-full ${paciente.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                {paciente.ativo ? 'Ativo' : 'Inativo'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right">
                                            <button
                                                onClick={() => openEditDrawer(paciente)}
                                                className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                title="Editar"
                                            >
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                        Nenhum paciente encontrado
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {pacientes?.links && pacientes.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                            <div className="text-sm text-gray-500">
                                Mostrando {pacientes.from} a {pacientes.to} de {pacientes.total}
                            </div>
                            <div className="flex gap-1">
                                {pacientes.links.map((link, i) => (
                                    <button
                                        key={i}
                                        onClick={() => link.url && router.get(link.url)}
                                        disabled={!link.url}
                                        className={`px-3 py-1 rounded text-sm ${link.active ? 'bg-emerald-600 text-white' : link.url ? 'bg-gray-100 hover:bg-gray-200' : 'bg-gray-50 text-gray-400'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Patient Drawer */}
            <PatientDrawer
                isOpen={drawerOpen}
                onClose={closeDrawer}
                paciente={editingPaciente}
                onSave={handleSave}
                isAdmin={isAdmin}
                enableAutoSave={true}
            />

            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
