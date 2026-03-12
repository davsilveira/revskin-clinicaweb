import { Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Pagination from '@/Components/Pagination';
import ExportConfirmModal from '@/Components/ExportConfirmModal';
import Toast from '@/Components/Toast';

export default function ReceitasMedico({ medicos, dados, filters }) {
    const { auth, flash } = usePage().props || {};
    const [medicoId, setMedicoId] = useState(filters?.medico_id || '');
    const [dataInicio, setDataInicio] = useState(filters?.data_inicio || '');
    const [dataFim, setDataFim] = useState(filters?.data_fim || '');
    const [perPage, setPerPage] = useState(filters?.per_page || 15);
    const [exportModal, setExportModal] = useState({ open: false, format: null });
    const [toast, setToast] = useState(null);

    // Sincronizar estado com filters quando mudarem (ex: navegação de paginação)
    useEffect(() => {
        setMedicoId(filters?.medico_id || '');
        setDataInicio(filters?.data_inicio || '');
        setDataFim(filters?.data_fim || '');
        setPerPage(filters?.per_page || 15);
    }, [filters]);

    useEffect(() => {
        if (flash?.success) setToast({ message: flash.success, type: 'success' });
        if (flash?.error) setToast({ message: flash.error, type: 'error' });
    }, [flash?.success, flash?.error]);

    const handleFiltrar = (e) => {
        e.preventDefault();
        router.get('/relatorios/receitas-medico', {
            medico_id: medicoId,
            data_inicio: dataInicio,
            data_fim: dataFim,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePerPageChange = (e) => {
        const newPerPage = Number(e.target.value);
        setPerPage(newPerPage);
        router.get('/relatorios/receitas-medico', {
            medico_id: medicoId,
            data_inicio: dataInicio,
            data_fim: dataFim,
            per_page: newPerPage,
        }, { preserveState: true });
    };

    const openExportModal = (format) => {
        setExportModal({ open: true, format });
    };

    const handleExportConfirm = (extraEmails) => {
        const payload = {
            format: exportModal.format,
            medico_id: medicoId || null,
            data_inicio: dataInicio || null,
            data_fim: dataFim || null,
            extra_emails: extraEmails,
        };

        router.post('/relatorios/receitas-medico/export', payload, {
            preserveScroll: true,
            onSuccess: () => {
                setExportModal({ open: false, format: null });
                setToast({
                    message: 'A exportação será processada em segundo plano. Você receberá um e-mail quando estiver pronto para download.',
                    type: 'success'
                });
            },
            onError: (errors) => {
                setToast({
                    message: Object.values(errors).flat().find(Boolean) || 'Erro ao solicitar exportação.',
                    type: 'error'
                });
            }
        });
    };

    const getItemCount = () => {
        return dados?.receitas?.total ?? dados?.receitas?.data?.length ?? 0;
    };

    return (
        <DashboardLayout>
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
            <ExportConfirmModal
                open={exportModal.open}
                onClose={() => setExportModal({ open: false, format: null })}
                onConfirm={handleExportConfirm}
                itemCount={getItemCount()}
                itemLabel="receitas"
                formatLabel={exportModal.format === 'pdf' ? 'PDF' : 'Excel'}
                defaultEmail={auth?.user?.email}
                title="Confirmar exportação"
            />
            <div className="p-6">
                <div className="mb-6">
                    <Link
                        href="/relatorios"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Relatórios
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Receitas por Médico</h1>
                    <p className="text-gray-600 mt-1">Relatório detalhado de receitas por médico</p>
                </div>

                {/* Filtros */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <form onSubmit={handleFiltrar} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Médico
                            </label>
                            <select
                                value={medicoId}
                                onChange={(e) => setMedicoId(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="">Todos os médicos</option>
                                {medicos?.map((medico) => (
                                    <option key={medico.id} value={medico.id}>
                                        {medico.nome}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Data Início
                            </label>
                            <input
                                type="date"
                                value={dataInicio}
                                onChange={(e) => setDataInicio(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Data Fim
                            </label>
                            <input
                                type="date"
                                value={dataFim}
                                onChange={(e) => setDataFim(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>

                        <div className="flex items-end">
                            <button
                                type="submit"
                                className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors cursor-pointer"
                            >
                                Filtrar
                            </button>
                        </div>
                    </form>
                </div>

                {/* Resultados */}
                {dados && (
                    <>
                        {/* Resumo */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div className="text-sm text-gray-500">Total de Receitas</div>
                                <div className="text-3xl font-bold text-gray-900 mt-1">
                                    {dados.totais?.quantidade || 0}
                                </div>
                            </div>
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div className="text-sm text-gray-500">Valor Total</div>
                                <div className="text-3xl font-bold text-emerald-600 mt-1">
                                    {new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL',
                                    }).format(dados.totais?.valor_total || 0)}
                                </div>
                            </div>
                        </div>

                        {/* Tabela */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="flex justify-between items-center p-4 border-b border-gray-200">
                                <div className="flex items-center gap-4">
                                    {dados?.receitas && (
                                        <div className="flex items-center gap-2 text-sm text-gray-600">
                                            <span>
                                                {dados.receitas.total} registros
                                            </span>
                                            <span className="text-gray-400">/</span>
                                            <select
                                                value={perPage}
                                                onChange={handlePerPageChange}
                                                className="px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            >
                                                <option value={15}>15 por página</option>
                                                <option value={30}>30 por página</option>
                                                <option value={50}>50 por página</option>
                                                <option value={100}>100 por página</option>
                                            </select>
                                        </div>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => openExportModal('pdf')}
                                        className="px-3 py-1.5 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-1 cursor-pointer"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        PDF
                                    </button>
                                    <button
                                        onClick={() => openExportModal('xlsx')}
                                        className="px-3 py-1.5 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-1 cursor-pointer"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Excel
                                    </button>
                                </div>
                            </div>

                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Receita
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Médico
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Paciente
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Data
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Valor
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {dados.receitas?.data?.length > 0 ? (
                                        dados.receitas.data.map((receita) => (
                                            <tr key={receita.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Link
                                                        href={`/receitas/${receita.id}`}
                                                        className="text-emerald-600 hover:text-emerald-700 font-medium"
                                                    >
                                                        #{receita.numero}
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {receita.medico?.nome || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {receita.paciente?.nome || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {new Date(receita.data_receita).toLocaleDateString('pt-BR')}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                                        receita.status === 'finalizada'
                                                            ? 'bg-green-100 text-green-800'
                                                            : receita.status === 'cancelada'
                                                            ? 'bg-red-100 text-red-800'
                                                            : 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {receita.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    {new Intl.NumberFormat('pt-BR', {
                                                        style: 'currency',
                                                        currency: 'BRL',
                                                    }).format(receita.valor_total || 0)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                                Nenhuma receita encontrada
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>

                            {/* Pagination */}
                            {dados.receitas?.links && dados.receitas.links.length > 3 && (
                                <Pagination links={dados.receitas.links} />
                            )}
                        </div>
                    </>
                )}

                {!dados && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Selecione os Filtros</h3>
                        <p className="text-gray-500">
                            Escolha o médico e/ou período para gerar o relatório
                        </p>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}










