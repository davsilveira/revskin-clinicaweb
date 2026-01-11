import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Toast from '@/Components/Toast';

export default function PacienteShow({ paciente }) {
    const [toast, setToast] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const handleDelete = () => {
        router.delete(`/pacientes/${paciente.id}`, {
            onSuccess: () => {
                setToast({ message: 'Paciente desativado com sucesso!', type: 'success' });
            },
        });
    };

    const formatDate = (date) => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('pt-BR');
    };

    return (
        <DashboardLayout>
            <Head title={`Paciente - ${paciente.nome}`} />

            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <Link
                        href="/pacientes"
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4"
                    >
                        <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para lista
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="w-16 h-16 bg-emerald-600 rounded-full flex items-center justify-center">
                                <span className="text-2xl font-bold text-white">
                                    {paciente.nome.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">{paciente.nome}</h1>
                                <div className="flex items-center gap-2 mt-1">
                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                                        paciente.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    }`}>
                                        {paciente.ativo ? 'Ativo' : 'Inativo'}
                                    </span>
                                    {paciente.codigo && (
                                        <span className="text-gray-500 text-sm">Código: {paciente.codigo}</span>
                                    )}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                href={`/pacientes/${paciente.id}/edit`}
                                className="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Editar
                            </Link>
                            {!showDeleteConfirm ? (
                                <button
                                    onClick={() => setShowDeleteConfirm(true)}
                                    className="px-4 py-2 bg-red-100 text-red-700 font-semibold rounded-lg hover:bg-red-200 transition-colors"
                                >
                                    Desativar
                                </button>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-gray-600">Confirmar?</span>
                                    <button
                                        onClick={handleDelete}
                                        className="px-3 py-1.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors"
                                    >
                                        Sim
                                    </button>
                                    <button
                                        onClick={() => setShowDeleteConfirm(false)}
                                        className="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-300 transition-colors"
                                    >
                                        Não
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Info Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    {/* Dados Pessoais */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados Pessoais</h2>
                        <dl className="space-y-3">
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Data de Nascimento</dt>
                                <dd className="text-gray-900 font-medium">{formatDate(paciente.data_nascimento)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Sexo</dt>
                                <dd className="text-gray-900 font-medium">{paciente.sexo || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Fototipo</dt>
                                <dd className="text-gray-900 font-medium">{paciente.fototipo ? `Tipo ${paciente.fototipo}` : '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">CPF</dt>
                                <dd className="text-gray-900 font-medium">{paciente.cpf || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">RG</dt>
                                <dd className="text-gray-900 font-medium">{paciente.rg || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Médico</dt>
                                <dd className="text-gray-900 font-medium">{paciente.medico?.nome || '-'}</dd>
                            </div>
                        </dl>
                    </div>

                    {/* Contato */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Contato</h2>
                        <dl className="space-y-3">
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Telefone 1</dt>
                                <dd className="text-gray-900 font-medium">{paciente.telefone1 || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Telefone 2</dt>
                                <dd className="text-gray-900 font-medium">{paciente.telefone2 || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Telefone 3</dt>
                                <dd className="text-gray-900 font-medium">{paciente.telefone3 || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">E-mail 1</dt>
                                <dd className="text-gray-900 font-medium">{paciente.email1 || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">E-mail 2</dt>
                                <dd className="text-gray-900 font-medium">{paciente.email2 || '-'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Indicado por</dt>
                                <dd className="text-gray-900 font-medium">{paciente.indicado_por || '-'}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {/* Endereço */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Endereço</h2>
                    {paciente.endereco ? (
                        <p className="text-gray-900">
                            {paciente.endereco}
                            {paciente.numero && `, ${paciente.numero}`}
                            {paciente.complemento && ` - ${paciente.complemento}`}
                            {paciente.bairro && ` - ${paciente.bairro}`}
                            {paciente.cidade && `, ${paciente.cidade}`}
                            {paciente.uf && `/${paciente.uf}`}
                            {paciente.cep && ` - CEP: ${paciente.cep}`}
                        </p>
                    ) : (
                        <p className="text-gray-500">Nenhum endereço cadastrado</p>
                    )}
                </div>

                {/* Anotações */}
                {paciente.anotacoes && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Anotações</h2>
                        <p className="text-gray-700 whitespace-pre-wrap">{paciente.anotacoes}</p>
                    </div>
                )}

                {/* Receitas */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-semibold text-gray-900">Últimas Receitas</h2>
                        <Link
                            href={`/receitas/create?paciente_id=${paciente.id}`}
                            className="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors"
                        >
                            Nova Receita
                        </Link>
                    </div>
                    {paciente.receitas && paciente.receitas.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Médico</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {paciente.receitas.map((receita) => (
                                        <tr key={receita.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm text-gray-900">{receita.numero || `#${receita.id}`}</td>
                                            <td className="px-4 py-3 text-sm text-gray-900">{formatDate(receita.data_receita)}</td>
                                            <td className="px-4 py-3 text-sm text-gray-900">{receita.medico?.nome || '-'}</td>
                                            <td className="px-4 py-3">
                                                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                    receita.status === 'finalizada' ? 'bg-green-100 text-green-800' :
                                                    receita.status === 'cancelada' ? 'bg-red-100 text-red-800' :
                                                    'bg-yellow-100 text-yellow-800'
                                                }`}>
                                                    {receita.status === 'finalizada' ? 'Finalizada' :
                                                     receita.status === 'cancelada' ? 'Cancelada' : 'Rascunho'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/receitas/${receita.id}`}
                                                    className="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                                >
                                                    Ver
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-gray-500 text-center py-8">Nenhuma receita cadastrada</p>
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










