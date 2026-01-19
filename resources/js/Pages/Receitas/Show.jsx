import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Tippy from '@tippyjs/react';
import 'tippy.js/dist/tippy.css';

export default function ReceitaShow({ receita, receitasAnteriores = [] }) {
    const { auth } = usePage().props;
    const isMedico = auth.user.role === 'medico';
    const [showCopiarModal, setShowCopiarModal] = useState(false);
    const [showCancelarModal, setShowCancelarModal] = useState(false);

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('pt-BR');
    };

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
        router.post(`/receitas/${receita.id}/copiar`);
        setShowCopiarModal(false);
    };

    const handleCancelar = () => {
        router.delete(`/receitas/${receita.id}`);
        setShowCancelarModal(false);
    };

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        }).format(value || 0);
    };

    return (
        <DashboardLayout>
            <div className="p-6">
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
                        {/* Produtos da Receita */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Produtos da Receita</h2>
                            
                            <div className="space-y-3">
                                {receita.itens?.map((item, index) => (
                                    <div key={index} className={`flex items-start justify-between p-4 rounded-lg ${item.imprimir ? 'bg-gray-50' : 'bg-gray-100 opacity-60'}`}>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className={`font-medium ${item.imprimir ? 'text-gray-900' : 'text-gray-500'}`}>
                                                    {item.produto?.codigo && (
                                                        <span className={item.imprimir ? 'text-emerald-600' : 'text-gray-400'}>{item.produto.codigo}</span>
                                                    )}
                                                    {' '}{item.produto?.nome || 'Produto'}
                                                </span>
                                                {item.imprimir ? (
                                                    <span className="px-2 py-0.5 text-xs bg-emerald-100 text-emerald-700 rounded">Incluído</span>
                                                ) : (
                                                    <span className="px-2 py-0.5 text-xs bg-gray-200 text-gray-500 rounded">Não incluído</span>
                                                )}
                                            </div>
                                            {item.local_uso && (
                                                <div className="text-sm text-gray-500 mt-1">
                                                    Tipo: {item.local_uso}
                                                </div>
                                            )}
                                            {item.ultima_aquisicao && (
                                                <div className="text-sm text-gray-500 mt-1 flex items-center gap-1">
                                                    <span>Última aquisição: {formatDate(item.ultima_aquisicao)}</span>
                                                    {item.datas_aquisicao?.length > 1 && (
                                                        <Tippy
                                                            content={
                                                                <div className="text-xs">
                                                                    <div className="font-medium mb-1">Histórico de aquisições:</div>
                                                                    {item.datas_aquisicao.map((data, idx) => (
                                                                        <div key={idx}>{formatDate(data)}</div>
                                                                    ))}
                                                                </div>
                                                            }
                                                            placement="top"
                                                        >
                                                            <button type="button" className="text-emerald-600 hover:text-emerald-700">
                                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </button>
                                                        </Tippy>
                                                    )}
                                                </div>
                                            )}
                                            {item.anotacoes && (
                                                <div className="text-sm text-gray-600 mt-1 italic">
                                                    {item.anotacoes}
                                                </div>
                                            )}
                                        </div>
                                        {!isMedico && (
                                            <div className="text-right">
                                                {item.imprimir ? (
                                                    <>
                                                        <div className="text-sm text-gray-500">
                                                            {item.quantidade}x {formatCurrency(item.valor_unitario)}
                                                        </div>
                                                        <div className="font-semibold text-gray-900">
                                                            {formatCurrency(item.valor_total)}
                                                        </div>
                                                    </>
                                                ) : (
                                                    <div className="text-sm text-gray-400">-</div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                        </div>

                        {/* Valores e Anotações - Card separado */}
                        {!isMedico && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h2 className="text-lg font-semibold text-gray-900 mb-4">Detalhes Financeiros</h2>
                                
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
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
                                            {receita.desconto_motivo && (
                                                <p className="text-xs text-gray-500 mt-0.5">{receita.desconto_motivo}</p>
                                            )}
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

                                {(receita.anotacoes || receita.anotacoes_paciente) && (
                                    <div className="mt-4 pt-4 border-t border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {receita.anotacoes && (
                                            <div>
                                                <label className="text-sm text-gray-500">Anotações Internas</label>
                                                <p className="text-gray-900 whitespace-pre-line mt-1">{receita.anotacoes}</p>
                                            </div>
                                        )}
                                        {receita.anotacoes_paciente && (
                                            <div>
                                                <label className="text-sm text-gray-500">Anotações para o Paciente</label>
                                                <p className="text-gray-900 whitespace-pre-line mt-1">{receita.anotacoes_paciente}</p>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

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
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="text-sm font-semibold text-gray-700">Paciente</h3>
                                <Link
                                    href={`/pacientes/${receita.paciente?.id}`}
                                    className="text-emerald-600 hover:text-emerald-700 text-xs font-medium"
                                >
                                    Ver perfil →
                                </Link>
                            </div>
                            <div className="space-y-2">
                                <p className="font-semibold text-gray-900">{receita.paciente?.nome}</p>
                                {receita.paciente?.cpf && (
                                    <p className="text-sm text-gray-600">
                                        <span className="text-gray-500">CPF:</span> {receita.paciente.cpf}
                                    </p>
                                )}
                                {receita.paciente?.telefone1 && (
                                    <p className="text-sm text-gray-600">
                                        <span className="text-gray-500">Tel:</span> {receita.paciente.telefone1}
                                    </p>
                                )}
                                {receita.paciente?.email1 && (
                                    <p className="text-sm text-gray-600 break-all">
                                        <span className="text-gray-500">Email:</span> {receita.paciente.email1}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Informações da Receita */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-3">Informações</h3>
                            <div className="space-y-3">
                                <div>
                                    <label className="text-xs text-gray-500">Data da Receita</label>
                                    <p className="font-medium text-gray-900">
                                        {new Date(receita.data_receita).toLocaleDateString('pt-BR')}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-xs text-gray-500">Médico</label>
                                    <p className="font-medium text-gray-900">
                                        {receita.medico?.nome || '-'}
                                    </p>
                                    {receita.medico?.crm && (
                                        <p className="text-xs text-gray-500">
                                            CRM: {receita.medico.crm}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Ações */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-3">Ações</h3>
                            
                            <div className="space-y-2">
                                {receita.status === 'finalizada' && (
                                    <a
                                        href={`/receitas/${receita.id}/pdf`}
                                        target="_blank"
                                        className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Download PDF
                                    </a>
                                )}

                                {receita.status === 'rascunho' && (
                                    <Link
                                        href={`/receitas/${receita.id}/edit`}
                                        className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Editar Receita
                                    </Link>
                                )}

                                <button
                                    onClick={() => setShowCopiarModal(true)}
                                    className="w-full flex items-center justify-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    Copiar Receita
                                </button>

                                {receita.status !== 'cancelada' && (
                                    <button
                                        onClick={() => setShowCancelarModal(true)}
                                        className="w-full flex items-center justify-center gap-2 px-3 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50 transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Cancelar Receita
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Outras Receitas - Formato simples */}
                        {receitasAnteriores.length > 0 && (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                                <h3 className="text-sm font-semibold text-gray-700 mb-3">Outras Receitas do Paciente</h3>
                                <div className="space-y-2 max-h-64 overflow-y-auto">
                                    {receitasAnteriores.map((r) => (
                                        <Link
                                            key={r.id}
                                            href={`/receitas/${r.id}`}
                                            className="block p-2 hover:bg-gray-50 rounded-lg transition-colors border border-gray-100"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <span className="text-sm font-medium text-gray-900">
                                                        #{r.numero || r.id.toString().padStart(5, '0')}
                                                    </span>
                                                    <span className={`ml-2 px-1.5 py-0.5 text-xs rounded ${
                                                        r.status === 'finalizada' ? 'bg-green-100 text-green-700' :
                                                        r.status === 'cancelada' ? 'bg-red-100 text-red-700' :
                                                        'bg-gray-100 text-gray-600'
                                                    }`}>
                                                        {r.status === 'finalizada' ? 'Finalizada' : 
                                                         r.status === 'cancelada' ? 'Cancelada' : 'Rascunho'}
                                                    </span>
                                                </div>
                                                <span className="text-xs text-gray-500">
                                                    {formatDate(r.data_receita)}
                                                </span>
                                            </div>
                                            {!isMedico && r.valor_total > 0 && (
                                                <div className="text-xs text-gray-500 mt-1">
                                                    {formatCurrency(r.valor_total)}
                                                </div>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal Copiar */}
            {showCopiarModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        <div className="fixed inset-0 bg-black/50" onClick={() => setShowCopiarModal(false)} />
                        <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900">Copiar Receita</h3>
                            </div>
                            <p className="text-gray-600 mb-6">Deseja criar uma cópia desta receita? Será criado um novo rascunho com os mesmos produtos.</p>
                            <div className="flex justify-end gap-3">
                                <button onClick={() => setShowCopiarModal(false)} className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                                    Cancelar
                                </button>
                                <button onClick={handleCopiar} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Cancelar */}
            {showCancelarModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        <div className="fixed inset-0 bg-black/50" onClick={() => setShowCancelarModal(false)} />
                        <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                    <svg className="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900">Cancelar Receita</h3>
                            </div>
                            <p className="text-gray-600 mb-6">Deseja cancelar esta receita? Esta ação não pode ser desfeita.</p>
                            <div className="flex justify-end gap-3">
                                <button onClick={() => setShowCancelarModal(false)} className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                                    Voltar
                                </button>
                                <button onClick={handleCancelar} className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2">
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Cancelar Receita
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
