import { useState } from 'react';
import { router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';

export default function TabelasKarnaugh({ tabelas = [] }) {
    const [showDrawer, setShowDrawer] = useState(false);
    const [importing, setImporting] = useState(false);
    const [importError, setImportError] = useState('');
    const [deletingId, setDeletingId] = useState(null);
    const [formData, setFormData] = useState({
        nome: '',
        descricao: '',
        padrao: false,
        arquivo: null,
    });

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setFormData(prev => ({ ...prev, arquivo: file }));
            if (!formData.nome) {
                // Auto-fill name from filename
                const nome = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
                setFormData(prev => ({ ...prev, nome }));
            }
        }
    };

    const handleImport = async (e) => {
        e.preventDefault();
        if (!formData.arquivo || !formData.nome) {
            setImportError('Nome e arquivo são obrigatórios');
            return;
        }

        setImporting(true);
        setImportError('');

        const data = new FormData();
        data.append('arquivo', formData.arquivo);
        data.append('nome', formData.nome);
        data.append('descricao', formData.descricao);
        data.append('padrao', formData.padrao ? '1' : '0');

        router.post('/assistente/tabelas-karnaugh/importar', data, {
            onSuccess: () => {
                setShowDrawer(false);
                setFormData({ nome: '', descricao: '', padrao: false, arquivo: null });
            },
            onError: (errors) => {
                setImportError(errors.arquivo || 'Erro ao importar');
            },
            onFinish: () => setImporting(false),
        });
    };

    const handleDefinirPadrao = (tabela) => {
        router.post(`/assistente/tabelas-karnaugh/${tabela.id}/padrao`);
    };

    const handleToggleAtivo = (tabela) => {
        router.post(`/assistente/tabelas-karnaugh/${tabela.id}/toggle-ativo`);
    };

    const handleExcluir = (tabela) => {
        router.delete(`/assistente/tabelas-karnaugh/${tabela.id}`, {
            onSuccess: () => setDeletingId(null),
            onError: () => setDeletingId(null),
        });
    };

    return (
        <DashboardLayout title="Tabelas Karnaugh">
            <div className="p-6">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Tabelas Karnaugh</h1>
                        <p className="text-gray-500 mt-1">
                            Gerencie as tabelas de mapeamento de produtos por caso clínico
                        </p>
                    </div>
                    <button
                        onClick={() => setShowDrawer(true)}
                        className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Importar CSV
                    </button>
                </div>

                {/* Lista de Tabelas */}
                {tabelas.length === 0 ? (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <svg className="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                        </svg>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Nenhuma tabela cadastrada</h3>
                        <p className="text-gray-500 mb-6">Importe um arquivo CSV para começar</p>
                        <button
                            onClick={() => setShowDrawer(true)}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
                        >
                            Importar primeira tabela
                        </button>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nome
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Casos
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Arquivo Original
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {tabelas.map((tabela) => (
                                    <tr key={tabela.id} className={!tabela.ativo ? 'bg-gray-50 opacity-60' : ''}>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-gray-900">{tabela.nome}</span>
                                                {tabela.padrao && (
                                                    <span className="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full">
                                                        Padrão
                                                    </span>
                                                )}
                                            </div>
                                            {tabela.descricao && (
                                                <p className="text-sm text-gray-500 mt-1">{tabela.descricao}</p>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="text-gray-900">{tabela.casos_count || 0}</span>
                                            <span className="text-gray-500 text-sm"> casos</span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                                tabela.ativo 
                                                    ? 'bg-green-100 text-green-800' 
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}>
                                                {tabela.ativo ? 'Ativa' : 'Inativa'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div className="flex items-center gap-2">
                                                <span>{tabela.arquivo_original || '-'}</span>
                                                {tabela.arquivo_original && (
                                                    <a
                                                        href={`/assistente/tabelas-karnaugh/${tabela.id}/download`}
                                                        className="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                                        title="Baixar arquivo"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                    </a>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => router.visit(`/assistente/tabelas-karnaugh/${tabela.id}`)}
                                                    className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
                                                    title="Visualizar"
                                                >
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                                {!tabela.padrao && tabela.ativo && (
                                                    <button
                                                        onClick={() => handleDefinirPadrao(tabela)}
                                                        className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                        title="Definir como padrão"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                        </svg>
                                                    </button>
                                                )}
                                                {!tabela.padrao && deletingId !== tabela.id && (
                                                    <button
                                                        onClick={() => setDeletingId(tabela.id)}
                                                        className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                                                        title="Excluir"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                )}
                                                {deletingId === tabela.id && (
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm text-gray-600">Confirmar?</span>
                                                        <button
                                                            onClick={() => handleExcluir(tabela)}
                                                            className="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700"
                                                        >
                                                            Sim
                                                        </button>
                                                        <button
                                                            onClick={() => setDeletingId(null)}
                                                            className="px-2 py-1 bg-gray-200 text-gray-700 text-xs rounded hover:bg-gray-300"
                                                        >
                                                            Não
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Drawer de Importação */}
                <Drawer
                    isOpen={showDrawer}
                    onClose={() => setShowDrawer(false)}
                    title="Importar Tabela Karnaugh"
                >
                    <form onSubmit={handleImport} className="p-6 space-y-6">
                        {importError && (
                            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                                {importError}
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Arquivo CSV *
                            </label>
                            <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-emerald-400 transition-colors">
                                <input
                                    type="file"
                                    accept=".csv,.txt"
                                    onChange={handleFileChange}
                                    className="hidden"
                                    id="csv-file"
                                />
                                <label htmlFor="csv-file" className="cursor-pointer">
                                    {formData.arquivo ? (
                                        <div className="flex items-center justify-center gap-2 text-emerald-600">
                                            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span className="font-medium">{formData.arquivo.name}</span>
                                        </div>
                                    ) : (
                                        <div className="text-gray-500">
                                            <svg className="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                            </svg>
                                            <span className="text-sm">Clique para selecionar ou arraste o arquivo</span>
                                        </div>
                                    )}
                                </label>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Nome da Tabela *
                            </label>
                            <input
                                type="text"
                                value={formData.nome}
                                onChange={(e) => setFormData(prev => ({ ...prev, nome: e.target.value }))}
                                placeholder="Ex: Tabela Principal 2025"
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Descrição (opcional)
                            </label>
                            <textarea
                                value={formData.descricao}
                                onChange={(e) => setFormData(prev => ({ ...prev, descricao: e.target.value }))}
                                rows={3}
                                placeholder="Descrição ou observações sobre esta tabela"
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="padrao"
                                checked={formData.padrao}
                                onChange={(e) => setFormData(prev => ({ ...prev, padrao: e.target.checked }))}
                                className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <label htmlFor="padrao" className="text-sm text-gray-700">
                                Definir como tabela padrão
                            </label>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                onClick={() => setShowDrawer(false)}
                                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={importing || !formData.arquivo || !formData.nome}
                                className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                {importing ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Importando...
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        Importar
                                    </>
                                )}
                            </button>
                        </div>
                    </form>
                </Drawer>
            </div>
        </DashboardLayout>
    );
}
