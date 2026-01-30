import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function CallCenterIndex({ atendimentos, filters }) {
    const [status, setStatus] = useState(filters?.status || '');
    const [search, setSearch] = useState(filters?.search || '');
    const [selectedIds, setSelectedIds] = useState([]);

    const statusConfig = {
        entrar_em_contato: { label: 'Entrar em Contato', color: 'bg-yellow-100 text-yellow-800' },
        aguardando_retorno: { label: 'Aguardando Retorno', color: 'bg-purple-100 text-purple-800' },
        em_producao: { label: 'Em Produção', color: 'bg-blue-100 text-blue-800' },
        finalizado: { label: 'Finalizado', color: 'bg-green-100 text-green-800' },
        cancelado: { label: 'Cancelado', color: 'bg-red-100 text-red-800' },
    };

    const handleFilter = () => {
        router.get('/callcenter', { status, search }, { preserveState: true });
    };

    const handleStatusChange = (id, newStatus) => {
        router.put(`/callcenter/${id}/status`, { status: newStatus }, { preserveState: true });
    };

    const handleCancelarMultiplos = () => {
        if (selectedIds.length === 0) return;
        if (confirm(`Deseja cancelar ${selectedIds.length} atendimento(s)?`)) {
            router.post('/callcenter/cancelar', { ids: selectedIds });
            setSelectedIds([]);
        }
    };

    const toggleSelection = (id) => {
        setSelectedIds(prev => 
            prev.includes(id) 
                ? prev.filter(i => i !== id)
                : [...prev, id]
        );
    };

    const toggleAll = () => {
        if (selectedIds.length === atendimentos?.data?.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(atendimentos?.data?.map(a => a.id) || []);
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Call Center</h1>
                        <p className="text-gray-600 mt-1">Fila de atendimentos e acompanhamento</p>
                    </div>
                    {selectedIds.length > 0 && (
                        <button
                            onClick={handleCancelarMultiplos}
                            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelar {selectedIds.length} selecionado(s)
                        </button>
                    )}
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    {Object.entries(statusConfig).map(([key, config]) => {
                        const count = atendimentos?.data?.filter(a => a.status === key).length || 0;
                        return (
                            <div
                                key={key}
                                onClick={() => setStatus(status === key ? '' : key)}
                                className={`p-4 rounded-xl cursor-pointer transition-all ${
                                    status === key 
                                        ? 'ring-2 ring-emerald-500 bg-emerald-50' 
                                        : 'bg-white hover:shadow-md'
                                } border border-gray-200`}
                            >
                                <div className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${config.color}`}>
                                    {config.label}
                                </div>
                                <div className="text-2xl font-bold text-gray-900 mt-2">{count}</div>
                            </div>
                        );
                    })}
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <div className="flex gap-4">
                        <div className="flex-1">
                            <input
                                type="text"
                                placeholder="Buscar por paciente..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="">Todos os status</option>
                            {Object.entries(statusConfig).map(([key, config]) => (
                                <option key={key} value={key}>{config.label}</option>
                            ))}
                        </select>
                        <button
                            onClick={handleFilter}
                            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Filtrar
                        </button>
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.length === atendimentos?.data?.length && atendimentos?.data?.length > 0}
                                        onChange={toggleAll}
                                        className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                    />
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Receita
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Paciente
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Telefone
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Data
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {atendimentos?.data?.length > 0 ? (
                                atendimentos.data.map((atendimento) => (
                                    <tr key={atendimento.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-4">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(atendimento.id)}
                                                onChange={() => toggleSelection(atendimento.id)}
                                                className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                            />
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="text-sm font-medium text-gray-900">
                                                #{atendimento.receita?.numero}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">
                                                {atendimento.paciente?.nome}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {atendimento.paciente?.telefone || '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {new Date(atendimento.created_at).toLocaleDateString('pt-BR')}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <select
                                                value={atendimento.status}
                                                onChange={(e) => handleStatusChange(atendimento.id, e.target.value)}
                                                className={`text-xs font-medium rounded-full px-3 py-1 border-0 ${
                                                    statusConfig[atendimento.status]?.color
                                                }`}
                                            >
                                                {Object.entries(statusConfig).map(([key, config]) => (
                                                    <option key={key} value={key}>{config.label}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link
                                                href={`/callcenter/${atendimento.id}`}
                                                className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors inline-block"
                                                title="Ver detalhes"
                                            >
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="7" className="px-6 py-12 text-center">
                                        <div className="text-gray-500">
                                            <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <p>Nenhum atendimento na fila</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {atendimentos?.links && atendimentos.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                            <div className="text-sm text-gray-500">
                                Mostrando {atendimentos.from} a {atendimentos.to} de {atendimentos.total} resultados
                            </div>
                            <div className="flex gap-1">
                                {atendimentos.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`px-3 py-1 rounded ${
                                            link.active
                                                ? 'bg-emerald-600 text-white'
                                                : link.url
                                                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                                : 'bg-gray-50 text-gray-400 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}




