import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function TabelasPrecoIndex({ tabelas }) {
    const handleDelete = (id) => {
        if (confirm('Tem certeza que deseja excluir esta tabela de preço?')) {
            router.delete(`/tabelas-preco/${id}`);
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Tabelas de Preço</h1>
                        <p className="text-gray-600 mt-1">Gerencie as tabelas de preço diferenciadas</p>
                    </div>
                    <Link
                        href="/tabelas-preco/create"
                        className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Nova Tabela
                    </Link>
                </div>

                {/* Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {(tabelas?.data || tabelas || []).length > 0 ? (
                        (tabelas?.data || tabelas || []).map((tabela) => (
                            <div key={tabela.id} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div className="flex justify-between items-start mb-4">
                                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg className="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                        tabela.ativa ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    }`}>
                                        {tabela.ativa ? 'Ativa' : 'Inativa'}
                                    </span>
                                </div>

                                <h3 className="text-lg font-semibold text-gray-900 mb-2">{tabela.nome}</h3>
                                
                                {tabela.descricao && (
                                    <p className="text-sm text-gray-600 mb-4 line-clamp-2">
                                        {tabela.descricao}
                                    </p>
                                )}

                                <div className="flex items-center gap-4 text-sm text-gray-500 mb-4">
                                    <div className="flex items-center gap-1">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        {tabela.itens_count || 0} itens
                                    </div>
                                    {tabela.clinica && (
                                        <div className="flex items-center gap-1">
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            {tabela.clinica.nome}
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-2 pt-4 border-t border-gray-200">
                                    <Link
                                        href={`/tabelas-preco/${tabela.id}/edit`}
                                        className="flex-1 text-center px-3 py-2 text-sm text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(tabela.id)}
                                        className="flex-1 text-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-full text-center py-12">
                            <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <p className="text-gray-500">Nenhuma tabela de preço cadastrada</p>
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}

