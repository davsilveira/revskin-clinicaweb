import { Head } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Input from '@/Components/Form/Input';
import MaskedInput from '@/Components/Form/MaskedInput';
import Select from '@/Components/Form/Select';
import Toast from '@/Components/Toast';
import { validateCPF, validateCNPJ } from '@/utils/validations';

const STATES = [
    { value: 'AC', label: 'AC' },
    { value: 'AL', label: 'AL' },
    { value: 'AP', label: 'AP' },
    { value: 'AM', label: 'AM' },
    { value: 'BA', label: 'BA' },
    { value: 'CE', label: 'CE' },
    { value: 'DF', label: 'DF' },
    { value: 'ES', label: 'ES' },
    { value: 'GO', label: 'GO' },
    { value: 'MA', label: 'MA' },
    { value: 'MT', label: 'MT' },
    { value: 'MS', label: 'MS' },
    { value: 'MG', label: 'MG' },
    { value: 'PA', label: 'PA' },
    { value: 'PB', label: 'PB' },
    { value: 'PR', label: 'PR' },
    { value: 'PE', label: 'PE' },
    { value: 'PI', label: 'PI' },
    { value: 'RJ', label: 'RJ' },
    { value: 'RN', label: 'RN' },
    { value: 'RS', label: 'RS' },
    { value: 'RO', label: 'RO' },
    { value: 'RR', label: 'RR' },
    { value: 'SC', label: 'SC' },
    { value: 'SP', label: 'SP' },
    { value: 'SE', label: 'SE' },
    { value: 'TO', label: 'TO' },
];

const SERVICE_LABELS = {
    'receita-federal/cpf': 'Consulta CPF',
    'receita-federal/cnpj': 'Consulta CNPJ',
};

const statusBadgeClass = (success) =>
    success
        ? 'bg-green-100 text-green-700'
        : 'bg-red-100 text-red-700';

const firstError = (errors, field) => {
    const value = errors?.[field];
    if (Array.isArray(value)) {
        return value[0];
    }
    return value || null;
};

const formatServiceLabel = (service) => {
    if (!service) {
        return '—';
    }
    if (service.startsWith('cro/')) {
        const parts = service.split('/');
        const uf = parts[1]?.toUpperCase();
        return `Consulta CRO (${uf ?? 'UF'})`;
    }
    return SERVICE_LABELS[service] ?? service;
};

const stringifyValue = (value) => {
    if (Array.isArray(value)) {
        return value.join(', ');
    }
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    return value;
};

const PER_PAGE_OPTIONS = [
    { value: 20, label: '20 itens por página' },
    { value: 50, label: '50 itens por página' },
    { value: 100, label: '100 itens por página' },
];

export default function InfosimplesTools({ infosimples, history: initialHistory, historyFilters = {} }) {
    const initialPerPage = historyFilters?.per_page ?? initialHistory?.meta?.per_page ?? 20;
    const initialSearch = historyFilters?.search ?? initialHistory?.meta?.search ?? '';
    const initialMeta = initialHistory?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: initialPerPage,
        total: 0,
        search: initialSearch,
    };

    const [cpfForm, setCpfForm] = useState({ cpf: '', birthdate: '' });
    const [cnpjForm, setCnpjForm] = useState({ cnpj: '' });
    const [croForm, setCroForm] = useState({ state: '', number: '' });
    const [loading, setLoading] = useState({ cpf: false, cnpj: false, cro: false });
    const [errors, setErrors] = useState({ cpf: {}, cnpj: {}, cro: {} });
    const [results, setResults] = useState({ cpf: null, cnpj: null, cro: null });
    const [historyData, setHistoryData] = useState(initialHistory?.data ?? []);
    const [historyMeta, setHistoryMeta] = useState(initialMeta);
    const [historyPerPage, setHistoryPerPage] = useState(initialMeta.per_page ?? 20);
    const [historySearch, setHistorySearch] = useState(initialMeta.search ?? '');
    const [historySearchInput, setHistorySearchInput] = useState(initialMeta.search ?? '');
    const [historyLoading, setHistoryLoading] = useState(false);
    const [expandedHistory, setExpandedHistory] = useState(null);
    const [historyJsonVisible, setHistoryJsonVisible] = useState({});
    const [toast, setToast] = useState(null);
    const [activeTab, setActiveTab] = useState('cpf');
    const [showClearConfirm, setShowClearConfirm] = useState(false);

    const integrationDisabled = !infosimples?.enabled;

    const tabs = [
        { key: 'cpf', label: 'Consultar CPF' },
        { key: 'cnpj', label: 'Consultar CNPJ' },
        { key: 'cro', label: 'Consultar CRO' },
        { key: 'history', label: 'Histórico' },
    ];

    const applyHistory = (payload) => {
        const emptyMeta = {
            current_page: 1,
            last_page: 1,
            per_page: historyPerPage,
            total: 0,
            search: historySearch,
        };

        if (!payload) {
            setHistoryData([]);
            setHistoryMeta(emptyMeta);
            setExpandedHistory(null);
            setHistoryJsonVisible({});
            return;
        }

        const meta = payload.meta ?? emptyMeta;
        const normalizedMeta = {
            current_page: meta.current_page ?? 1,
            last_page: meta.last_page ?? 1,
            per_page: meta.per_page ?? historyPerPage,
            total: meta.total ?? 0,
            search: meta.search ?? '',
        };

        setHistoryData(payload.data ?? []);
        setHistoryMeta(normalizedMeta);
        setHistoryPerPage(normalizedMeta.per_page);
        setHistorySearch(normalizedMeta.search);
        setHistorySearchInput(normalizedMeta.search);
        setExpandedHistory(null);
        setHistoryJsonVisible({});
    };

    const fetchHistory = async (page, overrides = {}) => {
        const nextSearch = overrides.search !== undefined ? overrides.search : historySearch;
        const nextPerPage = overrides.perPage !== undefined ? overrides.perPage : historyPerPage;

        setHistoryLoading(true);

        try {
            const response = await window.axios.get('/tools/infosimples/history', {
                params: {
                    page,
                    per_page: nextPerPage,
                    search: nextSearch,
                },
            });

            applyHistory(response.data);
        } catch (error) {
            setToast({
                type: 'error',
                message: error.response?.data?.message ?? 'Não foi possível carregar o histórico agora.',
            });
        } finally {
            setHistoryLoading(false);
        }
    };

    const canGoPrev = historyMeta.current_page > 1;
    const canGoNext = historyMeta.current_page < historyMeta.last_page;

    const handlePageChange = (page) => {
        if (historyLoading) return;
        if (page < 1 || (historyMeta.last_page && page > historyMeta.last_page)) return;

        fetchHistory(page);
    };

    const submitLookup = async (type, payload) => {
        if (integrationDisabled) {
            setToast({
                type: 'error',
                message: 'Ative as configurações do Infosimples para liberar as consultas.',
            });
            return;
        }

        setLoading((prev) => ({ ...prev, [type]: true }));
        setErrors((prev) => ({ ...prev, [type]: {} }));

        try {
            const response = await window.axios.post(`/tools/infosimples/${type}`, payload);

            setResults((prev) => ({
                ...prev,
                [type]: response.data,
            }));

            setToast({
                type: response.data.success ? 'success' : 'warning',
                message: response.data.message ?? 'Consulta concluída.',
            });

            await fetchHistory(1);
        } catch (error) {
            if (error.response?.status === 422) {
                const serverErrors = error.response.data?.errors ?? {};
                const formatted = Object.fromEntries(
                    Object.entries(serverErrors).map(([key, value]) => [
                        key,
                        Array.isArray(value) ? value[0] : value,
                    ]),
                );

                setErrors((prev) => ({
                    ...prev,
                    [type]: formatted,
                }));

                const message = error.response.data?.message;
                if (message) {
                    setToast({ type: 'error', message });
                }
            } else {
                setToast({
                    type: 'error',
                    message: error.response?.data?.message ?? 'Não foi possível concluir a consulta agora.',
                });
            }
        } finally {
            setLoading((prev) => ({ ...prev, [type]: false }));
        }
    };

    const handleClearHistory = async () => {
        setShowClearConfirm(false);
        setHistoryLoading(true);

        try {
            const response = await window.axios.delete('/tools/infosimples/history', {
                params: {
                    per_page: historyPerPage,
                    search: historySearch,
                },
            });
            applyHistory(response.data.history);
            setToast({
                type: 'success',
                message: response.data.message ?? 'Histórico limpo com sucesso.',
            });
        } catch (error) {
            setToast({
                type: 'error',
                message: error.response?.data?.message ?? 'Não foi possível limpar o histórico agora.',
            });
        } finally {
            setHistoryLoading(false);
        }
    };

    const handleCpfChange = (event) => {
        const digits = event.target.value.replace(/\D/g, '').slice(0, 11);
        setCpfForm((prev) => ({ ...prev, cpf: digits }));
    };

    const handleCnpjChange = (event) => {
        const digits = event.target.value.replace(/\D/g, '').slice(0, 14);
        setCnpjForm({ cnpj: digits });
    };

    const handleCpfSubmit = async (event) => {
        event.preventDefault();

        const validationErrors = {};

        if (!validateCPF(cpfForm.cpf)) {
            validationErrors.cpf = 'Informe um CPF válido.';
        }

        if (!cpfForm.birthdate) {
            validationErrors.birthdate = 'Informe a data de nascimento.';
        }

        if (Object.keys(validationErrors).length > 0) {
            setErrors((prev) => ({ ...prev, cpf: validationErrors }));
            return;
        }

        await submitLookup('cpf', {
            cpf: cpfForm.cpf,
            birthdate: cpfForm.birthdate,
        });
    };

    const handleCnpjSubmit = async (event) => {
        event.preventDefault();

        const validationErrors = {};

        if (!validateCNPJ(cnpjForm.cnpj)) {
            validationErrors.cnpj = 'Informe um CNPJ válido.';
        }

        if (Object.keys(validationErrors).length > 0) {
            setErrors((prev) => ({ ...prev, cnpj: validationErrors }));
            return;
        }

        await submitLookup('cnpj', {
            cnpj: cnpjForm.cnpj,
        });
    };

    const handleCroSubmit = async (event) => {
        event.preventDefault();

        const validationErrors = {};

        if (!croForm.state) {
            validationErrors.state = 'Selecione a UF.';
        }

        if (!croForm.number) {
            validationErrors.number = 'Informe o número do CRO.';
        }

        if (Object.keys(validationErrors).length > 0) {
            setErrors((prev) => ({ ...prev, cro: validationErrors }));
            return;
        }

        await submitLookup('cro', {
            state: croForm.state,
            number: croForm.number.trim(),
        });
    };

    const handleSearchSubmit = async (event) => {
        event.preventDefault();
        const term = historySearchInput.trim();

        if (term === historySearch) {
            return;
        }

        await fetchHistory(1, { search: term });
    };

    const handleResetSearch = async () => {
        if (!historySearch) {
            setHistorySearchInput('');
            return;
        }

        setHistorySearchInput('');
        await fetchHistory(1, { search: '' });
    };

    const handlePerPageChange = async (value) => {
        if (value === historyPerPage) {
            return;
        }
        await fetchHistory(1, { perPage: value });
    };

    const renderCpfDetails = (details) => {
        if (!details) {
            return null;
        }

        return (
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 space-y-2 text-sm text-gray-700">
                {details.holder_name && (
                    <div>
                        <span className="font-semibold text-gray-800">Nome: </span>
                        {details.holder_name}
                    </div>
                )}
                {details.status && (
                    <div>
                        <span className="font-semibold text-gray-800">Situação cadastral: </span>
                        {details.status}
                    </div>
                )}
                {details.registration_date && (
                    <div>
                        <span className="font-semibold text-gray-800">Data de inscrição: </span>
                        {details.registration_date}
                    </div>
                )}
            </div>
        );
    };

    const renderCnpjDetails = (details) => {
        if (!details) {
            return null;
        }

        const summary = details.summary ?? {};
        const extra = details.extra ?? [];

        return (
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 space-y-3 text-sm text-gray-700">
                {(summary.company_name || summary.status) && (
                    <div className="space-y-1">
                        {summary.company_name && (
                            <div>
                                <span className="font-semibold text-gray-800">Razão social: </span>
                                {summary.company_name}
                            </div>
                        )}
                        {summary.status && (
                            <div>
                                <span className="font-semibold text-gray-800">Situação: </span>
                                {summary.status}
                                {summary.status_date && ` (atualizado em ${summary.status_date})`}
                            </div>
                        )}
                    </div>
                )}

                {Array.isArray(extra) && extra.length > 0 && (
                    <div className="space-y-2">
                        {extra.map((item, index) => (
                            <div key={index}>
                                <span className="font-semibold text-gray-800">{item.label}: </span>
                                {stringifyValue(item.value)}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    const renderCroDetails = (details) => {
        if (!details) {
            return null;
        }

        const summary = details.summary ?? {};
        const extra = details.extra ?? [];

        return (
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 space-y-3 text-sm text-gray-700">
                {(summary.name || summary.status) && (
                    <div className="space-y-1">
                        {summary.name && (
                            <div>
                                <span className="font-semibold text-gray-800">Profissional: </span>
                                {summary.name}
                            </div>
                        )}
                        {summary.category && (
                            <div>
                                <span className="font-semibold text-gray-800">Categoria: </span>
                                {summary.category}
                            </div>
                        )}
                        {summary.status && (
                            <div>
                                <span className="font-semibold text-gray-800">Situação: </span>
                                {summary.status}
                            </div>
                        )}
                    </div>
                )}

                {Array.isArray(extra) && extra.length > 0 && (
                    <div className="space-y-2">
                        {extra.map((item, index) => (
                            <div key={index}>
                                <span className="font-semibold text-gray-800">{item.label}: </span>
                                {stringifyValue(item.value)}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    const renderResult = (type) => {
        const result = results[type];

        if (!result) {
            return null;
        }

        const showStatusCode = !result.success && result.code;

        return (
            <div className="mt-4 space-y-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={`inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full ${statusBadgeClass(result.success)}`}
                    >
                        {result.success ? 'Consulta concluída' : 'Consulta com alertas'}
                    </span>
                    {showStatusCode && (
                        <span className="text-xs font-semibold tracking-wide text-gray-500 uppercase">
                            Código: {result.code}
                        </span>
                    )}
                </div>

                {result.message && (
                    <p className="text-sm text-gray-700">
                        {result.message}
                    </p>
                )}

                {type === 'cpf' && renderCpfDetails(result.details)}
                {type === 'cnpj' && renderCnpjDetails(result.details)}
                {type === 'cro' && renderCroDetails(result.details)}

                {Array.isArray(result.errors) && result.errors.length > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                        <p className="text-xs font-semibold text-red-700 uppercase tracking-wide">Erros retornados</p>
                        <ul className="mt-1.5 space-y-1 text-xs text-red-600">
                            {result.errors.map((item, index) => (
                                <li key={index}>{stringifyValue(item)}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {result.raw && (
                    <details className="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700">
                        <summary className="cursor-pointer text-sm font-semibold text-gray-800">
                            Ver resposta completa
                        </summary>
                        <pre className="mt-2 whitespace-pre-wrap break-words font-mono text-[11px] text-gray-700">
                            {JSON.stringify(result.raw, null, 2)}
                        </pre>
                    </details>
                )}
            </div>
        );
    };

    return (
        <>
            <Head title="Ferramentas Infosimples" />

            <DashboardLayout>
                <div className="space-y-6">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-bold text-gray-900">Ferramentas Infosimples</h1>
                        <p className="text-sm text-gray-600">
                            Execute consultas pontuais diretamente nos serviços do Infosimples e acompanhe o histórico.
                        </p>
                        {infosimples?.updated_at && (
                            <p className="text-xs text-gray-500">
                                Configurações atualizadas em {infosimples.updated_at}
                            </p>
                        )}
                    </div>

                    {integrationDisabled && (
                        <div className="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4">
                            <p className="font-semibold">Integração desativada</p>
                            <p className="text-sm mt-1">
                                Ative a integração em Configurações para liberar as consultas.
                            </p>
                        </div>
                    )}

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div className="border-b border-gray-200 px-6">
                            <nav className="flex flex-wrap gap-2 py-4">
                                {tabs.map((tab) => (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                            activeTab === tab.key
                                                ? 'bg-blue-600 text-white shadow-sm'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </nav>
                        </div>

                        <div className="p-6">
                            {activeTab === 'cpf' && (
                                <div className="space-y-4">
                                    <div>
                                        <h2 className="text-lg font-semibold text-gray-900">Consulta CPF</h2>
                                        <p className="text-sm text-gray-500 mt-1">
                                            Valide a situação cadastral de um CPF informando a data de nascimento.
                                        </p>
                                    </div>

                                    <form onSubmit={handleCpfSubmit} className="space-y-4">
                                        <MaskedInput
                                            label="CPF"
                                            value={cpfForm.cpf}
                                            onChange={handleCpfChange}
                                            placeholder="000.000.000-00"
                                            mask="999.999.999-99"
                                            error={firstError(errors.cpf, 'cpf')}
                                            disabled={loading.cpf || integrationDisabled}
                                        />

                                        <Input
                                            type="date"
                                            label="Data de nascimento"
                                            value={cpfForm.birthdate}
                                            onChange={(event) => setCpfForm((prev) => ({ ...prev, birthdate: event.target.value }))}
                                            error={firstError(errors.cpf, 'birthdate')}
                                            disabled={loading.cpf || integrationDisabled}
                                        />

                                        <button
                                            type="submit"
                                            disabled={loading.cpf || integrationDisabled}
                                            className="inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                        >
                                            {loading.cpf ? 'Consultando...' : 'Consultar CPF'}
                                        </button>
                                    </form>

                                    {renderResult('cpf')}
                                </div>
                            )}

                            {activeTab === 'cnpj' && (
                                <div className="space-y-4">
                                    <div>
                                        <h2 className="text-lg font-semibold text-gray-900">Consulta CNPJ</h2>
                                        <p className="text-sm text-gray-500 mt-1">
                                            Obtenha o cadastro da Receita Federal para uma empresa pelo CNPJ.
                                        </p>
                                    </div>

                                    <form onSubmit={handleCnpjSubmit} className="space-y-4">
                                        <MaskedInput
                                            label="CNPJ"
                                            value={cnpjForm.cnpj}
                                            onChange={handleCnpjChange}
                                            placeholder="00.000.000/0000-00"
                                            mask="99.999.999/9999-99"
                                            error={firstError(errors.cnpj, 'cnpj')}
                                            disabled={loading.cnpj || integrationDisabled}
                                        />

                                        <button
                                            type="submit"
                                            disabled={loading.cnpj || integrationDisabled}
                                            className="inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                        >
                                            {loading.cnpj ? 'Consultando...' : 'Consultar CNPJ'}
                                        </button>
                                    </form>

                                    {renderResult('cnpj')}
                                </div>
                            )}

                            {activeTab === 'cro' && (
                                <div className="space-y-4">
                                    <div>
                                        <h2 className="text-lg font-semibold text-gray-900">Consulta CRO</h2>
                                        <p className="text-sm text-gray-500 mt-1">
                                            Verifique a inscrição do profissional no Conselho Regional de Odontologia.
                                        </p>
                                    </div>

                                    <form onSubmit={handleCroSubmit} className="space-y-4">
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <Select
                                                label="UF"
                                                value={croForm.state}
                                                onChange={(event) => setCroForm((prev) => ({ ...prev, state: event.target.value }))}
                                                options={STATES}
                                                error={firstError(errors.cro, 'state')}
                                                disabled={loading.cro || integrationDisabled}
                                            />

                                            <div className="md:col-span-2">
                                                <Input
                                                    label="Número do CRO"
                                                    value={croForm.number}
                                                    onChange={(event) => setCroForm((prev) => ({ ...prev, number: event.target.value }))}
                                                    placeholder="Informe o número"
                                                    error={firstError(errors.cro, 'number')}
                                                    disabled={loading.cro || integrationDisabled}
                                                />
                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={loading.cro || integrationDisabled}
                                            className="inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                        >
                                            {loading.cro ? 'Consultando...' : 'Consultar CRO'}
                                        </button>
                                    </form>

                                    {renderResult('cro')}
                                </div>
                            )}

                            {activeTab === 'history' && (
                                <div className="space-y-4">
                                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                                        <form onSubmit={handleSearchSubmit} className="flex flex-1 flex-col gap-2 md:flex-row md:items-end">
                                            <div className="flex-1">
                                                <Input
                                                    label="Buscar no histórico"
                                                    value={historySearchInput}
                                                    onChange={(event) => setHistorySearchInput(event.target.value)}
                                                    placeholder="CPF, CNPJ ou tipo de consulta"
                                                    className="w-full"
                                                />
                                            </div>
                                            <div className="flex gap-2 self-stretch md:self-auto">
                                                <button
                                                    type="submit"
                                                    className="inline-flex items-center justify-center px-4 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 h-[44px]"
                                                    disabled={historyLoading}
                                                >
                                                    Buscar
                                                </button>
                                                {historySearch && (
                                                    <button
                                                        type="button"
                                                        onClick={handleResetSearch}
                                                        className="inline-flex items-center justify-center px-4 text-sm font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1 h-[44px]"
                                                        disabled={historyLoading}
                                                    >
                                                        Limpar filtros
                                                    </button>
                                                )}
                                            </div>
                                        </form>

                                        <div className="w-full md:w-56">
                                            <Select
                                                label="Resultados por página"
                                                value={historyPerPage}
                                                onChange={(event) => handlePerPageChange(Number(event.target.value))}
                                                options={PER_PAGE_OPTIONS}
                                                disabled={historyLoading}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h2 className="text-lg font-semibold text-gray-900">Consultas anteriores</h2>
                                            <p className="text-sm text-gray-500">
                                                Histórico recente das respostas armazenadas em cache.
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-gray-400 uppercase tracking-wide">
                                                Exibindo {historyData.length} de {historyMeta.total} registros
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setShowClearConfirm(true)}
                                                disabled={historyLoading || historyData.length === 0}
                                                className={`inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 ${
                                                    historyLoading || historyData.length === 0
                                                        ? 'bg-red-400 text-white cursor-not-allowed opacity-60'
                                                        : 'bg-red-600 text-white hover:bg-red-700'
                                                }`}
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Limpar histórico
                                            </button>
                                        </div>
                                    </div>

                                    {historyLoading && (
                                        <p className="text-sm text-gray-500">Carregando histórico...</p>
                                    )}

                                    {!historyLoading && historyData.length === 0 && (
                                        <p className="text-sm text-gray-500">
                                            {historySearch ? 'Nenhuma consulta encontrada para os critérios informados.' : 'Nenhuma consulta registrada ainda.'}
                                        </p>
                                    )}

                                    <div className="space-y-3">
                                        {historyData.map((entry) => (
                                            <div
                                                key={entry.id}
                                                className="border border-gray-200 rounded-lg"
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setExpandedHistory((prev) => (prev === entry.id ? null : entry.id))
                                                    }
                                                    className="w-full px-4 py-3 flex flex-col gap-1 text-left hover:bg-gray-50"
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm font-semibold text-gray-900">
                                                            {formatServiceLabel(entry.service)}
                                                        </span>
                                                        <span className="flex items-center gap-2 text-xs text-gray-500">
                                                            <span
                                                                className={`inline-flex w-2.5 h-2.5 rounded-full ${
                                                                    entry.success ? 'bg-green-500' : 'bg-red-500'
                                                                }`}
                                                            />
                                                            {entry.cached_at}
                                                        </span>
                                                    </div>
                                                    <span className="text-xs font-mono text-gray-500 truncate">
                                                        {entry.key}
                                                    </span>
                                                </button>

                                                {expandedHistory === entry.id && (
                                                    <div className="px-4 pb-4 space-y-3">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span
                                                                className={`inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full ${statusBadgeClass(entry.success)}`}
                                                            >
                                                                {entry.success ? 'Consulta concluída' : 'Consulta com alertas'}
                                                            </span>
                                                            {entry.code && !entry.success && (
                                                                <span className="text-xs font-semibold tracking-wide text-gray-500 uppercase">
                                                                    Código: {entry.code}
                                                                </span>
                                                            )}
                                                        </div>

                                                        {entry.message && (
                                                            <p className="text-sm text-gray-700">
                                                                {entry.message}
                                                            </p>
                                                        )}

                                                        {entry.type === 'cpf' && entry.details && renderCpfDetails(entry.details)}
                                                        {entry.type === 'cnpj' && entry.details && renderCnpjDetails(entry.details)}
                                                        {entry.type === 'cro' && entry.details && renderCroDetails(entry.details)}

                                                        {Array.isArray(entry.errors) && entry.errors.length > 0 && (
                                                            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                                                                <p className="text-xs font-semibold text-red-700 uppercase tracking-wide">Erros retornados</p>
                                                                <ul className="mt-1.5 space-y-1 text-xs text-red-600">
                                                                    {entry.errors.map((item, index) => (
                                                                        <li key={index}>{stringifyValue(item)}</li>
                                                                    ))}
                                                                </ul>
                                                            </div>
                                                        )}

                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setHistoryJsonVisible((prev) => ({
                                                                    ...prev,
                                                                    [entry.id]: !prev[entry.id],
                                                                }))
                                                            }
                                                            className="inline-flex items-center gap-2 text-xs font-medium text-blue-600 hover:text-blue-700 focus:outline-none"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            {historyJsonVisible[entry.id] ? 'Ocultar JSON bruto' : 'Ver JSON bruto'}
                                                        </button>

                                                        {historyJsonVisible[entry.id] && (
                                                            <pre className="whitespace-pre-wrap break-words font-mono text-[11px] text-gray-700 bg-gray-50 border border-gray-200 rounded-lg p-3">
                                                                {JSON.stringify(entry.response, null, 2)}
                                                            </pre>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>

                                    {historyMeta.last_page > 1 && (
                                        <div className="flex items-center justify-between pt-2">
                                            <button
                                                type="button"
                                                onClick={() => handlePageChange(historyMeta.current_page - 1)}
                                                disabled={!canGoPrev || historyLoading}
                                                className={`inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border ${
                                                    canGoPrev && !historyLoading
                                                        ? 'border-gray-300 text-gray-700 hover:bg-gray-100'
                                                        : 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50'
                                                }`}
                                            >
                                                Anterior
                                            </button>
                                            <span className="text-xs text-gray-500">
                                                Página {historyMeta.current_page} de {historyMeta.last_page}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => handlePageChange(historyMeta.current_page + 1)}
                                                disabled={!canGoNext || historyLoading}
                                                className={`inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border ${
                                                    canGoNext && !historyLoading
                                                        ? 'border-gray-300 text-gray-700 hover:bg-gray-100'
                                                        : 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50'
                                                }`}
                                            >
                                                Próxima
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </DashboardLayout>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}

            {showClearConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-md bg-white rounded-xl shadow-xl p-6 space-y-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Limpar histórico?</h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Essa ação remove todas as consultas armazenadas em cache. O processo é irreversível.
                            </p>
                        </div>

                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setShowClearConfirm(false)}
                                className="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1"
                                disabled={historyLoading}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={handleClearHistory}
                                className="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed"
                                disabled={historyLoading}
                            >
                                Limpar histórico
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

