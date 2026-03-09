import { router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useState, useMemo } from 'react';

export default function TabelaKarnaughView({ tabela, produtosPorCaso, categorias }) {
    const [filtro, setFiltro] = useState('');

    // Filtrar casos por busca
    const casosFiltrados = useMemo(() => {
        const casos = Object.entries(produtosPorCaso || {});
        if (!filtro) return casos;
        
        return casos.filter(([casoClinico, produtos]) => {
            const lowerFiltro = filtro.toLowerCase();
            if (casoClinico.toLowerCase().includes(lowerFiltro)) return true;
            return produtos.some(p => 
                p.produto_codigo.toLowerCase().includes(lowerFiltro) ||
                p.categoria.toLowerCase().includes(lowerFiltro)
            );
        });
    }, [produtosPorCaso, filtro]);

    return (
        <DashboardLayout title={`Tabela: ${tabela.nome}`}>
            <div className="p-6">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-4">
                        <button
                            onClick={() => router.visit('/assistente/tabelas-karnaugh')}
                            className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold text-gray-900">{tabela.nome}</h1>
                                {tabela.padrao && (
                                    <span className="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full">
                                        Padrão
                                    </span>
                                )}
                            </div>
                            {tabela.descricao && (
                                <p className="text-gray-500 mt-1">{tabela.descricao}</p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="relative">
                            <input
                                type="text"
                                value={filtro}
                                onChange={(e) => setFiltro(e.target.value)}
                                placeholder="Buscar caso ou produto..."
                                className="pl-10 pr-4 py-2 w-64 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <svg className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                {/* Estatísticas */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <p className="text-sm text-gray-500">Total de Casos</p>
                        <p className="text-2xl font-bold text-gray-900">{Object.keys(produtosPorCaso || {}).length}</p>
                    </div>
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <p className="text-sm text-gray-500">Tipos</p>
                        <p className="text-2xl font-bold text-gray-900">{categorias?.length || 0}</p>
                    </div>
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm text-gray-500">Arquivo Original</p>
                                <p className="text-sm font-medium text-gray-900 truncate">{tabela.arquivo_original || '-'}</p>
                            </div>
                            {tabela.arquivo_original && (
                                <a
                                    href={`/assistente/tabelas-karnaugh/${tabela.id}/download`}
                                    className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                    title="Baixar arquivo original"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                            )}
                        </div>
                    </div>
                </div>

                {/* Legenda */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <div className="flex items-center gap-6 text-sm">
                        <span className="text-gray-500">Legenda:</span>
                        <div className="flex items-center gap-2">
                            <span className="w-3 h-3 bg-emerald-100 border border-emerald-300 rounded"></span>
                            <span className="text-gray-700">Primeiro Grupo (pré-selecionado)</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="w-3 h-3 bg-gray-100 border border-gray-300 rounded"></span>
                            <span className="text-gray-700">Segundo Grupo (opcional)</span>
                        </div>
                    </div>
                </div>

                {/* Tabela de Dados */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">
                                        Caso Clínico
                                    </th>
                                    {categorias?.map((cat) => (
                                        <th key={cat} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            {cat}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {casosFiltrados.map(([casoClinico, produtos]) => {
                                    // Organizar produtos por categoria, ordenando por grupo (primeiro > segundo)
                                    const produtosPorCategoria = {};
                                    produtos
                                        .sort((a, b) => {
                                            // Primeiro grupo vem antes do segundo
                                            if (a.grupo === 'primeiro' && b.grupo !== 'primeiro') return -1;
                                            if (a.grupo !== 'primeiro' && b.grupo === 'primeiro') return 1;
                                            return 0;
                                        })
                                        .forEach(p => {
                                            if (!produtosPorCategoria[p.categoria]) {
                                                produtosPorCategoria[p.categoria] = [];
                                            }
                                            produtosPorCategoria[p.categoria].push(p);
                                        });

                                    return (
                                        <tr key={casoClinico} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 whitespace-nowrap font-mono text-sm font-medium text-gray-900 sticky left-0 bg-white">
                                                {casoClinico}
                                            </td>
                                            {categorias?.map((cat) => {
                                                const prods = produtosPorCategoria[cat] || [];
                                                return (
                                                    <td key={cat} className="px-4 py-3 text-sm">
                                                        {prods.map((p, idx) => (
                                                            <div
                                                                key={idx}
                                                                className={`inline-block px-2 py-1 rounded text-xs mb-1 mr-1 ${
                                                                    p.grupo === 'primeiro'
                                                                        ? 'bg-emerald-100 text-emerald-800'
                                                                        : 'bg-gray-100 text-gray-700'
                                                                }`}
                                                            >
                                                                {p.produto_codigo}
                                                                {p.marcar && (
                                                                    <svg className="w-3 h-3 inline ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                                    </svg>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {casosFiltrados.length === 0 && (
                        <div className="p-12 text-center text-gray-500">
                            {filtro ? 'Nenhum resultado encontrado para a busca' : 'Nenhum dado disponível'}
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}
