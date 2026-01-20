import { useState } from 'react';
import { router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';

export default function RegrasCondicionais({
    regras = [],
    tabelasKarnaugh = [],
    produtos = [],
    camposDisponiveis = {},
    operadores = {},
    tiposAcao = {},
    tiposRegra = {},
    opcoesValores = {},
    filtroAtual = 'todas',
}) {
    const [showDrawer, setShowDrawer] = useState(false);
    const [editingRegra, setEditingRegra] = useState(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [deletingId, setDeletingId] = useState(null);
    const [filtro, setFiltro] = useState(filtroAtual);

    const emptyForm = {
        nome: '',
        descricao: '',
        tipo: 'selecao_tabela',
        tabela_karnaugh_id: '',
        ativo: true,
        condicoes: [{ campo: 'gravidez', operador: 'igual', valor: '' }],
        acoes: [{ tipo_acao: 'usar_tabela', tabela_karnaugh_id: '', produto_id: '', marcar: true }],
    };

    const [form, setForm] = useState(emptyForm);

    // Ações disponíveis baseadas no tipo de regra
    const acoesDisponiveis = form.tipo === 'selecao_tabela'
        ? { usar_tabela: 'Usar Tabela Karnaugh' }
        : { adicionar_item: 'Adicionar Item', remover_item: 'Remover Item' };

    // Aplicar filtro
    const handleFiltroChange = (novoFiltro) => {
        setFiltro(novoFiltro);
        router.get('/assistente/regras', { filtro: novoFiltro }, { preserveState: true });
    };

    const openCreate = () => {
        setEditingRegra(null);
        setForm(emptyForm);
        setError('');
        setShowDrawer(true);
    };

    const openEdit = (regra) => {
        setEditingRegra(regra);
        setForm({
            nome: regra.nome,
            descricao: regra.descricao || '',
            tipo: regra.tipo || 'selecao_tabela',
            tabela_karnaugh_id: regra.tabela_karnaugh_id || '',
            ativo: regra.ativo,
            condicoes: regra.condicoes.map(c => ({
                campo: c.campo,
                operador: c.operador,
                valor: c.valor || '',
            })),
            acoes: regra.acoes.map(a => ({
                tipo_acao: a.tipo_acao,
                tabela_karnaugh_id: a.tabela_karnaugh_id || '',
                produto_id: a.produto_id || '',
                marcar: a.marcar,
            })),
        });
        setError('');
        setShowDrawer(true);
    };

    const addCondicao = () => {
        setForm(prev => ({
            ...prev,
            condicoes: [...prev.condicoes, { campo: 'gravidez', operador: 'igual', valor: '' }],
        }));
    };

    const removeCondicao = (index) => {
        if (form.condicoes.length > 1) {
            setForm(prev => ({
                ...prev,
                condicoes: prev.condicoes.filter((_, i) => i !== index),
            }));
        }
    };

    const updateCondicao = (index, field, value) => {
        setForm(prev => ({
            ...prev,
            condicoes: prev.condicoes.map((c, i) => 
                i === index ? { ...c, [field]: value } : c
            ),
        }));
    };

    const addAcao = () => {
        const tipoAcaoPadrao = form.tipo === 'selecao_tabela' ? 'usar_tabela' : 'adicionar_item';
        setForm(prev => ({
            ...prev,
            acoes: [...prev.acoes, { tipo_acao: tipoAcaoPadrao, tabela_karnaugh_id: '', produto_id: '', marcar: true }],
        }));
    };

    // Quando mudar o tipo de regra, resetar as ações
    const handleTipoChange = (novoTipo) => {
        const tipoAcaoPadrao = novoTipo === 'selecao_tabela' ? 'usar_tabela' : 'adicionar_item';
        setForm(prev => ({
            ...prev,
            tipo: novoTipo,
            tabela_karnaugh_id: novoTipo === 'selecao_tabela' ? '' : prev.tabela_karnaugh_id,
            acoes: [{ tipo_acao: tipoAcaoPadrao, tabela_karnaugh_id: '', produto_id: '', marcar: true }],
        }));
    };

    const removeAcao = (index) => {
        if (form.acoes.length > 1) {
            setForm(prev => ({
                ...prev,
                acoes: prev.acoes.filter((_, i) => i !== index),
            }));
        }
    };

    const updateAcao = (index, field, value) => {
        setForm(prev => ({
            ...prev,
            acoes: prev.acoes.map((a, i) => 
                i === index ? { ...a, [field]: value } : a
            ),
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');

        const url = editingRegra 
            ? `/assistente/regras/${editingRegra.id}`
            : '/assistente/regras';
        
        const method = editingRegra ? 'put' : 'post';

        router[method](url, form, {
            onSuccess: () => {
                setShowDrawer(false);
                setForm(emptyForm);
            },
            onError: (errors) => {
                setError(Object.values(errors).flat().join('. '));
            },
            onFinish: () => setSaving(false),
        });
    };

    const handleDelete = (regra) => {
        router.delete(`/assistente/regras/${regra.id}`, {
            onSuccess: () => setDeletingId(null),
            onError: () => setDeletingId(null),
        });
    };

    const handleToggleAtivo = (regra) => {
        router.put(`/assistente/regras/${regra.id}`, {
            ...regra,
            ativo: !regra.ativo,
            condicoes: regra.condicoes,
            acoes: regra.acoes,
        });
    };

    // Resumo das condições para exibição
    const resumoCondicoes = (condicoes) => {
        return condicoes.map(c => {
            const campo = camposDisponiveis[c.campo] || c.campo;
            if (c.operador === 'qualquer') return `${campo}: Qualquer`;
            return `${campo} = ${c.valor}`;
        }).join(' E ');
    };

    // Resumo das ações para exibição
    const resumoAcoes = (acoes) => {
        return acoes.map(a => {
            if (a.tipo_acao === 'usar_tabela') {
                return `Usar tabela: ${a.tabela_karnaugh?.nome || 'N/A'}`;
            }
            if (a.tipo_acao === 'adicionar_item') {
                return `Adicionar: ${a.produto?.nome || 'N/A'}${a.marcar ? ' (marcado)' : ''}`;
            }
            if (a.tipo_acao === 'remover_item') {
                return `Remover: ${a.produto?.nome || 'N/A'}`;
            }
            return a.tipo_acao;
        }).join(', ');
    };

    // Badge do tipo de regra
    const TipoBadge = ({ tipo, tabelaAlvo }) => {
        if (tipo === 'selecao_tabela') {
            return (
                <span className="px-2 py-0.5 text-xs bg-emerald-100 text-emerald-700 rounded-full">
                    Seleção de Tabela
                </span>
            );
        }
        return (
            <span className="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded-full">
                {tabelaAlvo?.nome || 'Modificação'}
            </span>
        );
    };

    return (
        <DashboardLayout title="Regras Condicionais">
            <div className="p-6">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Regras Condicionais</h1>
                        <p className="text-gray-500 mt-1">
                            Configure regras para determinar tabela Karnaugh e modificar produtos
                        </p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Nova Regra
                    </button>
                </div>

                {/* Filtros - Segmented Control */}
                <div className="mb-6">
                    <div className="inline-flex bg-gray-100 rounded-lg p-1 gap-1">
                        <button
                            onClick={() => handleFiltroChange('todas')}
                            className={`px-4 py-2 rounded-md text-sm font-medium transition-all ${
                                filtro === 'todas'
                                    ? 'bg-white shadow-sm text-gray-900'
                                    : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            Todas
                        </button>
                        <button
                            onClick={() => handleFiltroChange('selecao_tabela')}
                            className={`px-4 py-2 rounded-md text-sm font-medium transition-all ${
                                filtro === 'selecao_tabela'
                                    ? 'bg-emerald-500 shadow-sm text-white'
                                    : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            Seleção de Tabela
                        </button>
                        {tabelasKarnaugh.map((tabela) => (
                            <button
                                key={tabela.id}
                                onClick={() => handleFiltroChange(String(tabela.id))}
                                className={`px-4 py-2 rounded-md text-sm font-medium transition-all ${
                                    filtro === String(tabela.id)
                                        ? 'bg-blue-500 shadow-sm text-white'
                                        : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                {tabela.nome} {tabela.padrao && '(Padrão)'}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Lista de Regras */}
                {regras.length === 0 ? (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <svg className="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Nenhuma regra cadastrada</h3>
                        <p className="text-gray-500 mb-6">Crie regras para personalizar o comportamento do assistente</p>
                        <button
                            onClick={openCreate}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
                        >
                            Criar primeira regra
                        </button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {regras.map((regra, index) => (
                            <div
                                key={regra.id}
                                className={`bg-white rounded-xl shadow-sm border ${regra.ativo ? 'border-gray-200' : 'border-gray-200 opacity-60'} p-5`}
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start gap-4">
                                        <div className="flex items-center justify-center w-8 h-8 bg-gray-100 rounded-full text-gray-500 font-medium text-sm">
                                            {index + 1}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <h3 className="font-medium text-gray-900">{regra.nome}</h3>
                                                <TipoBadge tipo={regra.tipo} tabelaAlvo={regra.tabela_alvo} />
                                                {!regra.ativo && (
                                                    <span className="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">
                                                        Inativa
                                                    </span>
                                                )}
                                            </div>
                                            {regra.descricao && (
                                                <p className="text-sm text-gray-500 mt-1">{regra.descricao}</p>
                                            )}
                                            
                                            <div className="mt-3 space-y-2">
                                                <div className="flex items-start gap-2 text-sm">
                                                    <span className="text-gray-400 mt-0.5">SE:</span>
                                                    <span className="text-gray-700">{resumoCondicoes(regra.condicoes)}</span>
                                                </div>
                                                <div className="flex items-start gap-2 text-sm">
                                                    <span className="text-gray-400 mt-0.5">ENTÃO:</span>
                                                    <span className="text-gray-700">{resumoAcoes(regra.acoes)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={() => openEdit(regra)}
                                            className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
                                            title="Editar"
                                        >
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            onClick={() => handleToggleAtivo(regra)}
                                            className={`p-2 rounded-lg ${
                                                regra.ativo 
                                                    ? 'text-gray-400 hover:text-orange-600 hover:bg-orange-50' 
                                                    : 'text-gray-400 hover:text-green-600 hover:bg-green-50'
                                            }`}
                                            title={regra.ativo ? 'Desativar' : 'Ativar'}
                                        >
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                {regra.ativo ? (
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                ) : (
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                )}
                                            </svg>
                                        </button>
                                        {deletingId !== regra.id && (
                                            <button
                                                onClick={() => setDeletingId(regra.id)}
                                                className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                                                title="Excluir"
                                            >
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        )}
                                        {deletingId === regra.id && (
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm text-gray-600">Confirmar?</span>
                                                <button
                                                    onClick={() => handleDelete(regra)}
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
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Drawer de Criação/Edição */}
                <Drawer
                    isOpen={showDrawer}
                    onClose={() => setShowDrawer(false)}
                    title={editingRegra ? 'Editar Regra' : 'Nova Regra'}
                    width="w-[800px]"
                >
                    <form onSubmit={handleSubmit} className="p-6 space-y-6">
                                {error && (
                                    <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                                        {error}
                                    </div>
                                )}

                                {/* Tipo de Regra */}
                                <div className="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Tipo de Regra *
                                    </label>
                                    <div className="grid grid-cols-2 gap-3">
                                        <button
                                            type="button"
                                            onClick={() => handleTipoChange('selecao_tabela')}
                                            className={`p-4 rounded-lg border-2 text-left transition-colors ${
                                                form.tipo === 'selecao_tabela'
                                                    ? 'border-emerald-500 bg-emerald-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        >
                                            <div className="flex items-center gap-2 mb-1">
                                                <svg className="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                <span className="font-medium text-gray-900">Seleção de Tabela</span>
                                            </div>
                                            <p className="text-xs text-gray-500">Define qual tabela Karnaugh usar</p>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => handleTipoChange('modificacao_tabela')}
                                            className={`p-4 rounded-lg border-2 text-left transition-colors ${
                                                form.tipo === 'modificacao_tabela'
                                                    ? 'border-emerald-500 bg-emerald-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        >
                                            <div className="flex items-center gap-2 mb-1">
                                                <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                <span className="font-medium text-gray-900">Modificação de Tabela</span>
                                            </div>
                                            <p className="text-xs text-gray-500">Adiciona ou remove produtos de uma tabela</p>
                                        </button>
                                    </div>
                                </div>

                                {/* Tabela Alvo (apenas para modificação) */}
                                {form.tipo === 'modificacao_tabela' && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Tabela Alvo *
                                        </label>
                                        <select
                                            value={form.tabela_karnaugh_id}
                                            onChange={(e) => setForm(prev => ({ ...prev, tabela_karnaugh_id: e.target.value }))}
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="">Selecione a tabela...</option>
                                            {tabelasKarnaugh.map((t) => (
                                                <option key={t.id} value={t.id}>
                                                    {t.nome} {t.padrao && '(Padrão)'}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-gray-500 mt-1">
                                            As regras de modificação só serão aplicadas quando esta tabela for selecionada
                                        </p>
                                    </div>
                                )}

                                {/* Dados básicos */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Nome da Regra *
                                        </label>
                                        <input
                                            type="text"
                                            value={form.nome}
                                            onChange={(e) => setForm(prev => ({ ...prev, nome: e.target.value }))}
                                            placeholder={form.tipo === 'selecao_tabela' 
                                                ? "Ex: Gestantes - Usar tabela específica"
                                                : "Ex: Adicionar protetor solar para fototipos altos"
                                            }
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Descrição
                                        </label>
                                        <input
                                            type="text"
                                            value={form.descricao}
                                            onChange={(e) => setForm(prev => ({ ...prev, descricao: e.target.value }))}
                                            placeholder="Descrição opcional"
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        />
                                    </div>
                                </div>

                                {/* Condições */}
                                <div>
                                    <div className="flex items-center justify-between mb-3">
                                        <label className="text-sm font-medium text-gray-700">
                                            Condições (SE)
                                        </label>
                                        <button
                                            type="button"
                                            onClick={addCondicao}
                                            className="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                            </svg>
                                            Adicionar condição
                                        </button>
                                    </div>
                                    
                                    <div className="space-y-2">
                                        {form.condicoes.map((cond, index) => (
                                            <div key={index} className="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
                                                {index > 0 && (
                                                    <span className="text-xs text-gray-400 font-medium">E</span>
                                                )}
                                                <select
                                                    value={cond.campo}
                                                    onChange={(e) => updateCondicao(index, 'campo', e.target.value)}
                                                    className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                >
                                                    {Object.entries(camposDisponiveis).map(([key, label]) => (
                                                        <option key={key} value={key}>{label}</option>
                                                    ))}
                                                </select>
                                                <select
                                                    value={cond.operador}
                                                    onChange={(e) => updateCondicao(index, 'operador', e.target.value)}
                                                    className="w-36 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                >
                                                    {Object.entries(operadores).map(([key, label]) => (
                                                        <option key={key} value={key}>{label}</option>
                                                    ))}
                                                </select>
                                                {cond.operador !== 'qualquer' && (
                                                    <select
                                                        value={cond.valor}
                                                        onChange={(e) => updateCondicao(index, 'valor', e.target.value)}
                                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                    >
                                                        <option value="">Selecione...</option>
                                                        {(opcoesValores[cond.campo] || []).map((val) => (
                                                            <option key={val} value={val}>{val}</option>
                                                        ))}
                                                    </select>
                                                )}
                                                {form.condicoes.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removeCondicao(index)}
                                                        className="p-2 text-gray-400 hover:text-red-600"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Ações */}
                                <div>
                                    <div className="flex items-center justify-between mb-3">
                                        <label className="text-sm font-medium text-gray-700">
                                            Ações (ENTÃO)
                                        </label>
                                        <button
                                            type="button"
                                            onClick={addAcao}
                                            className="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                            </svg>
                                            Adicionar ação
                                        </button>
                                    </div>

                                    <div className="space-y-3">
                                        {form.acoes.map((acao, index) => (
                                            <div key={index} className="p-4 bg-blue-50 rounded-lg border border-blue-100">
                                                <div className="flex items-start gap-3">
                                                    <div className="flex-1 space-y-3">
                                                        <div className="flex items-center gap-2">
                                                            <select
                                                                value={acao.tipo_acao}
                                                                onChange={(e) => updateAcao(index, 'tipo_acao', e.target.value)}
                                                                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                            >
                                                                {Object.entries(acoesDisponiveis).map(([key, label]) => (
                                                                    <option key={key} value={key}>{label}</option>
                                                                ))}
                                                            </select>
                                                        </div>

                                                        {acao.tipo_acao === 'usar_tabela' && (
                                                            <select
                                                                value={acao.tabela_karnaugh_id}
                                                                onChange={(e) => updateAcao(index, 'tabela_karnaugh_id', e.target.value)}
                                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                            >
                                                                <option value="">Selecione a tabela...</option>
                                                                {tabelasKarnaugh.map((t) => (
                                                                    <option key={t.id} value={t.id}>
                                                                        {t.nome} {t.padrao && '(Padrão)'}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        )}

                                                        {(acao.tipo_acao === 'adicionar_item' || acao.tipo_acao === 'remover_item') && (
                                                            <>
                                                                <select
                                                                    value={acao.produto_id}
                                                                    onChange={(e) => updateAcao(index, 'produto_id', e.target.value)}
                                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                                >
                                                                    <option value="">Selecione o produto...</option>
                                                                    {produtos.map((p) => (
                                                                        <option key={p.id} value={p.id}>
                                                                            {p.codigo ? `${p.codigo} - ` : ''}{p.nome}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                                {acao.tipo_acao === 'adicionar_item' && (
                                                                    <label className="flex items-center gap-2 text-sm text-gray-700">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={acao.marcar}
                                                                            onChange={(e) => updateAcao(index, 'marcar', e.target.checked)}
                                                                            className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                                                        />
                                                                        Produto deve vir marcado
                                                                    </label>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>

                                                    {form.acoes.length > 1 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeAcao(index)}
                                                            className="p-2 text-gray-400 hover:text-red-600"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Ativo */}
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="ativo"
                                        checked={form.ativo}
                                        onChange={(e) => setForm(prev => ({ ...prev, ativo: e.target.checked }))}
                                        className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                    />
                                    <label htmlFor="ativo" className="text-sm text-gray-700">
                                        Regra ativa
                                    </label>
                                </div>

                                {/* Botões */}
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
                                        disabled={saving || !form.nome}
                                        className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2"
                                    >
                                        {saving ? (
                                            <>
                                                <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Salvando...
                                            </>
                                        ) : (
                                            'Salvar Regra'
                                        )}
                                    </button>
                                </div>
                            </form>
                </Drawer>
            </div>
        </DashboardLayout>
    );
}
