import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';
import Checkbox from '@/Components/Form/Checkbox';
import Pagination from '@/Components/Pagination';

export default function ProdutosIndex({ produtos, totalGeral, filters, lastSync }) {
    const { auth, flash } = usePage().props;
    const isAdmin = auth.user.role === 'admin';
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [bulkDrawerOpen, setBulkDrawerOpen] = useState(false);
    const [editingProduto, setEditingProduto] = useState(null);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(
        filters?.pendentes ? 'pendentes' : (filters?.ativo === undefined ? 'all' : String(filters.ativo))
    );
    const [syncing, setSyncing] = useState(false);
    const [importing, setImporting] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [acoesDrawerOpen, setAcoesDrawerOpen] = useState(false);
    const [expandedExportar, setExpandedExportar] = useState(false);
    const [expandedImportar, setExpandedImportar] = useState(false);
    const [exportFormat, setExportFormat] = useState('xlsx');
    const [exportStatus, setExportStatus] = useState('all');
    const [exportPendentes, setExportPendentes] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        nome: '',
        descricao: '',
        anotacoes: '',
        anotacoes_internas: '',
        modo_uso: '',
        ativo: true,
    });

    const openEditDrawer = (produto) => {
        setEditingProduto(produto);
        setData({
            nome: produto.nome || '',
            descricao: produto.descricao || '',
            anotacoes: produto.anotacoes || '',
            anotacoes_internas: produto.anotacoes_internas || '',
            modo_uso: produto.modo_uso || '',
            ativo: produto.ativo ?? true,
        });
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingProduto(null);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingProduto) {
            put(`/produtos/${editingProduto.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Produto atualizado!', type: 'success' }); },
            });
        }
    };

    const buildQuery = (extra = {}) => {
        const q = {};
        if (search?.trim()) q.search = search.trim();
        if (statusFilter === 'pendentes') q.pendentes = '1';
        else if (statusFilter !== 'all') q.ativo = statusFilter;
        return { ...q, ...extra };
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/produtos', buildQuery(), { preserveState: true });
    };

    const handleExport = (format, statusOverride, somentePendentes) => {
        const q = {};
        if (search?.trim()) q.search = search.trim();
        if (statusOverride !== 'all' && statusOverride !== undefined) q.ativo = statusOverride;
        if (somentePendentes) q.pendentes = '1';
        const params = new URLSearchParams(q);
        params.set('format', format);
        window.open(`/produtos/export?${params.toString()}`, '_blank');
    };

    const handleDownloadTemplate = (format) => {
        window.open(`/produtos/template?format=${format}`, '_blank');
    };

    const handleBulkImportPreview = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setImporting(true);
        const formData = new FormData();
        formData.append('arquivo', file);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
        router.post('/produtos/importar-edicoes/preview', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setExpandedImportar(true),
            onError: (errors) => {
                setToast({ message: errors?.arquivo?.[0] || 'Erro ao fazer upload.', type: 'error' });
            },
            onFinish: () => setImporting(false),
        });
        e.target.value = '';
    };

    const handleConfirmImport = () => {
        setConfirming(true);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        router.post('/produtos/importar-edicoes/executar', { _token: csrf }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const successMsg = page.props.flash?.success || 'Edição em massa concluída!';
                const importRes = page.props.flash?.import_result;
                let msg = successMsg;
                if (importRes?.nao_encontrados?.length > 0) {
                    msg += ` ${importRes.nao_encontrados.length} não encontrado(s).`;
                }
                setToast({ message: msg, type: 'success' });
            },
            onError: (errors) => {
                setToast({ message: errors?.arquivo?.[0] || 'Erro ao executar importação.', type: 'error' });
            },
            onFinish: () => setConfirming(false),
        });
    };

    const handleSync = async () => {
        setSyncing(true);
        try {
            const response = await window.axios.post('/integracoes/tiny/sync-produtos');
            setToast({ message: response.data?.message || 'Sincronização concluída!', type: 'success' });
            if (response.data?.success) {
                router.reload();
            }
        } catch (error) {
            setToast({ message: error.response?.data?.message || 'Erro ao sincronizar.', type: 'error' });
        } finally {
            setSyncing(false);
        }
    };

    const produtosList = produtos?.data || produtos || [];

    return (
        <DashboardLayout>
            <Head title="Produtos" />
            <div className="py-4 lg:py-6 px-0">
                <PageHeader
                    title="Produtos"
                    subtitle={
                        <>
                            {(produtos?.total != null || totalGeral != null) && (
                                <p className="font-medium text-gray-800">
                                    {produtos?.total ?? 0} / {totalGeral ?? 0} cadastrados
                                </p>
                            )}
                            {lastSync && (
                                <p className="text-xs text-gray-500">
                                    Última sincronização com Tiny: {new Date(lastSync).toLocaleString('pt-BR')}
                                </p>
                            )}
                        </>
                    }
                    actions={
                        isAdmin ? (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setAcoesDrawerOpen(true)}
                                    className="w-full sm:w-auto justify-center min-h-[44px] flex items-center gap-2 px-4 py-2 bg-gray-50 border border-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-100 transition-colors text-sm shadow-sm"
                                >
                                    + Ações
                                </button>
                                <button
                                    type="button"
                                    onClick={handleSync}
                                    disabled={syncing}
                                    className="w-full sm:w-auto justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-60 flex items-center gap-2"
                                >
                                    <svg className={`w-5 h-5 ${syncing ? 'animate-spin' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    {syncing ? 'Sincronizando...' : 'Sincronizar Produtos'}
                                </button>
                            </>
                        ) : null
                    }
                />

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                        <input
                            type="text"
                            placeholder="Buscar por código ou nome..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full min-w-0 flex-1 sm:min-w-[200px] px-4 py-2.5 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        />
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="w-full sm:w-auto min-h-[44px] px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Todos</option>
                            <option value="1">Ativos</option>
                            <option value="0">Inativos</option>
                            <option value="pendentes">Pendentes</option>
                        </select>
                        <button
                            type="submit"
                            className="w-full sm:w-auto min-h-[44px] px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                        >
                            Buscar
                        </button>
                    </form>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <ResponsiveEntityList
                        desktop={
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidade</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {produtosList.length > 0 ? (
                                        produtosList.map((produto) => (
                                            <tr key={produto.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 text-sm font-mono text-gray-900">{produto.codigo}</td>
                                                <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                                    {produto.nome}
                                                    {!produto.descricao && !produto.modo_uso && (
                                                        <span className="ml-2 px-1.5 py-0.5 text-[10px] font-medium rounded bg-amber-100 text-amber-700">Pendente</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500">{produto.unidade || '-'}</td>
                                                <td className="px-6 py-4 text-sm text-gray-500">
                                                    {produto.preco ? `R$ ${parseFloat(produto.preco).toFixed(2)}` : '-'}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className={`px-2 py-1 text-xs rounded-full ${produto.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                        {produto.ativo ? 'Ativo' : 'Inativo'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditDrawer(produto)}
                                                        className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                        title="Editar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                                Nenhum produto encontrado
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        }
                        mobile={
                            <div className="divide-y divide-gray-200">
                                {produtosList.length > 0 ? (
                                    produtosList.map((produto) => (
                                        <div key={produto.id} className="p-4">
                                            <div className="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                                <button
                                                    type="button"
                                                    className="w-full text-left p-3 min-h-[44px] hover:bg-gray-50 transition-colors"
                                                    onClick={() => openEditDrawer(produto)}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-xs font-mono text-gray-500">{produto.codigo}</p>
                                                            <p className="font-medium text-gray-900 break-words mt-0.5">{produto.nome}</p>
                                                            {!produto.descricao && !produto.modo_uso && (
                                                                <span className="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-amber-100 text-amber-700">Pendente</span>
                                                            )}
                                                            <p className="text-sm text-gray-600 mt-2">
                                                                {produto.unidade || '—'} · {produto.preco ? `R$ ${parseFloat(produto.preco).toFixed(2)}` : '—'}
                                                            </p>
                                                        </div>
                                                        <span className={`flex-shrink-0 px-2 py-1 text-xs rounded-full ${produto.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                            {produto.ativo ? 'Ativo' : 'Inativo'}
                                                        </span>
                                                    </div>
                                                </button>
                                                <div className="flex flex-wrap items-center justify-end gap-1 px-2 py-2 border-t border-gray-100 bg-gray-50/60">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditDrawer(produto)}
                                                        className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                        aria-label="Editar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="px-4 py-12 text-center text-gray-500">Nenhum produto encontrado</div>
                                )}
                            </div>
                        }
                    />
                    {produtos?.links && (
                        <Pagination links={produtos.links} preserveScroll />
                    )}
                </div>
            </div>

            <Drawer isOpen={drawerOpen} onClose={closeDrawer} title="Editar Produto" size="lg">
                <form onSubmit={handleSubmit} className="flex flex-col h-full">
                    <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                        <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                            <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Dados do Tiny ERP (somente leitura)</h3>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <span className="block text-xs text-gray-500">Código</span>
                                    <span className="text-sm font-mono text-gray-900">{editingProduto?.codigo || '-'}</span>
                                </div>
                                <div>
                                    <span className="block text-xs text-gray-500">Unidade</span>
                                    <span className="text-sm text-gray-900">{editingProduto?.unidade || '-'}</span>
                                </div>
                                <div>
                                    <span className="block text-xs text-gray-500">Preço Venda</span>
                                    <span className="text-sm text-gray-900">
                                        {editingProduto?.preco ? `R$ ${parseFloat(editingProduto.preco).toFixed(2)}` : '-'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="border-t pt-6">
                            <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Dados ClinicaWeb (editáveis)</h3>
                            <Input label="Nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} error={errors.nome} />
                            <div className="mt-4">
                                <Input label="Descrição / Fórmula" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)} multiline rows={3} placeholder="Fórmula ou descrição detalhada do produto..." />
                            </div>
                            <div className="mt-4">
                                <Input label="Modo de Uso" value={data.modo_uso} onChange={(e) => setData('modo_uso', e.target.value)} multiline rows={3} placeholder="Ex: Aplicar à noite após limpeza" />
                            </div>
                            <div className="mt-4">
                                <Input label="Anotações dos Especialistas" value={data.anotacoes} onChange={(e) => setData('anotacoes', e.target.value)} multiline rows={3} placeholder="Dicas sobre o creme: para quem é indicado, outras formas de uso..." />
                            </div>
                            {isAdmin && (
                                <div className="mt-4">
                                    <Input label="Anotações Internas" value={data.anotacoes_internas} onChange={(e) => setData('anotacoes_internas', e.target.value)} multiline rows={3} placeholder="Notas internas da equipe (não exibidas no catálogo)..." />
                                </div>
                            )}
                        </div>

                        <Select label="Status" value={data.ativo ? '1' : '0'} onChange={(e) => setData('ativo', e.target.value === '1')} options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />
                    </div>
                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-end gap-3">
                            <button type="button" onClick={closeDrawer} className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                            <button type="submit" disabled={processing} className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">{processing ? 'Salvando...' : 'Salvar'}</button>
                        </div>
                    </div>
                </form>
            </Drawer>

            <Drawer isOpen={acoesDrawerOpen} onClose={() => setAcoesDrawerOpen(false)} title="Outras ações" width="w-full max-w-[100vw] sm:max-w-[500px] sm:w-[500px]">
                <div className="p-6 space-y-2">
                    {(() => {
                        const importRes = flash?.import_result;
                        const importPreview = flash?.import_preview;
                        return (
                            <>
                                {/* Exportar */}
                                <div className="border border-gray-200 rounded-lg overflow-hidden">
                                    <button
                                        type="button"
                                        onClick={() => setExpandedExportar(!expandedExportar)}
                                        className="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left"
                                    >
                                        <span className="flex items-center gap-2 font-medium text-gray-900">
                                            <svg className="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Exportar produtos
                                        </span>
                                        <svg className={`w-5 h-5 text-gray-400 transition-transform ${expandedExportar ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    {expandedExportar && (
                                        <div className="p-4 border-t border-gray-200 space-y-4">
                                            <Select label="Formato" value={exportFormat} onChange={(e) => setExportFormat(e.target.value)} options={[{ value: 'xlsx', label: 'Excel' }, { value: 'csv', label: 'CSV' }]} />
                                            <Select label="Status" value={exportStatus} onChange={(e) => setExportStatus(e.target.value)} options={[{ value: 'all', label: 'Todos' }, { value: '1', label: 'Ativos' }, { value: '0', label: 'Inativos' }]} />
                                            <Checkbox label="Somente pendentes (sem descrição e modo de uso)" checked={exportPendentes} onChange={(e) => setExportPendentes(e.target.checked)} />
                                            <button type="button" onClick={() => handleExport(exportFormat, exportStatus, exportPendentes)} className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium">
                                                Exportar
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {/* Importar */}
                                <div className="border border-gray-200 rounded-lg overflow-hidden">
                                    <button
                                        type="button"
                                        onClick={() => setExpandedImportar(!expandedImportar)}
                                        className="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left"
                                    >
                                        <span className="flex items-center gap-2 font-medium text-gray-900">
                                            <svg className="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                            Importar alterações em massa
                                        </span>
                                        <svg className={`w-5 h-5 text-gray-400 transition-transform ${expandedImportar ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    {expandedImportar && (
                                        <div className="p-4 border-t border-gray-200 space-y-4">
                                            <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
                                                <p className="font-medium mb-2">1. Baixe o modelo abaixo (contém apenas as colunas editáveis).</p>
                                                <p className="mb-2">2. Edite no Excel: preencha as colunas que deseja alterar. O código identifica cada produto.</p>
                                                <p className="mb-2">3. Faça o upload do arquivo editado.</p>
                                                <p className="text-xs">Importante: preço, unidade e outros dados vêm do Tiny ERP e não podem ser alterados aqui. Formatos: CSV ou XLSX. Máx. 2MB.</p>
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-gray-700 mb-2">Baixar modelo</p>
                                                <div className="flex gap-2">
                                                    <button type="button" onClick={() => handleDownloadTemplate('xlsx')} className="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Excel</button>
                                                    <button type="button" onClick={() => handleDownloadTemplate('csv')} className="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">CSV</button>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">Enviar arquivo para atualizar</label>
                                                <input
                                                    type="file"
                                                    accept=".csv,.txt,.xlsx,.xls"
                                                    onChange={handleBulkImportPreview}
                                                    disabled={importing}
                                                    className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                                                />
                                                {importing && <p className="mt-2 text-sm text-gray-500">Analisando arquivo...</p>}
                                            </div>
                                            {importPreview && (
                                                <div className={`border rounded-lg overflow-hidden ${importPreview.columns_ok ? 'border-gray-200 bg-gray-50' : 'border-red-200 bg-red-50'}`}>
                                                    <div className="px-3 py-2 font-medium text-sm">
                                                        {importPreview.columns_ok ? (
                                                            <span className="text-green-800">Colunas OK</span>
                                                        ) : (
                                                            <span className="text-red-800">Faltam colunas obrigatórias</span>
                                                        )}
                                                    </div>
                                                    {importPreview.missing_columns?.length > 0 && (
                                                        <div className="px-3 py-2 text-sm text-red-700">
                                                            Faltam: {importPreview.missing_columns.map((c) => (c === 'codigo' ? 'código' : c)).join(', ')}
                                                        </div>
                                                    )}
                                                    {importPreview.columns_ok && (
                                                        <>
                                                            <div className="px-3 py-2 text-sm text-gray-700 space-y-1">
                                                                <p>Linhas no arquivo: {importPreview.total_rows}</p>
                                                                <p>Alterações a aplicar: <strong>{importPreview.alteracoes_count}</strong></p>
                                                                {importPreview.nao_encontrados_count > 0 && (
                                                                    <p className="text-amber-700">
                                                                        Não encontrados na base: {importPreview.nao_encontrados_count}
                                                                        {importPreview.nao_encontrados?.length > 0 && (
                                                                            <span className="block text-xs mt-1">Ex: {importPreview.nao_encontrados.slice(0, 5).join(', ')}{importPreview.nao_encontrados.length > 5 ? '...' : ''}</span>
                                                                        )}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            {importPreview.can_confirm && (
                                                                <div className="px-3 pb-3">
                                                                    <button
                                                                        type="button"
                                                                        onClick={handleConfirmImport}
                                                                        disabled={confirming || importPreview.alteracoes_count === 0}
                                                                        className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 font-medium"
                                                                    >
                                                                        {confirming ? 'Executando...' : 'Confirmar e aplicar alterações'}
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            )}
                                            {importRes?.log && importRes.log.length > 0 && (
                                                <div className="mt-4 border border-gray-200 rounded-lg overflow-hidden">
                                                    <p className="px-3 py-2 bg-gray-50 font-medium text-sm text-gray-700">
                                                        {importRes.atualizados} atualizado(s), {importRes.nao_encontrados?.length || 0} não encontrado(s)
                                                    </p>
                                                    <div className="max-h-48 overflow-y-auto divide-y divide-gray-100">
                                                        {importRes.log.map((item, idx) => (
                                                            <div key={idx} className={`px-3 py-2 text-sm flex items-start gap-2 ${item.tipo === 'sucesso' ? 'text-green-700 bg-green-50' : item.tipo === 'nao_encontrado' ? 'text-amber-700 bg-amber-50' : 'text-red-700 bg-red-50'}`}>
                                                                {item.tipo === 'sucesso' && <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>}
                                                                {item.tipo === 'nao_encontrado' && <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>}
                                                                {item.tipo === 'erro' && <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>}
                                                                <span>{item.mensagem}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </>
                        );
                    })()}
                </div>
            </Drawer>

            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
