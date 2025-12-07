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
        if (confirm('Deseja criar uma cópia desta receita?')) {
            router.post(`/receitas/${receita.id}/copiar`);
        }
    };

    const handleCancelar = () => {
        if (confirm('Deseja cancelar esta receita? Esta ação não pode ser desfeita.')) {
            router.put(`/receitas/${receita.id}`, { status: 'cancelada' });
        }
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
                                Receita #{receita.id.toString().padStart(5, '0')}
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
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Informações</h2>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm text-gray-500">Data da Receita</label>
                                    <p className="font-medium text-gray-900">
                                        {new Date(receita.data_receita).toLocaleDateString('pt-BR')}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm text-gray-500">Médico</label>
                                    <p className="font-medium text-gray-900">
                                        {receita.medico?.nome || '-'}
                                    </p>
                                    <p className="text-sm text-gray-500">
                                        CRM: {receita.medico?.crm || '-'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm text-gray-500">Tabela de Preço</label>
                                    <p className="font-medium text-gray-900">
                                        {receita.tabela_preco?.nome || 'Tabela Padrão'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm text-gray-500">Valor Total</label>
                                    <p className="text-xl font-bold text-emerald-600">
                                        {new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                        }).format(receita.valor_total)}
                                    </p>
                                </div>
                            </div>

                            {receita.observacoes && (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                    <label className="text-sm text-gray-500">Observações</label>
                                    <p className="text-gray-900 whitespace-pre-line">{receita.observacoes}</p>
                                </div>
                            )}
                        </div>

                        {/* Itens */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Itens da Receita</h2>
                            
                            <div className="space-y-3">
                                {receita.itens?.map((item, index) => (
                                    <div key={index} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div className="flex-1">
                                            <div className="font-medium text-gray-900">
                                                {item.produto?.nome || 'Produto'}
                                            </div>
                                            {item.comentario && (
                                                <div className="text-sm text-gray-500 mt-1">
                                                    {item.comentario}
                                                </div>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <div className="text-sm text-gray-500">
                                                {item.quantidade}x {new Intl.NumberFormat('pt-BR', {
                                                    style: 'currency',
                                                    currency: 'BRL',
                                                }).format(item.preco_unitario)}
                                                {item.desconto > 0 && (
                                                    <span className="text-red-500 ml-1">
                                                        (-{item.desconto}%)
                                                    </span>
                                                )}
                                            </div>
                                            <div className="font-semibold text-gray-900">
                                                {new Intl.NumberFormat('pt-BR', {
                                                    style: 'currency',
                                                    currency: 'BRL',
                                                }).format(item.subtotal)}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-4 pt-4 border-t border-gray-200 flex justify-end">
                                <div className="text-right">
                                    <span className="text-sm text-gray-500">Total</span>
                                    <div className="text-2xl font-bold text-emerald-600">
                                        {new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                        }).format(receita.valor_total)}
                                    </div>
                                </div>
                            </div>
                        </div>
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
                                <div>
                                    <label className="text-sm text-gray-500">CPF</label>
                                    <p className="font-medium text-gray-900">{receita.paciente?.cpf}</p>
                                </div>
                                {receita.paciente?.telefone && (
                                    <div>
                                        <label className="text-sm text-gray-500">Telefone</label>
                                        <p className="font-medium text-gray-900">{receita.paciente?.telefone}</p>
                                    </div>
                                )}
                                {receita.paciente?.email && (
                                    <div>
                                        <label className="text-sm text-gray-500">Email</label>
                                        <p className="font-medium text-gray-900">{receita.paciente?.email}</p>
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

                        {/* Ações */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Ações</h2>
                            
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

