import { useEffect, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import Checkbox from '@/Components/Form/Checkbox';
import Toast from '@/Components/Toast';
import DatePickerField from '@/Components/Form/DatePickerField';

export default function ExportsIndex({
    history,
    historyFilters,
    historyStatusOptions,
    fieldCatalog,
    filterOptions,
    defaults,
}) {
    const { flash } = usePage().props;

    const [currentStep, setCurrentStep] = useState(0);
    const [showFieldCustomization, setShowFieldCustomization] = useState(false);
    const [showClearConfirm, setShowClearConfirm] = useState(false);
    const [showScheduler, setShowScheduler] = useState(false);
    const [expandedHistory, setExpandedHistory] = useState({});
    const [toast, setToast] = useState(null);

    const typeLabels = {
        pacientes: 'Pacientes',
        receitas: 'Receitas',
        atendimentos: 'Atendimentos Call Center',
        medicos: 'Médicos',
        produtos: 'Produtos',
    };

    const fieldLabelMaps = useMemo(() => {
        const maps = {};
        Object.keys(fieldCatalog || {}).forEach((type) => {
            maps[type] = {};
            (fieldCatalog[type] || []).forEach((field) => {
                maps[type][field.key] = field.label;
            });
        });
        return maps;
    }, [fieldCatalog]);

    const form = useForm({
        type: defaults?.type ?? 'pacientes',
        export_all_fields: true,
        selected_fields: [],
        filters: {},
    });

    useEffect(() => {
        if (flash?.success) {
            setToast({ message: flash.success, type: 'success' });
        }
        if (flash?.error) {
            setToast({ message: flash.error, type: 'error' });
        }
    }, [flash]);

    const currentFieldCatalog = fieldCatalog?.[form.data.type] || [];
    const currentFieldLabelMap = fieldLabelMaps[form.data.type] || {};
    const currentFilterOptions = filterOptions?.[form.data.type] || {};

    const canGoNext = () => {
        if (currentStep === 0) {
            return Boolean(form.data.type);
        }
        if (currentStep === 1) {
            return true;
        }
        return true;
    };

    const goNext = () => {
        if (!canGoNext()) return;
        setCurrentStep((prev) => Math.min(prev + 1, steps.length - 1));
    };

    const goBack = () => {
        setCurrentStep((prev) => Math.max(prev - 1, 0));
    };

    const steps = [
        {
            title: '1. Escolha o tipo de exportação',
            description: 'Selecione qual módulo deseja exportar.',
        },
        {
            title: '2. Defina os filtros',
            description: 'Refine os dados que serão exportados. Caso não queira filtrar, deixe os valores padrão.',
        },
        {
            title: '3. Campos e confirmação',
            description: 'Escolha quais campos deseja incluir e confirme o agendamento da exportação.',
        },
    ];

    const handleTypeSelect = (type) => {
        form.setData({
            ...form.data,
            type,
            export_all_fields: true,
            selected_fields: [],
            filters: {},
        });
        setShowFieldCustomization(false);
    };

    const handleFilterChange = (field, value) => {
        form.setData('filters', {
            ...form.data.filters,
            [field]: value,
        });
    };

    const toggleFieldSelection = (key) => {
        if (form.data.selected_fields.includes(key)) {
            form.setData(
                'selected_fields',
                form.data.selected_fields.filter((item) => item !== key)
            );
        } else {
            form.setData('selected_fields', [...form.data.selected_fields, key]);
        }
    };

    const handleToggleScheduler = () => {
        setShowScheduler((prev) => {
            const next = !prev;
            if (!next) {
                setCurrentStep(0);
                setShowFieldCustomization(false);
            }
            return next;
        });
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        form.post('/exports', {
            preserveScroll: true,
            onSuccess: () => {
                setCurrentStep(0);
                setShowFieldCustomization(false);
                form.reset();
                setShowScheduler(false);
            },
        });
    };

    const handleHistoryStatusChange = (value) => {
        router.get(
            '/exports',
            { history_status: value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const handleClearHistory = () => {
        router.delete('/exports/history', {
            preserveScroll: true,
            onFinish: () => setShowClearConfirm(false),
        });
    };

    const getHistoryFieldLabels = (entry) => {
        const map = fieldLabelMaps[entry.type] || {};

        if (entry.export_all_fields) {
            return ['Todos os campos'];
        }

        if (!entry.selected_fields || entry.selected_fields.length === 0) {
            return ['Todos os campos'];
        }

        return entry.selected_fields.map((key) => map[key] || key);
    };

    const getHistoryFilters = (entry) => {
        if (!entry.filters || Object.keys(entry.filters).length === 0) {
            return ['Sem filtros'];
        }

        const parts = [];

        Object.entries(entry.filters).forEach(([key, value]) => {
            if (!value || value === 'all' || value === '') {
                return;
            }

            // Map filter keys to labels
            const filterLabels = {
                status: 'Status',
                medico_id: 'Médico',
                paciente_id: 'Paciente',
                usuario_id: 'Usuário',
                clinica_id: 'Clínica',
                created_from: 'Criado de',
                created_to: 'Criado até',
                data_receita_from: 'Data receita de',
                data_receita_to: 'Data receita até',
                data_abertura_from: 'Abertura de',
                data_abertura_to: 'Abertura até',
                cidade: 'Cidade',
                uf: 'UF',
                categoria: 'Categoria',
                local_uso: 'Local de uso',
                preco_min: 'Preço mínimo',
                preco_max: 'Preço máximo',
            };

            const label = filterLabels[key] || key;
            parts.push(`${label}: ${value}`);
        });

        return parts.length ? parts : ['Sem filtros'];
    };

    const renderStepContent = () => {
        if (currentStep === 0) {
            return (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {Object.entries(typeLabels).map(([type, label]) => (
                        <button
                            key={type}
                            type="button"
                            onClick={() => handleTypeSelect(type)}
                            className={`rounded-xl border p-6 text-left transition-all ${
                                form.data.type === type
                                    ? 'border-blue-500 bg-blue-50 shadow-sm'
                                    : 'border-gray-200 hover:border-blue-300 hover:bg-blue-50/50'
                            }`}
                        >
                            <h3 className="text-lg font-semibold text-gray-900">
                                {label}
                            </h3>
                            <p className="mt-2 text-sm text-gray-600">
                                {type === 'pacientes' && 'Exporta informações completas dos pacientes cadastrados.'}
                                {type === 'receitas' && 'Exporta receitas com detalhes financeiros e relacionamentos.'}
                                {type === 'atendimentos' && 'Exporta atendimentos do call center com histórico.'}
                                {type === 'medicos' && 'Exporta informações dos médicos e estatísticas.'}
                                {type === 'produtos' && 'Exporta catálogo de produtos com dados de vendas.'}
                            </p>
                        </button>
                    ))}
                </div>
            );
        }

        if (currentStep === 1) {
            // Determine the correct filter key based on module type
            const statusFilterKey = (form.data.type === 'pacientes' || form.data.type === 'medicos' || form.data.type === 'produtos') 
                ? 'ativo' 
                : 'status';

            return (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Status/Ativo filter */}
                    {currentFilterOptions.status && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Status
                            </label>
                            <select
                                value={form.data.filters[statusFilterKey] || 'all'}
                                onChange={(event) =>
                                    handleFilterChange(statusFilterKey, event.target.value)
                                }
                                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                {currentFilterOptions.status.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Médico filter */}
                    {currentFilterOptions.medicos && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Médico
                            </label>
                            <select
                                value={form.data.filters.medico_id || 'all'}
                                onChange={(event) =>
                                    handleFilterChange('medico_id', event.target.value === 'all' ? '' : event.target.value)
                                }
                                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="all">Todos</option>
                                {currentFilterOptions.medicos.map((medico) => (
                                    <option key={medico.value} value={medico.value}>
                                        {medico.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Paciente filter */}
                    {currentFilterOptions.pacientes && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Paciente
                            </label>
                            <select
                                value={form.data.filters.paciente_id || 'all'}
                                onChange={(event) =>
                                    handleFilterChange('paciente_id', event.target.value === 'all' ? '' : event.target.value)
                                }
                                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="all">Todos</option>
                                {currentFilterOptions.pacientes.map((paciente) => (
                                    <option key={paciente.value} value={paciente.value}>
                                        {paciente.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Clínica filter */}
                    {currentFilterOptions.clinicas && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Clínica
                            </label>
                            <select
                                value={form.data.filters.clinica_id || 'all'}
                                onChange={(event) =>
                                    handleFilterChange('clinica_id', event.target.value === 'all' ? '' : event.target.value)
                                }
                                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="all">Todos</option>
                                {currentFilterOptions.clinicas.map((clinica) => (
                                    <option key={clinica.value} value={clinica.value}>
                                        {clinica.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Usuário filter */}
                    {currentFilterOptions.usuarios && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Usuário Responsável
                            </label>
                            <select
                                value={form.data.filters.usuario_id || 'all'}
                                onChange={(event) =>
                                    handleFilterChange('usuario_id', event.target.value === 'all' ? '' : event.target.value)
                                }
                                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="all">Todos</option>
                                {currentFilterOptions.usuarios.map((usuario) => (
                                    <option key={usuario.value} value={usuario.value}>
                                        {usuario.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Categoria filter */}
                    {currentFilterOptions.categorias && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Categoria
                            </label>
                            <select
                                value={form.data.filters.categoria || 'all'}
                                onChange={(event) =>
                                    handleFilterChange('categoria', event.target.value === 'all' ? '' : event.target.value)
                                }
                                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="all">Todas</option>
                                {currentFilterOptions.categorias.map((cat) => (
                                    <option key={cat.value} value={cat.value}>
                                        {cat.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Local uso filter */}
                    {currentFilterOptions.local_uso && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Local de Uso
                            </label>
                            <select
                                value={form.data.filters.local_uso || 'all'}
                                onChange={(event) =>
                                    handleFilterChange('local_uso', event.target.value === 'all' ? '' : event.target.value)
                                }
                                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="all">Todos</option>
                                {currentFilterOptions.local_uso.map((local) => (
                                    <option key={local.value} value={local.value}>
                                        {local.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Date filters */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-3">
                            <DatePickerField
                                label="Data inicial"
                                value={form.data.filters.created_from || form.data.filters.data_receita_from || form.data.filters.data_abertura_from || ''}
                                onChange={(v) => {
                                    const key = form.data.type === 'receitas' ? 'data_receita_from' : 
                                               form.data.type === 'atendimentos' ? 'data_abertura_from' : 
                                               'created_from';
                                    handleFilterChange(key, v);
                                }}
                                allowType
                            />
                        </div>

                        <div className="space-y-3">
                            <DatePickerField
                                label="Data final"
                                value={form.data.filters.created_to || form.data.filters.data_receita_to || form.data.filters.data_abertura_to || ''}
                                onChange={(v) => {
                                    const key = form.data.type === 'receitas' ? 'data_receita_to' : 
                                               form.data.type === 'atendimentos' ? 'data_abertura_to' : 
                                               'created_to';
                                    handleFilterChange(key, v);
                                }}
                                allowType
                            />
                        </div>
                    </div>
                </div>
            );
        }

        return (
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Checkbox
                        checked={form.data.export_all_fields}
                        onChange={(event) => {
                            const checked = event.target.checked;
                            form.setData('export_all_fields', checked);
                            if (checked) {
                                form.setData('selected_fields', []);
                            }
                        }}
                        label="Exportar todos os campos"
                    />

                    <button
                        type="button"
                        onClick={() => {
                            setShowFieldCustomization((prev) => !prev);
                            if (form.data.export_all_fields) {
                                form.setData('export_all_fields', false);
                            }
                        }}
                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                    >
                        {showFieldCustomization ? 'Ocultar campos' : 'Customizar campos'}
                    </button>
                </div>

                {showFieldCustomization && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 border border-gray-200 rounded-xl p-4 bg-gray-50 max-h-96 overflow-y-auto">
                        {currentFieldCatalog.map((field) => (
                            <Checkbox
                                key={field.key}
                                checked={
                                    form.data.export_all_fields ||
                                    form.data.selected_fields.includes(field.key)
                                }
                                onChange={() => toggleFieldSelection(field.key)}
                                disabled={form.data.export_all_fields}
                                label={
                                    <span>
                                        <span className="font-medium text-gray-800">
                                            {field.label}
                                        </span>
                                        {field.description && (
                                            <span className="ml-2 text-xs text-gray-500">
                                                {field.description}
                                            </span>
                                        )}
                                    </span>
                                }
                            />
                        ))}
                    </div>
                )}

                {form.errors.selected_fields && (
                    <p className="text-sm text-red-600">{form.errors.selected_fields}</p>
                )}

                <div className="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-800">
                    <p className="font-semibold">Resumo da exportação</p>
                    <ul className="mt-2 space-y-1 text-blue-700">
                        <li>
                            <span className="font-medium">Tipo:</span>{' '}
                            {typeLabels[form.data.type] || form.data.type}
                        </li>
                        <li>
                            <span className="font-medium">Filtros:</span>{' '}
                            {Object.entries(form.data.filters)
                                .filter(([key, value]) => value && value !== 'all' && value !== '')
                                .map(([key, value]) => value)
                                .join(', ') || 'Nenhum'}
                        </li>
                        <li>
                            <span className="font-medium">Campos:</span>{' '}
                            {form.data.export_all_fields
                                ? 'Todos os campos disponíveis'
                                : form.data.selected_fields
                                      .map((key) => currentFieldLabelMap[key] || key)
                                      .join(', ') || 'Selecione ao menos um campo'}
                        </li>
                    </ul>
                </div>
            </div>
        );
    };

    const renderHistory = () => {
        if (!history || history.data.length === 0) {
            return (
                <div className="bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-500">
                    Nenhuma exportação encontrada.
                </div>
            );
        }

        return (
            <div className="max-w-full min-w-0 overflow-hidden border border-gray-200 rounded-xl bg-white shadow-sm">
                <div className="min-w-0 divide-y divide-gray-200">
                    {history.data.map((entry) => (
                        <div key={entry.id} className="bg-white min-w-0">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between md:gap-4 px-4 py-4 sm:px-6 min-w-0">
                                <div className="flex gap-3 min-w-0 flex-1">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setExpandedHistory((prev) => ({
                                                ...prev,
                                                [entry.id]: !prev[entry.id],
                                            }))
                                        }
                                        className="inline-flex flex-shrink-0 items-center justify-center w-9 h-9 min-w-[36px] min-h-[36px] rounded-full border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors"
                                        aria-label="Alternar detalhes"
                                    >
                                        <svg
                                            className={`w-4 h-4 transition-transform ${
                                                expandedHistory[entry.id] ? 'rotate-90' : ''
                                            }`}
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </button>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-start gap-2">
                                            <span className="text-base font-semibold text-gray-900 break-words">
                                                {entry.type_label}
                                            </span>
                                            <span
                                                className={`inline-flex flex-shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold ${entry.status_badge}`}
                                            >
                                                {entry.status_label}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs text-gray-500 break-words">
                                            Solicitado em {entry.created_at}
                                            {entry.completed_at ? ` • Finalizado em ${entry.completed_at}` : ''}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between w-full gap-3 md:w-auto md:justify-end md:flex-shrink-0 pl-12 md:pl-0">
                                    <span className="text-sm text-gray-500">
                                        Registros: {entry.total_records ?? 0}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (entry.download_url) {
                                                window.location.href = entry.download_url;
                                            }
                                        }}
                                        disabled={!entry.download_url}
                                        className={`min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg p-2 transition-colors ${
                                            entry.download_url
                                                ? 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50'
                                                : 'text-gray-300 cursor-not-allowed opacity-50'
                                        }`}
                                        aria-label="Baixar arquivo"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {expandedHistory[entry.id] && (
                                <div className="px-4 sm:px-6 pt-4 pb-6 space-y-4 border-t border-gray-100 min-w-0">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-700">
                                        <div>
                                            <h4 className="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                                Solicitado por
                                            </h4>
                                            <p className="mt-1">
                                                {entry.requested_by?.name ?? 'Desconhecido'}
                                            </p>
                                        </div>
                                        <div>
                                            <h4 className="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                                Filtros usados
                                            </h4>
                                            <ul className="mt-1 space-y-1">
                                                {getHistoryFilters(entry).map((item, index) => (
                                                    <li key={index}>{item}</li>
                                                ))}
                                            </ul>
                                        </div>
                                        <div>
                                            <h4 className="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                                Campos exportados
                                            </h4>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {getHistoryFieldLabels(entry).map((item, index) => (
                                                    <span
                                                        key={index}
                                                        className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
                                                    >
                                                        {item}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    {entry.error_message && (
                                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                            Erro: {entry.error_message}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
                <Pagination links={history.links} />
            </div>
        );
    };

    return (
        <DashboardLayout>
            <Head title="Exportações" />

            <div className="space-y-8 py-4 lg:py-6 px-0">
                <PageHeader
                    title="Exportações"
                    description="Exporte dados do sistema em formato CSV. Você receberá um e-mail quando a exportação estiver pronta."
                    actions={
                        <button
                            type="button"
                            onClick={handleToggleScheduler}
                            className="w-full sm:w-auto justify-center min-h-[44px] inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            {showScheduler ? 'Ocultar formulário' : 'Nova exportação'}
                        </button>
                    }
                />

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap sm:gap-4">
                        <div className="flex flex-col gap-2 min-w-0 flex-1 sm:flex-row sm:items-center sm:gap-3">
                            <label htmlFor="exports-history-filter" className="text-sm font-medium text-gray-700 shrink-0">
                                Filtrar histórico
                            </label>
                            <select
                                id="exports-history-filter"
                                value={historyFilters.status}
                                onChange={(event) =>
                                    handleHistoryStatusChange(event.target.value)
                                }
                                className="min-h-[44px] w-full sm:max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-base sm:text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                            >
                                {historyStatusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {history?.data?.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setShowClearConfirm(true)}
                                className="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                            >
                                Limpar histórico
                            </button>
                        )}
                    </div>
                </div>

                {showScheduler && (
                    <form
                        onSubmit={handleSubmit}
                        className="space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
                    >
                        <div className="grid grid-cols-1 gap-6">
                            {steps.map((step, index) => (
                                <div
                                    key={step.title}
                                    className={`rounded-xl border p-5 transition-colors ${
                                        index === currentStep
                                            ? 'border-blue-500 bg-blue-50'
                                            : 'border-gray-200 bg-white'
                                    }`}
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h2 className="text-lg font-semibold text-gray-900">
                                                {step.title}
                                            </h2>
                                            <p className="mt-1 text-sm text-gray-600">
                                                {step.description}
                                            </p>
                                        </div>
                                        <span
                                            className={`mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold ${
                                                index <= currentStep
                                                    ? 'bg-blue-600 text-white'
                                                    : 'bg-gray-200 text-gray-600'
                                            }`}
                                        >
                                            {index + 1}
                                        </span>
                                    </div>

                                    {index === currentStep && (
                                        <div className="mt-4">{renderStepContent()}</div>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center justify-between">
                            <div>
                                {Object.values(form.errors).length > 0 && (
                                    <p className="text-sm text-red-600">
                                        Corrija os erros antes de prosseguir.
                                    </p>
                                )}
                            </div>
                            <div className="flex gap-3">
                                {currentStep > 0 && (
                                    <button
                                        type="button"
                                        onClick={goBack}
                                        className="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                                    >
                                        Voltar
                                    </button>
                                )}
                                {currentStep < steps.length - 1 && (
                                    <button
                                        type="button"
                                        onClick={goNext}
                                        className="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                    >
                                        Próximo
                                    </button>
                                )}
                                {currentStep === steps.length - 1 && (
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {form.processing ? 'Agendando...' : 'Agendar exportação'}
                                    </button>
                                )}
                            </div>
                        </div>
                    </form>
                )}

                <div className="space-y-4">
                    <div className="flex items-center gap-3">
                        <h2 className="text-lg font-semibold text-gray-900">
                            Histórico de exportações
                        </h2>
                    </div>
                    {renderHistory()}
                </div>
            </div>

            {showClearConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Limpar histórico de exportações
                        </h3>
                        <p className="mt-2 text-sm text-gray-600">
                            Essa ação remove todas as exportações já realizadas e seus arquivos
                            associados. Ela não pode ser desfeita.
                        </p>

                        <div className="mt-6 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setShowClearConfirm(false)}
                                className="inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={handleClearHistory}
                                className="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                            >
                                Confirmar limpeza
                            </button>
                        </div>
                    </div>
                </div>
            )}

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
