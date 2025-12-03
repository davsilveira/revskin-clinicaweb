import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Pagination from '@/Components/Pagination';
import Toast from '@/Components/Toast';

export default function ExportsIndex({ history, historyFilters, historyStatusOptions }) {
    const [toast, setToast] = useState(null);
    const [showClearConfirm, setShowClearConfirm] = useState(false);

    const { data, setData, post, processing } = useForm({
        type: 'default',
        export_all_fields: true,
        selected_fields: [],
        filters: {},
    });

    const handleExport = (e) => {
        e.preventDefault();
        post('/exports', {
            onSuccess: () => {
                setToast({ message: 'Exportação agendada com sucesso!', type: 'success' });
            },
        });
    };

    const handleStatusFilter = (status) => {
        router.get('/exports', { history_status: status }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleClearHistory = () => {
        router.delete('/exports/history', {
            onSuccess: () => {
                setShowClearConfirm(false);
                setToast({ message: 'Histórico limpo com sucesso!', type: 'success' });
            },
        });
    };

    return (
        <DashboardLayout>
            <Head title="Exportações" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Exportações</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Exporte dados do sistema em diversos formatos.
                        </p>
                    </div>
                </div>

                {/* Export Form */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Nova Exportação</h2>
                    <form onSubmit={handleExport}>
                        <div className="space-y-4">
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
                                <svg className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div className="text-sm text-blue-800">
                                    <p className="font-medium mb-1">Como funciona:</p>
                                    <p>
                                        Clique em "Exportar" para iniciar o processo. Você receberá um e-mail
                                        quando a exportação estiver pronta para download.
                                    </p>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-lg shadow-blue-600/30 flex items-center gap-2"
                                >
                                    {processing ? (
                                        <>
                                            <svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Processando...
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                                            </svg>
                                            Exportar
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {/* History */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div className="p-6 border-b border-gray-200">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900">Histórico de Exportações</h2>
                            <div className="flex items-center gap-4">
                                {/* Status Filter */}
                                <select
                                    value={historyFilters.status}
                                    onChange={(e) => handleStatusFilter(e.target.value)}
                                    className="px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    {historyStatusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>

                                {/* Clear History */}
                                {history.data.length > 0 && (
                                    showClearConfirm ? (
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-600">Limpar tudo?</span>
                                            <button
                                                onClick={handleClearHistory}
                                                className="px-3 py-1.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700"
                                            >
                                                Sim
                                            </button>
                                            <button
                                                onClick={() => setShowClearConfirm(false)}
                                                className="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300"
                                            >
                                                Não
                                            </button>
                                        </div>
                                    ) : (
                                        <button
                                            onClick={() => setShowClearConfirm(true)}
                                            className="px-4 py-2 text-red-600 hover:text-red-700 hover:bg-red-50 font-medium rounded-lg transition-colors text-sm"
                                        >
                                            Limpar Histórico
                                        </button>
                                    )
                                )}
                            </div>
                        </div>
                    </div>

                    {history.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <svg className="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p className="text-gray-500">Nenhuma exportação encontrada</p>
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tipo
                                            </th>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Registros
                                            </th>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Solicitado em
                                            </th>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Solicitado por
                                            </th>
                                            <th className="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {history.data.map((exportItem) => (
                                            <tr key={exportItem.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="font-medium text-gray-900">
                                                        {exportItem.type_label}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${exportItem.status_badge}`}>
                                                        {exportItem.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    {exportItem.total_records ?? '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    {exportItem.created_at}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    {exportItem.requested_by?.name ?? '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right">
                                                    {exportItem.download_url ? (
                                                        <a
                                                            href={exportItem.download_url}
                                                            className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors inline-flex items-center gap-2"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                                                            </svg>
                                                            Baixar
                                                        </a>
                                                    ) : exportItem.status === 'failed' ? (
                                                        <span className="text-sm text-red-600" title={exportItem.error_message}>
                                                            Erro
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-gray-400">
                                                            Aguardando...
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            <Pagination links={history.links} />
                        </>
                    )}
                </div>
            </div>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
        </DashboardLayout>
    );
}

