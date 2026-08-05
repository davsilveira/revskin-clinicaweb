import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

function formatBytes(n) {
    if (!n) return '—';
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
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
    const [selected, setSelected] = useState(() => {
        const ids = (pilotHint || []).map((p) => p.clw3_id).filter(Boolean);
        return ids.length ? ids : [];
    });
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
                                Selecionar piloto (3)
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

                {report && (
                    <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                        <div className="flex items-baseline justify-between gap-3">
                            <h2 className="font-semibold text-gray-900">
                                Report {report.dry_run ? '(dry-run)' : '(aplicado)'}
                            </h2>
                            <span className="text-xs text-gray-500">{report.generated_at}</span>
                        </div>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                            {Object.entries(report.stats || {}).map(([k, v]) => (
                                <div key={k} className="rounded-lg bg-gray-50 px-3 py-2">
                                    <div className="text-xs text-gray-500">{k}</div>
                                    <div className="font-semibold tabular-nums">{v}</div>
                                </div>
                            ))}
                        </div>
                        <div>
                            <h3 className="text-sm font-medium text-gray-800 mb-2">
                                Sinais ({report.signals_count ?? (report.signals || []).length}
                                {report.signals_count > (report.signals || []).length
                                    ? ` — mostrando ${(report.signals || []).length}`
                                    : ''}
                                )
                            </h3>
                            <pre className="text-xs bg-gray-50 rounded-lg p-3 overflow-auto max-h-80">
                                {JSON.stringify(report.signals || [], null, 2)}
                            </pre>
                        </div>
                        <p className="text-xs text-gray-500">
                            Arquivo completo: {report.work_dir}/report-latest.json
                        </p>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
