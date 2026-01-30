import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function RelatoriosIndex({ medicos, relatorio }) {
    const [medicoId, setMedicoId] = useState('');
    const [dataInicio, setDataInicio] = useState('');
    const [dataFim, setDataFim] = useState('');

    const handleFiltrar = (e) => {
        e.preventDefault();
        router.get('/relatorios/receitas-medico', {
            medico_id: medicoId,
            data_inicio: dataInicio,
            data_fim: dataFim,
        }, { preserveState: true });
    };

    const handleExport = (format) => {
        const params = new URLSearchParams({
            medico_id: medicoId,
            data_inicio: dataInicio,
            data_fim: dataFim,
        });
        window.open(`/relatorios/receitas-medico/export/${format}?${params}`, '_blank');
    };

    return (
        <DashboardLayout>
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Relatórios</h1>
                    <p className="text-gray-600 mt-1">Gere relatórios e exporte dados</p>
                </div>

                {/* Filtros */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Relatório de Receitas por Médico</h2>
                    
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
                                className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                            >
                                Filtrar
                            </button>
                        </div>
                    </form>
                </div>

                {/* Resultados */}
                {relatorio && (
                    <>
                        {/* Resumo */}
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div className="text-sm text-gray-500">Total de Receitas</div>
                                <div className="text-2xl font-bold text-gray-900 mt-1">
                                    {relatorio.total_receitas || 0}
                                </div>
                            </div>
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div className="text-sm text-gray-500">Valor Total</div>
                                <div className="text-2xl font-bold text-emerald-600 mt-1">
                                    {new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL',
                                    }).format(relatorio.valor_total || 0)}
                                </div>
                            </div>
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div className="text-sm text-gray-500">Média por Receita</div>
                                <div className="text-2xl font-bold text-gray-900 mt-1">
                                    {new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL',
                                    }).format(relatorio.media_receita || 0)}
                                </div>
                            </div>
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div className="text-sm text-gray-500">Pacientes Atendidos</div>
                                <div className="text-2xl font-bold text-gray-900 mt-1">
                                    {relatorio.pacientes_unicos || 0}
                                </div>
                            </div>
                        </div>

                        {/* Tabela */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="flex justify-between items-center p-4 border-b border-gray-200">
                                <h3 className="font-semibold text-gray-900">Detalhamento</h3>
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => handleExport('pdf')}
                                        className="px-3 py-1.5 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-1"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        PDF
                                    </button>
                                    <button
                                        onClick={() => handleExport('xlsx')}
                                        className="px-3 py-1.5 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-1"
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
                                    {relatorio.receitas?.length > 0 ? (
                                        relatorio.receitas.map((receita) => (
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
                                                    }).format(receita.valor_total)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                                Nenhuma receita encontrada para os filtros selecionados
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {!relatorio && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Gere um Relatório</h3>
                        <p className="text-gray-500">
                            Selecione os filtros acima e clique em "Filtrar" para gerar o relatório
                        </p>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}










