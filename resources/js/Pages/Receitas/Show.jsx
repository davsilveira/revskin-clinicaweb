import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function ReceitaShow({ receita }) {
    const getStatusBadge = (status) => {
        const badges = {
            rascunho: 'bg-gray-100 text-gray-800',
            finalizada: 'bg-green-100 text-green-800',
            cancelada: 'bg-red-100 text-red-800',
        };
        const labels = {
            rascunho: 'Rascunho',
            finalizada: 'Finalizada',
            cancelada: 'Cancelada',
        };
        return (
            <span className={`px-3 py-1 text-sm font-medium rounded-full ${badges[status] || 'bg-gray-100'}`}>
                {labels[status] || status}
            </span>
        );
    };

    const handleCopiar = () => {
        if (confirm('Deseja criar uma copia desta receita?')) {
            router.post(`/receitas/${receita.id}/copiar`);
        }
    };

    const handleCancelar = () => {
        if (confirm('Deseja cancelar esta receita? Esta acao nao pode ser desfeita.')) {
            router.delete(`/receitas/${receita.id}`);
        }
    };

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        }).format(value || 0);
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-6xl mx-auto">
                <div className="mb-6">
                    <Link
                        href="/receitas"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Receitas
                    </Link>
                    
                    <div className="flex justify-between items-start mt-2">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">
                                Receita #{receita.numero || receita.id.toString().padStart(5, '0')}
                            </h1>
                            <p className="text-gray-500 mt-1">
                                Criada em {new Date(receita.created_at).toLocaleDateString('pt-BR')}
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            {getStatusBadge(receita.status)}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Dados da Receita */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Informacoes</h2>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm text-gray-500">Data da Receita</label>
                                    <p className="font-medium text-gray-900">
                                        {new Date(receita.data_receita).toLocaleDateString('pt-BR')}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm text-gray-500">Medico</label>
                                    <p className="font-medium text-gray-900">
                                        {receita.medico?.nome || '-'}
                                    </p>
                                    {receita.medico?.crm && (
                                        <p className="text-sm text-gray-500">
                                            CRM: {receita.medico.crm}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Valores */}
                            <div className="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label className="text-sm text-gray-500">Subtotal</label>
                                    <p className="font-medium text-gray-900">
                                        {formatCurrency(receita.subtotal)}
                                    </p>
                                </div>
                                {receita.desconto_percentual > 0 && (
                                    <div>
                                        <label className="text-sm text-gray-500">Desconto ({receita.desconto_percentual}%)</label>
                                        <p className="font-medium text-red-600">
                                            - {formatCurrency(receita.desconto_valor)}
                                        </p>
                                    </div>
                                )}
                                {receita.valor_frete > 0 && (
                                    <div>
                                        <label className="text-sm text-gray-500">Frete</label>
                                        <p className="font-medium text-gray-900">
                                            + {formatCurrency(receita.valor_frete)}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <label className="text-sm text-gray-500">Valor Total</label>
                                    <p className="text-xl font-bold text-emerald-600">
                                        {formatCurrency(receita.valor_total)}
                                    </p>
                                </div>
                            </div>

                            {receita.anotacoes && (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                    <label className="text-sm text-gray-500">Anotacoes Internas</label>
                                    <p className="text-gray-900 whitespace-pre-line">{receita.anotacoes}</p>
                                </div>
                            )}

                            {receita.anotacoes_paciente && (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                    <label className="text-sm text-gray-500">Anotacoes para o Paciente</label>
                                    <p className="text-gray-900 whitespace-pre-line">{receita.anotacoes_paciente}</p>
                                </div>
                            )}
                        </div>

                        {/* Itens */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Produtos da Receita</h2>
                            
                            <div className="space-y-3">
                                {receita.itens?.map((item, index) => (
                                    <div key={index} className="flex items-start justify-between p-4 bg-gray-50 rounded-lg">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-gray-900">
                                                    {item.produto?.codigo && (
                                                        <span className="text-emerald-600">{item.produto.codigo}</span>
                                                    )}
                                                    {' '}{item.produto?.nome || 'Produto'}
                                                </span>
                                                {item.imprimir && (
                                                    <span className="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">PDF</span>
                                                )}
                                            </div>
                                            {item.local_uso && (
                                                <div className="text-sm text-gray-500 mt-1">
                                                    Local de uso: {item.local_uso}
                                                </div>
                                            )}
                                            {item.anotacoes && (
                                                <div className="text-sm text-gray-600 mt-1 italic">
                                                    {item.anotacoes}
                                                </div>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <div className="text-sm text-gray-500">
                                                {item.quantidade}x {formatCurrency(item.valor_unitario)}
                                            </div>
                                            <div className="font-semibold text-gray-900">
                                                {formatCurrency(item.valor_total)}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-4 pt-4 border-t border-gray-200 flex justify-end">
                                <div className="text-right">
                                    <span className="text-sm text-gray-500">Total</span>
                                    <div className="text-2xl font-bold text-emerald-600">
                                        {formatCurrency(receita.valor_total)}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Call Center */}
                        {receita.atendimento_callcenter && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h2 className="text-lg font-semibold text-gray-900 mb-4">Atendimento Call Center</h2>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className={`px-3 py-1 text-sm font-medium rounded-full ${
                                            receita.atendimento_callcenter.status === 'finalizado' ? 'bg-green-100 text-green-800' :
                                            receita.atendimento_callcenter.status === 'em_atendimento' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-gray-100 text-gray-800'
                                        }`}>
                                            {receita.atendimento_callcenter.status}
                                        </span>
                                    </div>
                                    <Link
                                        href={`/callcenter/${receita.atendimento_callcenter.id}`}
                                        className="text-emerald-600 hover:text-emerald-700 text-sm font-medium"
                                    >
                                        Ver atendimento →
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Paciente */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Paciente</h2>
                            
                            <div className="space-y-3">
                                <div>
                                    <label className="text-sm text-gray-500">Nome</label>
                                    <p className="font-medium text-gray-900">{receita.paciente?.nome}</p>
                                </div>
                                {receita.paciente?.cpf && (
                                    <div>
                                        <label className="text-sm text-gray-500">CPF</label>
                                        <p className="font-medium text-gray-900">{receita.paciente.cpf}</p>
                                    </div>
                                )}
                                {receita.paciente?.telefone1 && (
                                    <div>
                                        <label className="text-sm text-gray-500">Telefone</label>
                                        <p className="font-medium text-gray-900">{receita.paciente.telefone1}</p>
                                    </div>
                                )}
                                {receita.paciente?.email1 && (
                                    <div>
                                        <label className="text-sm text-gray-500">Email</label>
                                        <p className="font-medium text-gray-900 break-all">{receita.paciente.email1}</p>
                                    </div>
                                )}
                            </div>

                            <Link
                                href={`/pacientes/${receita.paciente?.id}`}
                                className="mt-4 block text-center text-emerald-600 hover:text-emerald-700 text-sm font-medium"
                            >
                                Ver perfil completo →
                            </Link>
                        </div>

                        {/* Acoes */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Acoes</h2>
                            
                            <div className="space-y-3">
                                {receita.status === 'finalizada' && (
                                    <a
                                        href={`/receitas/${receita.id}/pdf`}
                                        target="_blank"
                                        className="w-full flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Download PDF
                                    </a>
                                )}

                                {receita.status === 'rascunho' && (
                                    <Link
                                        href={`/receitas/${receita.id}/edit`}
                                        className="w-full flex items-center justify-center gap-2 px-4 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Editar Receita
                                    </Link>
                                )}

                                <button
                                    onClick={handleCopiar}
                                    className="w-full flex items-center justify-center gap-2 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    Copiar Receita
                                </button>

                                {receita.status !== 'cancelada' && (
                                    <button
                                        onClick={handleCancelar}
                                        className="w-full flex items-center justify-center gap-2 px-4 py-3 border border-red-300 text-red-700 rounded-lg hover:bg-red-50 transition-colors"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Cancelar Receita
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
