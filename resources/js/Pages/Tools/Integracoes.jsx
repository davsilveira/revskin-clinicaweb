import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import debounce from 'lodash/debounce';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import Pagination from '@/Components/Pagination';
import Toast from '@/Components/Toast';

const TABS = [
    { id: 'pending', label: 'Pendentes' },
    { id: 'failed', label: 'Falhos' },
    { id: 'retry', label: 'Estado de retry' },
];

const QUEUE_LABELS = {
    'rd-sync': 'RD Station',
    'rd-webhooks': 'RD Webhooks',
    'tiny-sync': 'oList',
    'tiny-webhooks': 'oList Webhooks',
};

const DAY_OPTIONS = [
    { value: 7, label: 'Últimos 7 dias' },
    { value: 15, label: 'Últimos 15 dias' },
    { value: 30, label: 'Últimos 30 dias' },
];

const selectClassName =
    'rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 bg-white cursor-pointer';

function buildFilterParams(filters, overrides = {}) {
    const days = overrides.days ?? filters.days ?? 7;
    const queue = overrides.queue !== undefined ? overrides.queue : filters.queue;
    const job = overrides.job !== undefined ? overrides.job : filters.job;
    const paciente = overrides.paciente !== undefined ? overrides.paciente : filters.paciente;
    const tab = overrides.tab ?? filters.tab ?? 'failed';
    const pendingPage = overrides.pending_page ?? filters.pending_page ?? 1;
    const failedPage = overrides.failed_page ?? filters.failed_page ?? 1;
    const retryPage = overrides.retry_page ?? filters.retry_page ?? 1;

    const params = {
        days,
        tab,
        pending_page: pendingPage,
        failed_page: failedPage,
        retry_page: retryPage,
    };
    if (queue) params.queue = queue;
    if (job) params.job = job;
    if (paciente?.trim()) params.paciente = paciente.trim();

    return params;
}

function buildFilterParamsResettingPages(filters, overrides = {}) {
    return buildFilterParams(filters, {
        pending_page: 1,
        failed_page: 1,
        retry_page: 1,
        ...overrides,
    });
}

function EyeIcon({ className = 'w-4 h-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
            />
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
            />
        </svg>
    );
}

function ErrorDetailModal({ open, error, onClose }) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="fixed inset-0 bg-black/50 transition-opacity" onClick={onClose} />
                <div className="relative bg-white rounded-xl shadow-xl max-w-3xl w-full p-6 transform transition-all">
                    <div className="flex items-start justify-between gap-4 mb-4">
                        <h3 className="text-lg font-semibold text-gray-900">Detalhes do erro</h3>
                        <button
                            type="button"
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600 cursor-pointer"
                            aria-label="Fechar"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <pre className="whitespace-pre-wrap text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg p-4 max-h-[70vh] overflow-auto">
                        {error}
                    </pre>
                    <div className="mt-4 flex justify-end">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 cursor-pointer"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function ErrorCell({ summary, full }) {
    const [modalOpen, setModalOpen] = useState(false);

    if (!summary && !full) {
        return <span className="text-gray-400">—</span>;
    }

    return (
        <>
            <div className="flex items-start gap-2">
                <span className="text-red-700 leading-snug">{summary || full}</span>
                {full && (
                    <button
                        type="button"
                        onClick={() => setModalOpen(true)}
                        className="flex-shrink-0 p-1 text-gray-400 hover:text-gray-600 rounded cursor-pointer"
                        aria-label="Ver erro completo"
                        title="Ver erro completo"
                    >
                        <EyeIcon />
                    </button>
                )}
            </div>
            <ErrorDetailModal open={modalOpen} error={full} onClose={() => setModalOpen(false)} />
        </>
    );
}

const statusBadge = (status) => {
    const map = {
        pending: 'bg-blue-100 text-blue-800',
        running: 'bg-amber-100 text-amber-800',
        failed: 'bg-red-100 text-red-800',
        exhausted: 'bg-gray-200 text-gray-700',
        in_flight: 'bg-purple-100 text-purple-800',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('pt-BR');
    } catch {
        return value;
    }
};

const formatRetrySchedule = (row) => {
    if (row.in_flight && row.last_dispatched_at) {
        return `Enviado ${formatDate(row.last_dispatched_at)}`;
    }
    if (row.next_retry_at) {
        return formatDate(row.next_retry_at);
    }
    if (row.exhausted) {
        if (row.job_label === 'Importar pacientes') {
            return 'Cron (10 min)';
        }
        return 'Sem auto-retry';
    }
    return '—';
};

function IntegrationFilters({
    days,
    queue,
    job,
    pacienteInput,
    queues,
    jobOptions,
    onDaysChange,
    onQueueChange,
    onJobChange,
    onPacienteChange,
}) {
    return (
        <div className="flex flex-wrap items-end gap-3">
            <label className="flex flex-col gap-1">
                <span className="text-xs font-medium text-gray-500 uppercase">Período</span>
                <select value={days ?? 7} onChange={(e) => onDaysChange(Number(e.target.value))} className={selectClassName}>
                    {DAY_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>

            <label className="flex flex-col gap-1">
                <span className="text-xs font-medium text-gray-500 uppercase">Fila</span>
                <select value={queue || ''} onChange={(e) => onQueueChange(e.target.value || null)} className={selectClassName}>
                    <option value="">Todas as filas</option>
                    {queues.map((item) => (
                        <option key={item} value={item}>
                            {QUEUE_LABELS[item] || item}
                        </option>
                    ))}
                </select>
            </label>

            <label className="flex flex-col gap-1">
                <span className="text-xs font-medium text-gray-500 uppercase">Job</span>
                <select value={job || ''} onChange={(e) => onJobChange(e.target.value || null)} className={selectClassName}>
                    <option value="">Todos os jobs</option>
                    {jobOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>

            <label className="flex flex-col gap-1 min-w-[12rem] flex-1">
                <span className="text-xs font-medium text-gray-500 uppercase">Paciente</span>
                <input
                    type="search"
                    value={pacienteInput}
                    onChange={(e) => onPacienteChange(e.target.value)}
                    placeholder="Buscar por nome…"
                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 bg-white"
                />
            </label>
        </div>
    );
}

function JobActions({ row, onRetry, onForget, processingId }) {
    const busy = processingId === row.uuid;
    const [confirmingForget, setConfirmingForget] = useState(false);

    useEffect(() => {
        setConfirmingForget(false);
    }, [row.uuid]);

    useEffect(() => {
        if (busy) {
            setConfirmingForget(false);
        }
    }, [busy]);

    return (
        <div className="flex flex-col gap-2 w-28">
            <button
                type="button"
                disabled={busy}
                onClick={() => onRetry([row.uuid])}
                className="w-full px-3 py-1.5 text-xs font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer text-center"
            >
                {busy ? 'Reprocessando…' : 'Reprocessar'}
            </button>
            {confirmingForget ? (
                <div
                    className="flex w-full items-stretch rounded-lg border border-gray-300 text-xs font-medium overflow-hidden"
                    role="group"
                    aria-label="Confirmar descarte"
                >
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => {
                            setConfirmingForget(false);
                            onForget(row.uuid);
                        }}
                        className="flex-1 px-2 py-1.5 text-red-700 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer text-center"
                    >
                        Sim
                    </button>
                    <span className="flex items-center text-gray-300 select-none" aria-hidden="true">
                        |
                    </span>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => setConfirmingForget(false)}
                        className="flex-1 px-2 py-1.5 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer text-center"
                    >
                        Não
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => setConfirmingForget(true)}
                    className="w-full px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer text-center"
                >
                    Descartar
                </button>
            )}
        </div>
    );
}

function FailedTable({ rows, selected, onToggle, onToggleAll, onRetry, onForget, processingId }) {
    const allSelected = rows.length > 0 && rows.every((row) => selected.has(row.uuid));

    return (
        <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
                <tr>
                    <th className="px-4 py-3 text-left">
                        <input
                            type="checkbox"
                            checked={allSelected}
                            onChange={(e) => onToggleAll(e.target.checked)}
                            aria-label="Selecionar todos"
                        />
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fila</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Job</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Receita</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paciente</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Falhou em</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Erro</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
                {rows.length === 0 ? (
                    <tr>
                        <td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-500">
                            Nenhum job falho nas filas de integração.
                        </td>
                    </tr>
                ) : (
                    rows.map((row) => (
                        <tr key={row.uuid} className="align-top">
                            <td className="px-4 py-3">
                                <input
                                    type="checkbox"
                                    checked={selected.has(row.uuid)}
                                    onChange={() => onToggle(row.uuid)}
                                    aria-label={`Selecionar job ${row.uuid}`}
                                />
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-700">{QUEUE_LABELS[row.queue] || row.queue}</td>
                            <td className="px-4 py-3 text-sm text-gray-900">{row.job_label}</td>
                            <td className="px-4 py-3 text-sm">
                                {row.receita_id ? (
                                    <Link href={`/receitas/${row.receita_id}`} className="text-emerald-700 hover:underline">
                                        #{row.receita_numero || row.receita_id}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-700">{row.paciente_nome || '—'}</td>
                            <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{formatDate(row.failed_at)}</td>
                            <td className="px-4 py-3 text-sm text-gray-700 max-w-md">
                                <ErrorCell summary={row.error_summary} full={row.error_full} />
                            </td>
                            <td className="px-4 py-3">
                                <JobActions row={row} onRetry={onRetry} onForget={onForget} processingId={processingId} />
                            </td>
                        </tr>
                    ))
                )}
            </tbody>
        </table>
    );
}

function PendingTable({ rows }) {
    return (
        <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
                <tr>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fila</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Job</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Receita</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paciente</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tentativas</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Disponível em</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
                {rows.length === 0 ? (
                    <tr>
                        <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-500">
                            Nenhum job pendente nas filas de integração.
                        </td>
                    </tr>
                ) : (
                    rows.map((row) => (
                        <tr key={row.id}>
                            <td className="px-4 py-3 text-sm text-gray-700">{QUEUE_LABELS[row.queue] || row.queue}</td>
                            <td className="px-4 py-3 text-sm text-gray-900">{row.job_label}</td>
                            <td className="px-4 py-3 text-sm">
                                {row.receita_id ? (
                                    <Link href={`/receitas/${row.receita_id}`} className="text-emerald-700 hover:underline">
                                        #{row.receita_numero || row.receita_id}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-700">{row.paciente_nome || row.context_label || '—'}</td>
                            <td className="px-4 py-3">
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge(row.status)}`}>
                                    {row.status === 'running' ? 'Em execução' : 'Pendente'}
                                </span>
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-600">{row.attempts}</td>
                            <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{formatDate(row.available_at)}</td>
                        </tr>
                    ))
                )}
            </tbody>
        </table>
    );
}

function RetryStateTable({ rows }) {
    return (
        <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
                <tr>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Job</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Receita</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Retries rápidos</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Retry 12h</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Próximo retry</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
                {rows.length === 0 ? (
                    <tr>
                        <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">
                            Nenhum estado de retry registrado.
                        </td>
                    </tr>
                ) : (
                    rows.map((row) => (
                        <tr key={row.fingerprint}>
                            <td className="px-4 py-3 text-sm text-gray-900">{row.job_label || '—'}</td>
                            <td className="px-4 py-3 text-sm">
                                {row.receita_id ? (
                                    <Link href={`/receitas/${row.receita_id}`} className="text-emerald-700 hover:underline">
                                        #{row.receita_numero || row.receita_id}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-600">{row.fast_retries_left}</td>
                            <td className="px-4 py-3 text-sm text-gray-600">{row.delayed_retry_left}</td>
                            <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{formatRetrySchedule(row)}</td>
                            <td className="px-4 py-3">
                                {row.exhausted ? (
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge('exhausted')}`}>
                                        Esgotado
                                    </span>
                                ) : row.in_flight ? (
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge('in_flight')}`}>
                                        Reenfileirado
                                    </span>
                                ) : (
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge('pending')}`}>
                                        Aguardando
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))
                )}
            </tbody>
        </table>
    );
}

export default function IntegracoesTools({
    queues = [],
    jobOptions = [],
    filters = {},
    pending = [],
    pendingPagination = {},
    failed = [],
    failedPagination = {},
    retryStates = [],
    retryPagination = {},
}) {
    const { flash } = usePage().props;
    const [activeTab, setActiveTab] = useState(() => filters.tab || 'failed');
    const [selected, setSelected] = useState(new Set());
    const [processingId, setProcessingId] = useState(null);
    const [toast, setToast] = useState(null);
    const [pacienteInput, setPacienteInput] = useState(() => filters.paciente || '');
    const skipNextPacienteFetch = useRef(true);

    const queueFilter = filters.queue || '';
    const filtersRef = useRef(filters);
    filtersRef.current = filters;

    useEffect(() => {
        setPacienteInput((prev) => (prev === (filters.paciente || '') ? prev : filters.paciente || ''));
    }, [filters.paciente]);

    useEffect(() => {
        setActiveTab(filters.tab || 'failed');
    }, [filters.tab]);

    const navigateWithFilters = useCallback((overrides = {}, resetPages = false) => {
        const params = resetPages
            ? buildFilterParamsResettingPages(filtersRef.current, overrides)
            : buildFilterParams(filtersRef.current, overrides);

        router.get('/tools/integracoes', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: [
                'pending',
                'pendingPagination',
                'failed',
                'failedPagination',
                'retryStates',
                'retryPagination',
                'filters',
            ],
        });
    }, []);

    const runPacienteSearch = useMemo(
        () =>
            debounce((term) => {
                router.get('/tools/integracoes', buildFilterParamsResettingPages(filtersRef.current, { paciente: term }), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: [
                        'pending',
                        'pendingPagination',
                        'failed',
                        'failedPagination',
                        'retryStates',
                        'retryPagination',
                        'filters',
                    ],
                });
            }, 350),
        []
    );

    useEffect(() => () => runPacienteSearch.cancel(), [runPacienteSearch]);

    useEffect(() => {
        if (skipNextPacienteFetch.current) {
            skipNextPacienteFetch.current = false;
            return;
        }
        runPacienteSearch(pacienteInput);
    }, [pacienteInput, runPacienteSearch]);

    useEffect(() => {
        if (flash?.success) setToast({ message: flash.success, type: 'success' });
        if (flash?.error) setToast({ message: flash.error, type: 'error' });
    }, [flash]);

    useEffect(() => {
        const timer = setInterval(() => {
            router.reload({
                only: [
                    'pending',
                    'pendingPagination',
                    'failed',
                    'failedPagination',
                    'retryStates',
                    'retryPagination',
                ],
                preserveScroll: true,
            });
        }, 30000);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        setSelected(new Set());
    }, [filters.queue, filters.days, filters.job, filters.paciente]);

    const counts = useMemo(
        () => ({
            pending: pendingPagination?.total ?? pending.length,
            failed: failedPagination?.total ?? failed.length,
            retry: retryPagination?.total ?? retryStates.length,
        }),
        [pending, failed, retryStates, pendingPagination, failedPagination, retryPagination]
    );

    const activePagination =
        activeTab === 'pending' ? pendingPagination : activeTab === 'failed' ? failedPagination : retryPagination;

    const handleTabChange = (tabId) => {
        setActiveTab(tabId);
        navigateWithFilters({ tab: tabId });
    };

    const toggleSelected = (uuid) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(uuid)) next.delete(uuid);
            else next.add(uuid);
            return next;
        });
    };

    const toggleAll = (checked) => {
        setSelected(checked ? new Set(failed.map((row) => row.uuid)) : new Set());
    };

    const retryJobs = (uuids) => {
        if (!uuids.length) return;
        if (uuids.length === 1) {
            setProcessingId(uuids[0]);
            router.post(`/tools/integracoes/failed/${uuids[0]}/retry`, {}, {
                preserveScroll: true,
                onFinish: () => setProcessingId(null),
            });
            return;
        }
        router.post('/tools/integracoes/failed/retry-batch', { uuids }, { preserveScroll: true });
    };

    const forgetJob = (uuid) => {
        setProcessingId(uuid);
        router.delete(`/tools/integracoes/failed/${uuid}`, {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => setProcessingId(null),
        });
    };

    const retryAllInQueue = () => {
        if (!queueFilter) {
            setToast({ message: 'Selecione uma fila para reprocessar todos.', type: 'error' });
            return;
        }
        router.post('/tools/integracoes/failed/retry-batch', { queue: queueFilter }, { preserveScroll: true });
    };

    return (
        <DashboardLayout>
            <Head title="Ferramentas — Integrações" />

            <PageHeader
                title="Ferramentas — Integrações"
                description="Monitore e reprocesse jobs das filas RD Station e oList."
                actions={
                    activeTab === 'failed' ? (
                        <>
                            <button
                                type="button"
                                onClick={() => retryJobs([...selected])}
                                disabled={selected.size === 0}
                                className="px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                            >
                                Reprocessar selecionados ({selected.size})
                            </button>
                            <button
                                type="button"
                                onClick={retryAllInQueue}
                                className="px-4 py-2 text-sm font-medium rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 cursor-pointer"
                            >
                                Reprocessar fila
                            </button>
                        </>
                    ) : null
                }
            />

            <div className="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <IntegrationFilters
                    days={filters.days}
                    queue={filters.queue}
                    job={filters.job}
                    pacienteInput={pacienteInput}
                    queues={queues}
                    jobOptions={jobOptions}
                    onDaysChange={(days) => navigateWithFilters({ days }, true)}
                    onQueueChange={(queue) => navigateWithFilters({ queue }, true)}
                    onJobChange={(job) => navigateWithFilters({ job }, true)}
                    onPacienteChange={setPacienteInput}
                />
            </div>

            <div className="mb-4 flex flex-wrap gap-2 border-b border-gray-200">
                {TABS.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => handleTabChange(tab.id)}
                        className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px cursor-pointer ${
                            activeTab === tab.id
                                ? 'border-emerald-600 text-emerald-700'
                                : 'border-transparent text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        {tab.label} ({counts[tab.id] ?? 0})
                    </button>
                ))}
            </div>

            {activeTab === 'pending' && counts.pending === 0 && (
                <div className="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    Nenhum job pendente nos últimos {filters.days ?? 7} dias com os filtros selecionados.
                </div>
            )}

            {activeTab === 'failed' && counts.failed === 0 && (
                <div className="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    Nenhum job falho nos últimos {filters.days ?? 7} dias com os filtros selecionados.
                </div>
            )}

            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <ResponsiveEntityList
                    desktop={
                        activeTab === 'pending' ? (
                            <PendingTable rows={pending} />
                        ) : activeTab === 'failed' ? (
                            <FailedTable
                                rows={failed}
                                selected={selected}
                                onToggle={toggleSelected}
                                onToggleAll={toggleAll}
                                onRetry={retryJobs}
                                onForget={forgetJob}
                                processingId={processingId}
                            />
                        ) : (
                            <RetryStateTable rows={retryStates} />
                        )
                    }
                    mobile={
                        <div className="divide-y divide-gray-200">
                            {(activeTab === 'pending' ? pending : activeTab === 'failed' ? failed : retryStates).map((row) => (
                                <div key={row.uuid || row.id || row.fingerprint} className="p-4 space-y-2">
                                    <div className="font-medium text-gray-900">{row.job_label || 'Job'}</div>
                                    <div className="text-sm text-gray-600">{QUEUE_LABELS[row.queue] || row.queue || '—'}</div>
                                    {row.receita_id && (
                                        <Link href={`/receitas/${row.receita_id}`} className="text-sm text-emerald-700">
                                            Receita #{row.receita_numero || row.receita_id}
                                        </Link>
                                    )}
                                    {(row.error_summary || row.error_full) && (
                                        <ErrorCell summary={row.error_summary} full={row.error_full} />
                                    )}
                                    {activeTab === 'failed' && (
                                        <JobActions row={row} onRetry={retryJobs} onForget={forgetJob} processingId={processingId} />
                                    )}
                                </div>
                            ))}
                        </div>
                    }
                />
                {activePagination?.links && (
                    <Pagination links={activePagination.links} preserveScroll />
                )}
            </div>

            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
