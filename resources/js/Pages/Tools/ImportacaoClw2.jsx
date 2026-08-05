import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

function formatBytes(n) {
    if (!n) return '—';
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

const STAT_LABELS = {
    pacientes_novos: 'Pacientes novos',
    pacientes_merge: 'Pacientes a mesclar',
    pacientes_skip: 'Pacientes ignorados',
    pacientes_needs_review: 'Pacientes p/ revisão',
    receitas_novas: 'Receitas novas',
    receitas_atualizadas: 'Receitas a atualizar',
    receitas_skip: 'Receitas ignoradas',
    receitas_conflito: 'Receitas em conflito',
    receitas_canceladas_espelhadas: 'Cancelamentos espelhados',
    itens_sem_produto: 'Itens sem produto',
    renumeracoes: 'Renumerações previstas',
};

const ACTION_STYLES = {
    novo: 'bg-emerald-100 text-emerald-800',
    nova: 'bg-emerald-100 text-emerald-800',
    merge: 'bg-sky-100 text-sky-800',
    atualizada: 'bg-amber-100 text-amber-900',
    cancelada: 'bg-orange-100 text-orange-900',
    conflito: 'bg-red-100 text-red-800',
};

function formatValue(v) {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'boolean') return v ? 'sim' : 'não';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}

export default function ImportacaoClw2({
    dumps = [],
    medicos = [],
    sqlDirectory,
    latestReport = null,
    pilotHint = [],
}) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const [sqlName, setSqlName] = useState(dumps[0]?.name || '');
    const [selected, setSelected] = useState([]);
    const [busy, setBusy] = useState(false);
    const [filter, setFilter] = useState('');

    const report = flash.import_report || latestReport;
    const preview = flash.import_preview;

    const filteredMedicos = useMemo(() => {
        const q = filter.trim().toLowerCase();
        if (!q) return medicos;
        return medicos.filter((m) => {
            const blob = `${m.nome || ''} ${m.crm || ''} ${m.cpf || ''} ${m.email1 || ''}`.toLowerCase();
            return blob.includes(q);
        });
    }, [medicos, filter]);

    const toggle = (id) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const selectPilots = () => {
        const ids = (pilotHint || []).map((p) => p.clw3_id).filter(Boolean);
        setSelected(ids);
    };

    const clearSelection = () => setSelected([]);

    const post = (url, extra = {}) => {
        if (!sqlName || selected.length === 0) {
            alert('Selecione o dump SQL e ao menos um médico.');
            return;
        }
        setBusy(true);
        router.post(
            url,
            { sql_name: sqlName, medico_ids: selected, ...extra },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            }
        );
    };

    return (
        <DashboardLayout title="Importação CLW2">
            <Head title="Ferramentas — Importação CLW2" />
            <div className="p-6 max-w-6xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Importação incremental CLW2</h1>
                    <p className="text-sm text-gray-600 mt-1">
                        Selecione médicos do CLW3 e um dump SQL já presente em disco. Sem upload — pasta:{' '}
                        <code className="text-xs bg-gray-100 px-1 rounded">{sqlDirectory}</code>
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Dump SQL</label>
                        <select
                            className="w-full rounded-lg border-gray-300"
                            value={sqlName}
                            onChange={(e) => setSqlName(e.target.value)}
                        >
                            {dumps.length === 0 && <option value="">Nenhum .sql encontrado</option>}
                            {dumps.map((d) => (
                                <option key={d.name} value={d.name}>
                                    {d.name} ({formatBytes(d.size)})
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="search"
                            placeholder="Filtrar médicos (nome, CRM, CPF)…"
                            className="flex-1 min-w-[200px] rounded-lg border-gray-300"
                            value={filter}
                            onChange={(e) => setFilter(e.target.value)}
                        />
                        {pilotHint?.length > 0 && (
                            <button
                                type="button"
                                onClick={selectPilots}
                                className="px-3 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50"
                            >
                                Selecionar piloto ({pilotHint.length})
                            </button>
                        )}
                        {selected.length > 0 && (
                            <button
                                type="button"
                                onClick={clearSelection}
                                className="px-3 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50"
                            >
                                Limpar
                            </button>
                        )}
                        <span className="text-sm text-gray-500">{selected.length} selecionado(s)</span>
                    </div>

                    <div className="max-h-72 overflow-auto border border-gray-100 rounded-lg divide-y">
                        {filteredMedicos.map((m) => (
                            <label
                                key={m.id}
                                className="flex items-start gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={selected.includes(m.id)}
                                    onChange={() => toggle(m.id)}
                                />
                                <span className="text-sm">
                                    <span className="font-medium text-gray-900">
                                        #{m.id} {m.nome || '—'}
                                    </span>
                                    <span className="block text-gray-500">
                                        CRM {m.crm || '—'}
                                        {m.uf_crm ? `/${m.uf_crm}` : ''} · CPF {m.cpf || '—'}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-wrap gap-2 pt-2">
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => post('/tools/importacao-clw2/preview')}
                            className="px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50 disabled:opacity-50"
                        >
                            1. Confirmar mapeamento
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => post('/tools/importacao-clw2/dry-run')}
                            className="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm hover:bg-amber-700 disabled:opacity-50"
                        >
                            2. Dry-run
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => {
                                if (
                                    !confirm(
                                        'Aplicar importação de verdade? Revise o dry-run antes. Esta ação altera a base.'
                                    )
                                ) {
                                    return;
                                }
                                post('/tools/importacao-clw2/apply', { confirm: true });
                            }}
                            className="px-4 py-2 rounded-lg bg-emerald-700 text-white text-sm hover:bg-emerald-800 disabled:opacity-50"
                        >
                            3. Aplicar
                        </button>
                    </div>
                    {busy && (
                        <p className="text-sm text-amber-700">
                            Processando (extração do SQL pode levar alguns minutos)…
                        </p>
                    )}
                </div>

                {preview && (
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="font-semibold text-gray-900 mb-3">Mapeamento CLW3 ↔ CLW2</h2>
                        <ul className="space-y-2 text-sm">
                            {(preview.mappings || []).map((m, i) => (
                                <li key={i} className={m.ok ? 'text-emerald-800' : 'text-red-700'}>
                                    {m.ok
                                        ? `✅ #${m.clw3_id} ${m.clw3_nome} ↔ legado #${m.legado_id} ${m.legado_nome} (${m.dump_match})`
                                        : `❌ #${m.clw3_id || '?'} ${m.clw3_nome || ''} — ${m.erro}`}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {report && <ImportReportPanel report={report} />}
            </div>
        </DashboardLayout>
    );
}

function ImportReportPanel({ report }) {
    const [tab, setTab] = useState('pacientes');
    const [listFilter, setListFilter] = useState('');
    const [actionFilter, setActionFilter] = useState('all');
    const [lists, setLists] = useState({ pacientes: [], receitas: [] });
    const [loadingList, setLoadingList] = useState(false);
    const [listError, setListError] = useState(null);
    const [activeId, setActiveId] = useState(null);
    const [detail, setDetail] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);

    const hash = report.report_hash;
    const summary = report.changes_summary || {};
    // O hash do dump é estável; generated_at muda a cada dry-run/apply e precisa
    // disparar novo fetch (senão a lista fica vazia com o cache do report antigo).
    const reportKey = `${hash || ''}:${report.generated_at || ''}`;

    useEffect(() => {
        if (!hash) return undefined;
        let cancelled = false;
        setLoadingList(true);
        setListError(null);
        setLists({ pacientes: [], receitas: [] });
        setActiveId(null);
        setDetail(null);
        fetch(`/tools/importacao-clw2/report/${hash}/changes?t=${encodeURIComponent(report.generated_at || '')}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then(async (res) => {
                if (!res.ok) throw new Error(`Falha ao carregar alterações (${res.status})`);
                return res.json();
            })
            .then((data) => {
                if (cancelled) return;
                setLists({
                    pacientes: Array.isArray(data.pacientes) ? data.pacientes : [],
                    receitas: Array.isArray(data.receitas) ? data.receitas : [],
                });
            })
            .catch((err) => {
                if (!cancelled) setListError(err.message || 'Erro ao carregar');
            })
            .finally(() => {
                if (!cancelled) setLoadingList(false);
            });
        return () => {
            cancelled = true;
        };
    }, [reportKey, hash]);

    const items = tab === 'pacientes' ? lists.pacientes : lists.receitas;

    const filteredItems = useMemo(() => {
        const q = listFilter.trim().toLowerCase();
        return items.filter((item) => {
            if (actionFilter !== 'all' && item.action !== actionFilter) return false;
            if (!q) return true;
            const blob = `${item.label || ''} ${item.subtitle || ''} ${item.legado_id || ''} ${item.clw3_id || ''}`.toLowerCase();
            return blob.includes(q);
        });
    }, [items, listFilter, actionFilter]);

    const activeIndex = filteredItems.findIndex((i) => i.id === activeId);

    const loadDetail = useCallback(
        async (id) => {
            if (!hash || !id) return;
            setActiveId(id);
            setLoadingDetail(true);
            setDetail(null);
            try {
                const res = await fetch(`/tools/importacao-clw2/report/${encodeURIComponent(hash)}/changes/${encodeURIComponent(id)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(`Falha ao carregar diff (${res.status})`);
                setDetail(await res.json());
            } catch (err) {
                setDetail({ error: err.message || 'Erro' });
            } finally {
                setLoadingDetail(false);
            }
        },
        [hash]
    );

    const goRelative = (delta) => {
        if (activeIndex < 0 || filteredItems.length === 0) return;
        const next = (activeIndex + delta + filteredItems.length) % filteredItems.length;
        loadDetail(filteredItems[next].id);
    };

    useEffect(() => {
        const onKey = (e) => {
            if (!activeId) return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                goRelative(-1);
            }
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                goRelative(1);
            }
            if (e.key === 'Escape') {
                setActiveId(null);
                setDetail(null);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeId, activeIndex, filteredItems]);

    const actionOptions = useMemo(() => {
        const set = new Set(items.map((i) => i.action).filter(Boolean));
        return Array.from(set);
    }, [items]);

    const compactStats = useMemo(() => {
        const s = report.stats || {};
        return Object.entries(s)
            .filter(([, v]) => Number(v) > 0)
            .map(([k, v]) => ({ key: k, label: STAT_LABELS[k] || k, value: v }));
    }, [report.stats]);

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="px-5 pt-5 pb-3 space-y-3 border-b border-gray-100">
                <div className="flex items-baseline justify-between gap-3">
                    <h2 className="font-semibold text-gray-900">
                        O que será {report.dry_run ? 'importado' : 'aplicado'}
                        {report.dry_run ? ' (dry-run)' : ''}
                    </h2>
                    <span className="text-xs text-gray-500">{report.generated_at}</span>
                </div>
                <div className="flex flex-wrap gap-2">
                    {compactStats.map((s) => (
                        <div key={s.key} className="rounded-lg bg-gray-50 px-3 py-1.5 text-sm">
                            <span className="text-gray-500">{s.label}: </span>
                            <span className="font-semibold tabular-nums">{s.value}</span>
                        </div>
                    ))}
                </div>
                {!hash && (
                    <p className="text-sm text-amber-700">
                        Report antigo sem lista detalhada. Rode o dry-run de novo para ver pacientes e receitas.
                    </p>
                )}
            </div>

            {hash && (
                <>
                    <div className="flex border-b border-gray-200">
                        <button
                            type="button"
                            onClick={() => {
                                setTab('pacientes');
                                setActiveId(null);
                                setDetail(null);
                                setActionFilter('all');
                                setListFilter('');
                            }}
                            className={`px-5 py-3 text-sm font-medium border-b-2 -mb-px ${
                                tab === 'pacientes'
                                    ? 'border-indigo-600 text-indigo-700'
                                    : 'border-transparent text-gray-500 hover:text-gray-800'
                            }`}
                        >
                            Pacientes ({summary.pacientes ?? lists.pacientes.length})
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setTab('receitas');
                                setActiveId(null);
                                setDetail(null);
                                setActionFilter('all');
                                setListFilter('');
                            }}
                            className={`px-5 py-3 text-sm font-medium border-b-2 -mb-px ${
                                tab === 'receitas'
                                    ? 'border-indigo-600 text-indigo-700'
                                    : 'border-transparent text-gray-500 hover:text-gray-800'
                            }`}
                        >
                            Receitas ({summary.receitas ?? lists.receitas.length})
                        </button>
                    </div>

                    <div className="grid md:grid-cols-2 min-h-[420px]">
                        <div className="border-r border-gray-100 flex flex-col min-h-[420px]">
                            <div className="p-3 flex flex-wrap gap-2 border-b border-gray-50">
                                <input
                                    type="search"
                                    placeholder="Buscar na lista…"
                                    className="flex-1 min-w-[140px] rounded-lg border-gray-300 text-sm"
                                    value={listFilter}
                                    onChange={(e) => setListFilter(e.target.value)}
                                />
                                <select
                                    className="rounded-lg border-gray-300 text-sm"
                                    value={actionFilter}
                                    onChange={(e) => setActionFilter(e.target.value)}
                                >
                                    <option value="all">Todas ações</option>
                                    {actionOptions.map((a) => (
                                        <option key={a} value={a}>
                                            {a}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex-1 overflow-auto max-h-[520px]">
                                {loadingList && (
                                    <p className="p-4 text-sm text-gray-500">Carregando lista…</p>
                                )}
                                {listError && (
                                    <p className="p-4 text-sm text-red-600">{listError}</p>
                                )}
                                {!loadingList && !listError && filteredItems.length === 0 && (
                                    <p className="p-4 text-sm text-gray-500">Nenhum item nesta aba.</p>
                                )}
                                <ul className="divide-y divide-gray-50">
                                    {filteredItems.map((item) => {
                                        const selectedRow = item.id === activeId;
                                        return (
                                            <li key={item.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => loadDetail(item.id)}
                                                    className={`w-full text-left px-4 py-3 hover:bg-gray-50 ${
                                                        selectedRow ? 'bg-indigo-50' : ''
                                                    }`}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <span className="text-sm font-medium text-gray-900 line-clamp-2">
                                                            {item.label}
                                                        </span>
                                                        <span
                                                            className={`shrink-0 text-[11px] font-medium px-2 py-0.5 rounded-full ${
                                                                ACTION_STYLES[item.action] || 'bg-gray-100 text-gray-700'
                                                            }`}
                                                        >
                                                            {item.action_label || item.action}
                                                        </span>
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-gray-500">
                                                        {item.subtitle}
                                                        {item.diff_count != null ? ` · ${item.diff_count} campo(s)` : ''}
                                                    </div>
                                                    {item.warning && (
                                                        <div className="mt-1 text-xs text-amber-700">⚠ {item.warning}</div>
                                                    )}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        </div>

                        <div className="flex flex-col min-h-[420px] bg-gray-50/40">
                            {!activeId && (
                                <div className="flex-1 flex items-center justify-center p-6 text-sm text-gray-500">
                                    Clique em um item à esquerda para ver o diff.
                                </div>
                            )}
                            {activeId && (
                                <>
                                    <div className="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-white">
                                        <button
                                            type="button"
                                            onClick={() => goRelative(-1)}
                                            disabled={filteredItems.length < 2}
                                            className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-40"
                                        >
                                            ← Anterior
                                        </button>
                                        <span className="text-xs text-gray-500 tabular-nums">
                                            {activeIndex >= 0 ? activeIndex + 1 : '—'} / {filteredItems.length}
                                            <span className="hidden sm:inline text-gray-400"> · ← →</span>
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => goRelative(1)}
                                            disabled={filteredItems.length < 2}
                                            className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-40"
                                        >
                                            Próximo →
                                        </button>
                                    </div>
                                    <div className="flex-1 overflow-auto p-4 max-h-[520px]">
                                        {loadingDetail && (
                                            <p className="text-sm text-gray-500">Carregando diff…</p>
                                        )}
                                        {!loadingDetail && detail?.error && (
                                            <p className="text-sm text-red-600">{detail.error}</p>
                                        )}
                                        {!loadingDetail && detail && !detail.error && (
                                            <DiffView change={detail} />
                                        )}
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </>
            )}

            <div className="px-5 py-3 border-t border-gray-100 text-xs text-gray-500">
                {report.signals_count > 0 && (
                    <span className="mr-3">{report.signals_count} sinal(is) técnicos no JSON do report</span>
                )}
                Arquivo: {report.work_dir}/report-latest.json
            </div>
        </div>
    );
}

function DiffView({ change }) {
    const diffs = Array.isArray(change.diff) ? change.diff : [];

    return (
        <div className="space-y-4">
            <div>
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-base font-semibold text-gray-900">{change.label}</h3>
                    <span
                        className={`text-[11px] font-medium px-2 py-0.5 rounded-full ${
                            ACTION_STYLES[change.action] || 'bg-gray-100 text-gray-700'
                        }`}
                    >
                        {change.action_label || change.action}
                    </span>
                </div>
                {change.subtitle && <p className="text-sm text-gray-500 mt-0.5">{change.subtitle}</p>}
                <p className="text-xs text-gray-400 mt-1">
                    Legado #{change.legado_id ?? '—'}
                    {change.clw3_id != null ? ` · CLW3 #${change.clw3_id}` : ''}
                </p>
                {change.warning && (
                    <p className="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        ⚠ {change.warning}
                    </p>
                )}
            </div>

            {diffs.length === 0 ? (
                <p className="text-sm text-gray-500">
                    Sem diferença de campos (ação sem alteração de dados, ou snapshot idêntico).
                </p>
            ) : (
                <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-left text-xs text-gray-500">
                            <tr>
                                <th className="px-3 py-2 font-medium">Campo</th>
                                <th className="px-3 py-2 font-medium">Atual (CLW3)</th>
                                <th className="px-3 py-2 font-medium">Depois (legado)</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {diffs.map((d) => (
                                <tr key={d.field || d.label}>
                                    <td className="px-3 py-2 align-top font-medium text-gray-800 whitespace-nowrap">
                                        {d.label || d.field}
                                        {d.op === 'add' && (
                                            <span className="ml-1 text-[10px] text-emerald-600">novo</span>
                                        )}
                                        {d.op === 'remove' && (
                                            <span className="ml-1 text-[10px] text-red-600">remove</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 align-top text-gray-500 break-words max-w-[12rem]">
                                        {formatValue(d.from)}
                                    </td>
                                    <td className="px-3 py-2 align-top text-gray-900 break-words max-w-[12rem] bg-emerald-50/50">
                                        {formatValue(d.to)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
