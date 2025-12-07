import { Link, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function AssistenteRegras({ casos, tratamentos, produtos }) {
    const [activeTab, setActiveTab] = useState('casos');
    const [editingCaso, setEditingCaso] = useState(null);
    const [editingTratamento, setEditingTratamento] = useState(null);
    
    // Form states
    const [casoForm, setCasoForm] = useState({ nome: '', descricao: '', ativo: true });
    const [tratamentoForm, setTratamentoForm] = useState({
        caso_clinico_id: '',
        produto_id: '',
        quantidade: 1,
        posologia: '',
        ordem: 1,
    });

    const handleSaveCaso = () => {
        if (editingCaso) {
            router.put(`/assistente/casos-clinicos/${editingCaso.id}`, casoForm, {
                onSuccess: () => {
                    setEditingCaso(null);
                    setCasoForm({ nome: '', descricao: '', ativo: true });
                },
            });
        } else {
            router.post('/assistente/casos-clinicos', casoForm, {
                onSuccess: () => {
                    setCasoForm({ nome: '', descricao: '', ativo: true });
                },
            });
        }
    };

    const handleDeleteCaso = (id) => {
        if (confirm('Tem certeza que deseja excluir este caso clínico?')) {
            router.delete(`/assistente/casos-clinicos/${id}`);
        }
    };

    const editCaso = (caso) => {
        setEditingCaso(caso);
        setCasoForm({
            nome: caso.nome,
            descricao: caso.descricao || '',
            ativo: caso.ativo,
        });
    };

    return (
        <DashboardLayout>
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Regras do Assistente</h1>
                    <p className="text-gray-600 mt-1">
                        Configure os casos clínicos e tratamentos do assistente de receitas
                    </p>
                </div>

                {/* Tabs */}
                <div className="border-b border-gray-200 mb-6">
                    <nav className="flex gap-8">
                        <button
                            onClick={() => setActiveTab('casos')}
                            className={`pb-4 text-sm font-medium border-b-2 transition-colors ${
                                activeTab === 'casos'
                                    ? 'border-emerald-500 text-emerald-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            Casos Clínicos
                        </button>
                        <button
                            onClick={() => setActiveTab('tratamentos')}
                            className={`pb-4 text-sm font-medium border-b-2 transition-colors ${
                                activeTab === 'tratamentos'
                                    ? 'border-emerald-500 text-emerald-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            Tratamentos
                        </button>
                    </nav>
                </div>

                {/* Casos Clínicos */}
                {activeTab === 'casos' && (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Form */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">
                                {editingCaso ? 'Editar Caso Clínico' : 'Novo Caso Clínico'}
                            </h2>
                            
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Nome *
                                    </label>
                                    <input
                                        type="text"
                                        value={casoForm.nome}
                                        onChange={(e) => setCasoForm({ ...casoForm, nome: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        placeholder="Ex: Acne Leve"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Descrição
                                    </label>
                                    <textarea
                                        value={casoForm.descricao}
                                        onChange={(e) => setCasoForm({ ...casoForm, descricao: e.target.value })}
                                        rows={3}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>

                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={casoForm.ativo}
                                        onChange={(e) => setCasoForm({ ...casoForm, ativo: e.target.checked })}
                                        className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                    />
                                    <span className="text-sm text-gray-700">Ativo</span>
                                </label>

                                <div className="flex gap-2">
                                    {editingCaso && (
                                        <button
                                            onClick={() => {
                                                setEditingCaso(null);
                                                setCasoForm({ nome: '', descricao: '', ativo: true });
                                            }}
                                            className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                                        >
                                            Cancelar
                                        </button>
                                    )}
                                    <button
                                        onClick={handleSaveCaso}
                                        disabled={!casoForm.nome}
                                        className="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {editingCaso ? 'Atualizar' : 'Adicionar'}
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Lista */}
                        <div className="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="p-4 border-b border-gray-200">
                                <h3 className="font-semibold text-gray-900">
                                    Casos Cadastrados ({casos?.length || 0})
                                </h3>
                            </div>

                            {casos?.length > 0 ? (
                                <div className="divide-y divide-gray-200">
                                    {casos.map((caso) => (
                                        <div key={caso.id} className="p-4 hover:bg-gray-50 flex items-center justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-gray-900">{caso.nome}</span>
                                                    {!caso.ativo && (
                                                        <span className="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded">
                                                            Inativo
                                                        </span>
                                                    )}
                                                </div>
                                                {caso.descricao && (
                                                    <p className="text-sm text-gray-500 mt-1">{caso.descricao}</p>
                                                )}
                                                <p className="text-xs text-gray-400 mt-1">
                                                    {caso.tratamentos_count || 0} tratamento(s)
                                                </p>
                                            </div>
                                            <div className="flex gap-2">
                                                <button
                                                    onClick={() => editCaso(caso)}
                                                    className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                >
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    onClick={() => handleDeleteCaso(caso.id)}
                                                    className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                                                >
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="p-12 text-center text-gray-500">
                                    <p>Nenhum caso clínico cadastrado</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Tratamentos */}
                {activeTab === 'tratamentos' && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div className="flex justify-between items-center mb-6">
                            <h2 className="text-lg font-semibold text-gray-900">
                                Tratamentos por Caso Clínico
                            </h2>
                            <p className="text-sm text-gray-500">
                                Configure quais produtos são sugeridos para cada caso
                            </p>
                        </div>

                        {casos?.length > 0 ? (
                            <div className="space-y-6">
                                {casos.map((caso) => (
                                    <div key={caso.id} className="border border-gray-200 rounded-lg p-4">
                                        <div className="flex justify-between items-center mb-4">
                                            <h3 className="font-medium text-gray-900">{caso.nome}</h3>
                                            <span className="text-sm text-gray-500">
                                                {caso.tratamentos?.length || 0} produto(s)
                                            </span>
                                        </div>

                                        {caso.tratamentos?.length > 0 ? (
                                            <div className="space-y-2">
                                                {caso.tratamentos.map((tratamento, index) => (
                                                    <div key={index} className="flex items-center justify-between bg-gray-50 rounded-lg p-3">
                                                        <div>
                                                            <span className="font-medium text-gray-900">
                                                                {tratamento.produto?.nome}
                                                            </span>
                                                            {tratamento.posologia && (
                                                                <span className="text-sm text-gray-500 ml-2">
                                                                    - {tratamento.posologia}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className="text-sm text-gray-500">
                                                            Qtd: {tratamento.quantidade}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-gray-500 text-center py-4">
                                                Nenhum tratamento configurado para este caso
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-12 text-gray-500">
                                <p>Cadastre casos clínicos primeiro para configurar os tratamentos</p>
                            </div>
                        )}

                        <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 text-blue-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p className="text-sm font-medium text-blue-900">
                                        Interface avançada em desenvolvimento
                                    </p>
                                    <p className="text-sm text-blue-700 mt-1">
                                        Uma interface estilo planilha (Glide Data Grid) será implementada para facilitar
                                        a gestão das regras de tratamento, similar à Tabela de Karnaugh do sistema anterior.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

