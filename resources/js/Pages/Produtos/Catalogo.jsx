import { Head, router } from '@inertiajs/react';
import { useState, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function ProdutosCatalogo({ produtos = [], filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const tableRef = useRef(null);

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/catalogo-produtos', { search }, { preserveState: true });
    };

    const handleExport = (format) => {
        const params = new URLSearchParams({ search, format });
        window.open(`/catalogo-produtos/export?${params.toString()}`, '_blank');
    };

    const formatDescription = (text) => {
        if (!text) return '-';
        return text.replace(/\\n|\/n/g, '\n');
    };

    return (
        <DashboardLayout>
            <Head title="Catálogo de Produtos" />

            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Catálogo de Produtos</h1>
                        <p className="text-gray-600 mt-1">Produtos disponíveis ativos</p>
                    </div>
                </div>

                {/* Search + Export */}
                <div className="flex justify-between items-center mb-4">
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar produto..."
                            className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 w-64"
                        />
                        <button
                            type="submit"
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium"
                        >
                            Buscar
                        </button>
                    </form>
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

                {/* Table */}
                <div ref={tableRef} className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nome (Tipo)
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fórmula (Etiqueta)
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Modo de Uso
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Anotações dos Especialistas
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {produtos.length > 0 ? (
                                    produtos.map((produto) => (
                                        <tr key={produto.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                                {produto.nome}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600 font-mono">
                                                {produto.codigo}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600 whitespace-pre-line max-w-xs">
                                                {formatDescription(produto.descricao)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600 whitespace-pre-line max-w-xs">
                                                {formatDescription(produto.modo_uso)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600 max-w-xs">
                                                {produto.anotacoes || '-'}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-12 text-center text-gray-500">
                                            Nenhum produto encontrado.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-2 text-sm text-gray-500">
                    {produtos.length} produto(s) encontrado(s)
                </div>
            </div>
        </DashboardLayout>
    );
}
