import { useForm, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PatientDrawer from '@/Components/PatientDrawer';
import Toast from '@/Components/Toast';
import DatePickerField from '@/Components/Form/DatePickerField';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import debounce from 'lodash/debounce';
import useAutoSave from '@/hooks/useAutoSave';
import ReceitaFormItemRow from '@/Components/Receita/ReceitaFormItemRow';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import ReceitasIndexBackLink from '@/Components/ReceitasIndexBackLink';
import Tippy from '@tippyjs/react';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/themes/light-border.css';
import { formatAnotacaoDisplay } from '@/utils/text';
import { nomeExibicaoSemTitulo } from '@/utils/nomeExibicao';
import { tituloReceitaComSequencia } from '@/utils/receitaNumero';

const tippyAquisicaoProps = {
    appendTo: () => document.body,
    popperOptions: { strategy: 'fixed' },
    zIndex: 9999,
};

const duplicarReceitaTippyContent = (
    <div className="text-xs text-gray-800 max-w-xs">
        Repete a prescrição numa nova receita com a data de hoje.
    </div>
);

// Mapeamento de local_uso para nomes mais descritivos
const localUsoLabels = {
    'face': 'Creme Facial',
    'rosto': 'Creme Facial',
    'olhos': 'Creme dos Olhos',
    'corpo': 'Creme Corpo',
    'maos': 'Creme Mãos',
    'pes': 'Creme Pés',
    'cabelo': 'Capilar',
    'solar': 'Protetor Solar',
    'limpeza': 'Limpeza',
    'serum': 'Sérum',
    'mascara': 'Máscara',
    'tonalite': 'Base Tonalité',
};

const formatLocalUso = (localUso) => {
    if (!localUso) return '-';
    // Se já tem um nome descritivo (mais de uma palavra ou começa com maiúscula), usar como está
    if (localUso.includes(' ') || /^[A-Z]/.test(localUso)) {
        return localUso;
    }
    // Caso contrário, tentar mapear
    const key = localUso.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return localUsoLabels[key] || localUso;
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('pt-BR');
};

function OutrasReceitaAquisicaoBadge({ item, tippyAquisicaoProps }) {
    const ultimaAquisicao =
        item.ultima_aquisicao ||
        (item.aquisicoes && item.aquisicoes.length > 0 ? item.aquisicoes[item.aquisicoes.length - 1].data_aquisicao : null);
    const datasAquisicao = item.datas_aquisicao || (item.aquisicoes ? item.aquisicoes.map((a) => a.data_aquisicao) : []);
    const temHistorico = datasAquisicao.length > 1;

    if (!ultimaAquisicao) return <span className="text-gray-400">—</span>;

    if (temHistorico) {
        return (
            <Tippy
                content={
                    <div className="text-xs py-1">
                        <div className="font-medium mb-2 text-gray-900">Últimas aquisições</div>
                        <div className="space-y-1">
                            {datasAquisicao.map((data, idx) => (
                                <div key={idx} className="text-gray-700">
                                    {formatDate(data)}
                                </div>
                            ))}
                        </div>
                    </div>
                }
                placement="top"
                interactive={true}
                theme="light-border"
                {...tippyAquisicaoProps}
            >
                <span className="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-md inline-flex items-center gap-1.5 cursor-help hover:bg-gray-200 transition-colors">
                    <span>{formatDate(ultimaAquisicao)}</span>
                    <span className="px-1 py-0 bg-gray-200 text-gray-600 text-[10px] font-medium rounded leading-none">+{datasAquisicao.length - 1}</span>
                </span>
            </Tippy>
        );
    }

    return (
        <Tippy content={<div className="text-xs text-gray-700">{formatDate(ultimaAquisicao)}</div>} placement="top" theme="light-border" {...tippyAquisicaoProps}>
            <span className="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-md inline-flex items-center gap-1 cursor-help hover:bg-gray-200 transition-colors">
                <span>{formatDate(ultimaAquisicao)}</span>
            </span>
        </Tippy>
    );
}

export default function ReceitaFormPage(props) {
    return <ReceitaFormInner key={props.receita?.id ?? 'new'} {...props} />;
}

function ReceitaFormInner({
    receita,
    paciente: initialPaciente,
    produtos,
    medicos,
    medicosPacienteDrawer = [],
    receitaFormIsAdmin = false,
    receitaFormIsSecretaria = false,
    receitaFormCanSelectMedico = true,
    defaultMedicoId,
    receitasAnteriores = [],
    bloqueadaParaEdicao = false,
    viewMode: initialViewMode = false,
    casoClinico = null,
}) {
    const { auth, flash } = usePage().props;
    const isMedico = auth.user.role === 'medico';
    const [toast, setToast] = useState(null);
    const isEditing = !!receita;
    const [viewMode, setViewMode] = useState(initialViewMode && isEditing);
    const isReadOnly = bloqueadaParaEdicao || viewMode;
    const [currentReceitaId, setCurrentReceitaId] = useState(receita?.id || null);
    const isFirstRender = useRef(true);
    const suppressAutosaveOnceRef = useRef(false);
    const [showFinalizarModal, setShowFinalizarModal] = useState(false);
    const [showDuplicarModal, setShowDuplicarModal] = useState(false);
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const [expandedReceitas, setExpandedReceitas] = useState({});
    const [patientDrawerOpen, setPatientDrawerOpen] = useState(false);
    
    const { data, setData, post, put, processing, errors } = useForm({
        paciente_id: receita?.paciente_id || initialPaciente?.id || '',
        medico_id: receita?.medico_id || defaultMedicoId || '',
        data_receita: receita?.data_receita ? receita.data_receita.split('T')[0] : new Date().toISOString().split('T')[0],
        anotacoes: receita?.anotacoes || '',
        desconto_percentual: receita?.desconto_percentual || 0,
        desconto_motivo: receita?.desconto_motivo || '',
        valor_caixa: receita?.valor_caixa || 0,
        valor_frete: receita?.valor_frete || 0,
        status: receita?.status || 'aberta',
        itens: receita?.itens?.map(item => ({
            id: item.id,
            produto_id: item.produto_id,
            local_uso: item.local_uso || '',
            anotacoes: item.anotacoes || '',
            quantidade: item.quantidade,
            valor_unitario: parseFloat(item.valor_unitario) || 0,
            imprimir: item.imprimir ?? true,
            vendido: item.vendido || false,
            grupo: item.grupo || 'recomendado',
            ultima_aquisicao: item.ultima_aquisicao || null,
            datas_aquisicao: item.datas_aquisicao || [],
        })) || [],
    });

    const [searchPaciente, setSearchPaciente] = useState('');
    const [pacienteResults, setPacienteResults] = useState([]);
    const [showPacienteDropdown, setShowPacienteDropdown] = useState(false);
    const [selectedPaciente, setSelectedPaciente] = useState(receita?.paciente || initialPaciente || null);
    const [loadingPacientes, setLoadingPacientes] = useState(false);
    const lastItemRef = useRef(null);

    // Autosave function
    const performAutoSave = useCallback(async () => {
        if (!data.paciente_id || !data.medico_id) return;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch('/api/receitas/autosave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                id: currentReceitaId,
                ...data,
            }),
        });
        
        if (!response.ok) throw new Error('Autosave failed');
        
        const result = await response.json();
        if (result.id && !currentReceitaId) {
            setCurrentReceitaId(result.id);
            // Update URL to edit mode without full page reload
            window.history.replaceState({}, '', `/receitas/${result.id}/edit`);
        }
        if (Array.isArray(result.itens) && result.itens.length === data.itens.length) {
            suppressAutosaveOnceRef.current = true;
            setData(
                'itens',
                data.itens.map((row, i) => ({
                    ...row,
                    id: result.itens[i]?.id ?? row.id,
                    produto_id: result.itens[i]?.produto_id ?? row.produto_id,
                }))
            );
        }
        setToast({
            message: 'Alterações salvas automaticamente',
            type: 'success',
            key: Date.now(),
        });
        return result;
    }, [data, currentReceitaId]);

    const canAutoSave = data.paciente_id && data.medico_id && !isReadOnly;
    const { 
        lastSavedText, 
        isSaving: isAutoSaving, 
        hasUnsavedChanges,
        triggerAutoSave,
        saveNow,
        cancelAutoSave,
        clearDirtyState,
        markUnsaved,
        bumpLastSaved,
    } = useAutoSave(performAutoSave, 2000, canAutoSave);

    // Unsaved changes modal state
    const [showUnsavedModal, setShowUnsavedModal] = useState(false);
    const [savingBeforeLeave, setSavingBeforeLeave] = useState(false);
    const pendingVisitRef = useRef(null);
    /** Deixa passar o próximo GET (ex.: após “Salvar e sair” / “Sair sem salvar”) sem reabrir a modal se o dirty voltar no mesmo tick. */
    const skipUnsavedGuardOnceRef = useRef(false);

    // Intercept Inertia navigation to warn about unsaved changes
    useEffect(() => {
        if (isReadOnly) return;

        const removeListener = router.on('before', (event) => {
            if (skipUnsavedGuardOnceRef.current) {
                skipUnsavedGuardOnceRef.current = false;
                return;
            }
            if (!hasUnsavedChanges) return;
            // Don't intercept form submissions (same-page autosave, finalizar, etc.)
            if (event.detail?.visit?.method !== 'get') return;

            event.preventDefault();
            cancelAutoSave();
            pendingVisitRef.current = event.detail.visit;
            setShowUnsavedModal(true);
        });

        return removeListener;
    }, [hasUnsavedChanges, isReadOnly, cancelAutoSave]);

    // Browser tab/close protection
    useEffect(() => {
        if (isReadOnly || !hasUnsavedChanges) return;

        const handler = (e) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [hasUnsavedChanges, isReadOnly]);

    const handleUnsavedCancel = () => {
        setShowUnsavedModal(false);
        pendingVisitRef.current = null;
    };

    const handleUnsavedDiscard = () => {
        setShowUnsavedModal(false);
        cancelAutoSave();
        const visit = pendingVisitRef.current;
        pendingVisitRef.current = null;
        if (visit) {
            skipUnsavedGuardOnceRef.current = true;
            router.visit(visit.url, { ...visit, onBefore: undefined });
        }
    };

    const handleUnsavedSave = async () => {
        setSavingBeforeLeave(true);
        try {
            await saveNow();
            const visit = pendingVisitRef.current;
            pendingVisitRef.current = null;
            setShowUnsavedModal(false);
            if (visit) {
                skipUnsavedGuardOnceRef.current = true;
                router.visit(visit.url, { ...visit, onBefore: undefined });
            }
        } catch {
            setToast({
                message: 'Não foi possível salvar automaticamente. Corrija os dados ou use Salvar na receita.',
                type: 'error',
                key: Date.now(),
            });
        } finally {
            setSavingBeforeLeave(false);
        }
    };

    // Atualizar dados de aquisição quando receita mudar ou paciente mudar (só linha atual; sem cruzar outras receitas)
    const pacienteId = selectedPaciente?.id || receita?.paciente_id || initialPaciente?.id;
    
    useEffect(() => {
        if (!pacienteId || data.itens.length === 0) return;
        
        const newItens = data.itens.map(item => {
            // Se já tem dados de aquisição, manter
            if (item.ultima_aquisicao) {
                return item;
            }
            
            // Se tem produto_id mas não tem dados de aquisição, buscar
            if (item.produto_id) {
                const itemOriginal = receita?.itens?.find(i => i.id === item.id);
                
                // Primeiro tentar usar dados do item original se disponível
                if (itemOriginal && (itemOriginal.ultima_aquisicao || itemOriginal.datas_aquisicao?.length > 0)) {
                    return {
                        ...item,
                        ultima_aquisicao: itemOriginal.ultima_aquisicao || null,
                        datas_aquisicao: itemOriginal.datas_aquisicao || [],
                    };
                }
            }
            
            return item;
        });
        
        // Verificar se há diferenças antes de atualizar (comparação profunda)
        const hasChanges = newItens.some((item, index) => {
            const original = data.itens[index];
            if (!original) return false;
            return item.ultima_aquisicao !== original.ultima_aquisicao || 
                   JSON.stringify(item.datas_aquisicao || []) !== JSON.stringify(original.datas_aquisicao || []);
        });
        
        if (hasChanges) {
            suppressAutosaveOnceRef.current = true;
            setData('itens', newItens);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [receita?.id, pacienteId]);

    // Trigger autosave when data changes
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (suppressAutosaveOnceRef.current) {
            suppressAutosaveOnceRef.current = false;
            return;
        }
        if (canAutoSave) {
            triggerAutoSave();
        }
    }, [data, canAutoSave, triggerAutoSave]);

    useEffect(() => {
        if (flash?.success) {
            setToast({ message: flash.success, type: 'success' });
        }
        if (flash?.error) {
            setToast({ message: flash.error, type: 'error' });
        }
    }, [flash]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('duplicada') === '1') {
            setToast({ message: 'Receita duplicada com sucesso!', type: 'success' });
            params.delete('duplicada');
            const newUrl = params.toString()
                ? `${window.location.pathname}?${params.toString()}`
                : window.location.pathname;
            window.history.replaceState({}, '', newUrl);
        }
    }, []);

    // Debounced search for patients
    const searchPacientes = useCallback(
        debounce(async (term) => {
            if (term.length < 2) {
                setPacienteResults([]);
                setShowPacienteDropdown(false);
                return;
            }
            setLoadingPacientes(true);
            try {
                const response = await fetch(`/api/pacientes/search?q=${encodeURIComponent(term)}`);
                const results = await response.json();
                setPacienteResults(results);
                setShowPacienteDropdown(true);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingPacientes(false);
            }
        }, 300),
        []
    );

    useEffect(() => {
        searchPacientes(searchPaciente);
    }, [searchPaciente, searchPacientes]);

    const selectPaciente = (paciente) => {
        setSelectedPaciente(paciente);
        setData('paciente_id', paciente.id);
        setSearchPaciente('');
        setShowPacienteDropdown(false);
    };

    useEffect(() => {
        if (receita?.paciente) {
            setSelectedPaciente(receita.paciente);
        }
    }, [receita?.paciente]);

    const reloadPacienteNaReceita = () => {
        if (receita?.id) {
            router.reload({ only: ['receita', 'paciente'] });
        } else if (selectedPaciente?.id) {
            router.reload({ only: ['paciente'] });
        }
    };

    const addItem = () => {
        setData('itens', [
            ...data.itens,
            {
                produto_id: '',
                local_uso: '',
                anotacoes: '',
                quantidade: 1,
                valor_unitario: 0,
                imprimir: true,
                grupo: 'recomendado',
                ultima_aquisicao: null,
                datas_aquisicao: [],
            },
        ]);
        // Scroll para o novo item após o DOM atualizar
        setTimeout(() => {
            lastItemRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    };

    const removeItem = (index) => {
        const newItens = [...data.itens];
        newItens.splice(index, 1);
        setData('itens', newItens);
    };

    const updateItem = (index, field, value) => {
        const newItens = [...data.itens];
        const currentItem = newItens[index];
        
        // Preservar dados de aquisição ao atualizar
        newItens[index] = { 
            ...currentItem, 
            [field]: value,
            // Preservar dados de aquisição se existirem
            ultima_aquisicao: currentItem.ultima_aquisicao,
            datas_aquisicao: currentItem.datas_aquisicao || [],
        };

        // Se mudou o produto, atualiza o preco e local_uso padrao
        if (field === 'produto_id') {
            const produto = produtos.find(p => p.id === parseInt(value));
            if (produto) {
                newItens[index].valor_unitario = parseFloat(produto.preco_venda) || parseFloat(produto.preco) || 0;
                newItens[index].local_uso = produto.local_uso || '';
                newItens[index].ultima_aquisicao = null;
                newItens[index].datas_aquisicao = [];
            }
        }

        // Se marcou o checkbox (imprimir) e tem produto_id mas não tem dados de aquisição, usar só a linha já persistida desta receita
        if (field === 'imprimir' && value === true && currentItem.produto_id && !currentItem.ultima_aquisicao) {
            const itemOriginal = receita?.itens?.find(
                (i) => i.id === currentItem.id && String(i.produto_id) === String(currentItem.produto_id)
            );
            if (itemOriginal && (itemOriginal.ultima_aquisicao || itemOriginal.datas_aquisicao?.length > 0)) {
                newItens[index].ultima_aquisicao = itemOriginal.ultima_aquisicao || null;
                newItens[index].datas_aquisicao = itemOriginal.datas_aquisicao || [];
            }
        }

        setData('itens', newItens);
    };

    const calcularSubtotalItem = (item) => {
        return item.quantidade * item.valor_unitario;
    };

    const calcularSubtotal = () => {
        return data.itens
            .filter(item => item.imprimir)
            .reduce((total, item) => total + calcularSubtotalItem(item), 0);
    };

    const calcularDesconto = () => {
        const subtotal = calcularSubtotal();
        return subtotal * (data.desconto_percentual / 100);
    };

    const calcularTotal = () => {
        const subtotal = calcularSubtotal();
        const desconto = calcularDesconto();
        const frete = parseFloat(data.valor_frete) || 0;
        const caixa = parseFloat(data.valor_caixa) || 0;
        return subtotal - desconto + frete + caixa;
    };

    const inertiaPersistReceitaOptions = {
        onStart: () => clearDirtyState(),
        onError: () => markUnsaved(),
    };

    const handleSubmit = (e) => {
        e?.preventDefault();
        if (isEditing) {
            put(`/receitas/${receita.id}`, {
                ...inertiaPersistReceitaOptions,
                onSuccess: () => {
                    bumpLastSaved();
                    setViewMode(true);
                },
            });
        } else {
            post('/receitas', {
                ...inertiaPersistReceitaOptions,
            });
        }
    };

    const finalizarReceita = () => {
        const receitaId = currentReceitaId || receita?.id;
        if (!receitaId) return;
        
        router.put(`/receitas/${receitaId}`, {
            ...data,
            status: 'finalizada',
        }, {
            ...inertiaPersistReceitaOptions,
            onSuccess: () => {
                bumpLastSaved();
                setData('status', 'finalizada');
                setViewMode(true);
                setToast({ message: 'Receita finalizada com sucesso!', type: 'success' });
            },
        });
        setShowFinalizarModal(false);
    };

    const toggleReceitaExpanded = (id) => {
        setExpandedReceitas(prev => ({
            ...prev,
            [id]: !prev[id]
        }));
    };

    const handleDuplicar = () => {
        const doCopy = () => {
            setShowDuplicarModal(false);
            router.post(`/receitas/${receita.id}/copiar`);
        };
        if (!viewMode && isEditing) {
            put(`/receitas/${receita.id}`, { ...inertiaPersistReceitaOptions, onSuccess: doCopy });
        } else {
            doCopy();
        }
    };

    const handleCancelar = () => {
        const doCancel = () => {
            setShowCancelarModal(false);
            router.delete(`/receitas/${receita.id}`);
        };
        if (!viewMode && isEditing) {
            put(`/receitas/${receita.id}`, { ...inertiaPersistReceitaOptions, onSuccess: doCancel });
        } else {
            doCancel();
        }
    };

    const canEdit = isEditing && receita.status === 'aberta' && !bloqueadaParaEdicao && !isMedico;
    const canCancel = isEditing && receita.status !== 'cancelada' && !(isMedico && receita.status === 'finalizada');

    const pacienteCabecalho = receita?.paciente || selectedPaciente;
    const codigoRegistroPaciente =
        pacienteCabecalho?.codigo != null && String(pacienteCabecalho.codigo).trim() !== ''
            ? String(pacienteCabecalho.codigo).trim()
            : '';

    return (
        <DashboardLayout>
            <div className="py-4 lg:py-6 px-0">
                {casoClinico && (
                    <div className="mb-4 px-4 py-2 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                        Caso clínico (debug): <strong>{casoClinico}</strong>
                    </div>
                )}
                <div className="mb-6">
                    <ReceitasIndexBackLink className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Receitas do Paciente
                    </ReceitasIndexBackLink>
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between mt-2">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">
                                {!isEditing
                                    ? 'Nova Receita'
                                    : viewMode
                                      ? tituloReceitaComSequencia('Receita', receita.numero)
                                      : tituloReceitaComSequencia('Editar Receita', receita.numero)}
                            </h1>
                            {isEditing && codigoRegistroPaciente !== '' && (
                                <p className="mt-1.5 text-sm text-gray-600">
                                    <span className="text-gray-500">Nº registro </span>
                                    <span className="font-semibold text-gray-900 tabular-nums">{codigoRegistroPaciente}</span>
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col gap-2 w-full lg:w-auto lg:justify-end">
                            {!isReadOnly && (isAutoSaving || lastSavedText) && (
                                <div className="text-xs text-gray-500 flex items-center gap-1 w-full justify-center sm:w-auto sm:justify-start sm:mr-1 order-first lg:order-none">
                                    {isAutoSaving ? (
                                        <>
                                            <svg className="animate-spin h-3 w-3" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            <span>Salvando...</span>
                                        </>
                                    ) : lastSavedText ? (
                                        <>
                                            <svg className="h-3 w-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Salvo às {lastSavedText}</span>
                                        </>
                                    ) : null}
                                </div>
                            )}

                            {/* Mobile: ações principais + "Mais ações" */}
                            <div className="flex flex-col gap-2 lg:hidden w-full">
                                {isEditing && viewMode && canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => setViewMode(false)}
                                        className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Editar Receita
                                    </button>
                                )}
                                {isEditing && viewMode && receita.status === 'finalizada' && (
                                    <a
                                        href={`/receitas/${receita.id}/pdf`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Download PDF
                                    </a>
                                )}
                                {!isReadOnly && (
                                    <button
                                        type="button"
                                        onClick={handleSubmit}
                                        disabled={processing || data.itens.length === 0}
                                        className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 text-sm"
                                    >
                                        {processing ? (
                                            <>
                                                <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Salvando...
                                            </>
                                        ) : (
                                            <>
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                Salvar
                                            </>
                                        )}
                                    </button>
                                )}
                                {(isEditing || currentReceitaId) && data.status === 'aberta' && (
                                    <button
                                        type="button"
                                        onClick={() => setShowFinalizarModal(true)}
                                        disabled={processing || data.itens.length === 0}
                                        className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Finalizar
                                    </button>
                                )}
                                {isEditing && (
                                    <details className="rounded-lg border border-gray-200 bg-gray-50">
                                        <summary className="min-h-[44px] px-3 py-2 cursor-pointer text-sm font-medium text-gray-700 list-none flex items-center justify-between [&::-webkit-details-marker]:hidden">
                                            <span>Mais ações</span>
                                            <svg className="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </summary>
                                        <div className="px-2 pb-2 pt-0 flex flex-col gap-2 border-t border-gray-200">
                                            <Tippy
                                                content={duplicarReceitaTippyContent}
                                                placement="top"
                                                theme="light-border"
                                                {...tippyAquisicaoProps}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => setShowDuplicarModal(true)}
                                                    className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm"
                                                >
                                                    Duplicar Receita
                                                </button>
                                            </Tippy>
                                            {canCancel && (
                                                <button
                                                    type="button"
                                                    onClick={() => setShowCancelarModal(true)}
                                                    className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm"
                                                >
                                                    Cancelar Receita
                                                </button>
                                            )}
                                        </div>
                                    </details>
                                )}
                            </div>

                            {/* Desktop: todas as ações em linha */}
                            <div className="hidden lg:flex lg:flex-row lg:flex-wrap gap-2 lg:justify-end">
                                {isEditing && viewMode && receita.status === 'finalizada' && (
                                    <a
                                        href={`/receitas/${receita.id}/pdf`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Download PDF
                                    </a>
                                )}

                                {isEditing && viewMode && canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => setViewMode(false)}
                                        className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Editar Receita
                                    </button>
                                )}

                                {!isReadOnly && (
                                    <button
                                        type="button"
                                        onClick={handleSubmit}
                                        disabled={processing || data.itens.length === 0}
                                        className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 text-sm"
                                    >
                                        {processing ? (
                                            <>
                                                <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Salvando...
                                            </>
                                        ) : (
                                            <>
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                Salvar
                                            </>
                                        )}
                                    </button>
                                )}

                                {(isEditing || currentReceitaId) && data.status === 'aberta' && (
                                    <button
                                        type="button"
                                        onClick={() => setShowFinalizarModal(true)}
                                        disabled={processing || data.itens.length === 0}
                                        className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Finalizar
                                    </button>
                                )}

                                {isEditing && (
                                    <>
                                        <Tippy
                                            content={duplicarReceitaTippyContent}
                                            placement="top"
                                            theme="light-border"
                                            {...tippyAquisicaoProps}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => setShowDuplicarModal(true)}
                                                className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors text-sm"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                Duplicar Receita
                                            </button>
                                        </Tippy>
                                        {canCancel && (
                                            <button
                                                type="button"
                                                onClick={() => setShowCancelarModal(true)}
                                                className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Cancelar Receita
                                            </button>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Dados Básicos - Compacto para Edição */}
                    {isEditing && selectedPaciente ? (
                        <div className="bg-white rounded-lg shadow-sm border border-gray-200 px-4 py-3">
                            <div className="flex flex-col gap-3 text-sm lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-6 lg:gap-y-1">
                                <div className="flex flex-col gap-1 min-w-0 w-full lg:w-auto lg:flex-row lg:items-center lg:gap-2">
                                    <span className="text-gray-500 flex-shrink-0">Paciente:</span>
                                    <span className="font-medium text-gray-900 break-words">{selectedPaciente.nome}</span>
                                    <span className="text-gray-400">({selectedPaciente.cpf})</span>
                                </div>
                                <div className="flex flex-col gap-1 min-w-0 w-full sm:flex-row sm:items-center sm:gap-2 lg:w-auto sm:max-w-[11rem]">
                                    <span className="text-gray-500 flex-shrink-0">Data:</span>
                                    <DatePickerField
                                        value={data.data_receita}
                                        onChange={(v) => setData('data_receita', v)}
                                        disabled={isReadOnly}
                                        compact
                                        error={errors.data_receita}
                                    />
                                </div>
                                <div className="flex flex-col gap-1 min-w-0 w-full lg:flex-row lg:items-center lg:gap-2 lg:w-auto lg:max-w-full">
                                    <span className="text-gray-500 flex-shrink-0">Médico:</span>
                                    <span className="font-medium text-gray-900 break-words">
                                        {nomeExibicaoSemTitulo(
                                            medicos?.find((m) => String(m.id) === String(data.medico_id))?.nome
                                                || receita?.medico?.nome
                                        ) || '-'}
                                    </span>
                                </div>
                                <div className={`inline-flex items-center shrink-0 px-2 py-0.5 rounded text-xs font-medium leading-tight ${
                                    data.status === 'finalizada' ? 'bg-green-100 text-green-700' :
                                    data.status === 'cancelada' ? 'bg-red-100 text-red-700' :
                                    'bg-gray-100 text-gray-600'
                                }`}>
                                    {data.status === 'finalizada' ? 'Finalizada' :
                                     data.status === 'cancelada' ? 'Cancelada' : 'Aberta'}
                                </div>
                                {!isReadOnly && selectedPaciente?.id && (
                                    <button
                                        type="button"
                                        onClick={() => setPatientDrawerOpen(true)}
                                        className="min-h-[36px] px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-800 hover:bg-gray-50 transition-colors"
                                    >
                                        Editar dados do paciente
                                    </button>
                                )}
                            </div>
                            {viewMode && data.status === 'aberta' && (
                                <p className="mt-3 text-sm text-gray-600 border-t border-gray-100 pt-3">
                                    <span className="font-medium text-gray-800">PDF da receita:</span>{' '}
                                    use o botão <span className="font-medium">Finalizar</span> acima. Depois, o botão{' '}
                                    <span className="font-medium">Download PDF</span> aparece aqui e no topo quando a receita estiver finalizada.
                                </p>
                            )}
                        </div>
                    ) : (
                        /* Form completo para Nova Receita */
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados da Receita</h2>
                            
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {/* Paciente */}
                                <div className="relative md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Paciente *</label>
                                    {selectedPaciente ? (
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 gap-2">
                                                <div className="min-w-0">
                                                    <span className="font-medium text-gray-900">{selectedPaciente.nome}</span>
                                                    <span className="text-sm text-gray-500 ml-2">{selectedPaciente.cpf}</span>
                                                </div>
                                                <div className="flex items-center gap-2 shrink-0">
                                                    {!isReadOnly && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPatientDrawerOpen(true)}
                                                            className="text-sm px-2 py-1 border border-emerald-300 rounded-md text-emerald-800 hover:bg-emerald-100/80"
                                                        >
                                                            Editar paciente
                                                        </button>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() => { setSelectedPaciente(null); setData('paciente_id', ''); }}
                                                        className="text-gray-400 hover:text-gray-600"
                                                        aria-label="Remover paciente"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    placeholder="Digite o nome ou CPF do paciente..."
                                                    value={searchPaciente}
                                                    onChange={(e) => setSearchPaciente(e.target.value)}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                                {loadingPacientes && (
                                                    <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                                        <svg className="animate-spin h-4 w-4 text-gray-400" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                        </svg>
                                                    </div>
                                                )}
                                            </div>
                                            {showPacienteDropdown && pacienteResults.length > 0 && (
                                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-auto">
                                                    {pacienteResults.map((paciente) => (
                                                        <button key={paciente.id} type="button" onClick={() => selectPaciente(paciente)}
                                                            className="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                                            <span className="font-medium text-gray-900">{paciente.nome}</span>
                                                            <span className="text-sm text-gray-500 ml-2">{paciente.cpf}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </>
                                    )}
                                    {errors.paciente_id && <p className="mt-1 text-sm text-red-600">{errors.paciente_id}</p>}
                                </div>

                                {/* Data */}
                                <div>
                                    <DatePickerField
                                        label="Data"
                                        value={data.data_receita}
                                        onChange={(v) => setData('data_receita', v)}
                                        disabled={isReadOnly}
                                        required
                                        error={errors.data_receita}
                                    />
                                </div>

                                {/* Medico — só alterável em nova receita */}
                                {isEditing ? (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Médico *</label>
                                        <div className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                            {medicos?.find((m) => String(m.id) === String(data.medico_id))?.nome
                                                || receita?.medico?.nome
                                                || '-'}
                                        </div>
                                    </div>
                                ) : !isMedico ? (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Médico *</label>
                                        <select value={data.medico_id} onChange={(e) => setData('medico_id', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                            disabled={isReadOnly || medicos?.length === 1}>
                                            <option value="">Selecione</option>
                                            {medicos?.map((medico) => (
                                                <option key={medico.id} value={medico.id}>
                                                    {nomeExibicaoSemTitulo(medico.nome)}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ) : (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Médico *</label>
                                        <div className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                            {medicos?.find((m) => String(m.id) === String(data.medico_id))?.nome || '-'}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Anotações internas (secretária/admin; não vão ao PDF) */}
                    {!isMedico && (
                        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <label className="block text-sm font-medium text-gray-700 mb-1">Anotações internas</label>
                            <textarea
                                value={data.anotacoes}
                                onChange={(e) => setData('anotacoes', e.target.value)}
                                disabled={isReadOnly}
                                rows={3}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                placeholder="Uso interno da equipe (não aparece no PDF da receita)."
                            />
                        </div>
                    )}

                    {/* Itens da Receita */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div className="flex justify-between items-center mb-2">
                            <h2 className="text-base font-semibold text-gray-900">Produtos</h2>
                        </div>

                        {bloqueadaParaEdicao && (
                            <div className="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-2">
                                <svg className="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span className="text-sm text-amber-800">
                                    Esta receita não pode ser editada pois o atendimento já está em produção ou finalizado.
                                </span>
                            </div>
                        )}

                        {errors.itens && (
                            <div className="mb-3 p-2 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                                {errors.itens}
                            </div>
                        )}

                        <div className="space-y-4">
                            {/* Produtos Recomendados */}
                            <div>
                                {data.itens.some(item => item.grupo === 'recomendado') && (
                                    <>
                                        <div className="flex items-center gap-2 mb-2 pb-1 border-b border-emerald-200">
                                            <div className="w-2.5 h-2.5 bg-emerald-500 rounded-full"></div>
                                            <span className="text-xs font-semibold text-emerald-700 uppercase tracking-wide">
                                                Recomendados para o Tratamento
                                            </span>
                                            <span className="text-xs text-gray-500">
                                            ({data.itens.filter(i => i.grupo === 'recomendado' && i.imprimir).length})
                                        </span>
                                    </div>
                                    {/* Cabeçalhos da tabela (desktop) */}
                                    <div className="hidden lg:flex items-center gap-2 py-2 px-2 border-b border-gray-200 mb-1">
                                        <div className="w-4 flex-shrink-0"></div>
                                        <div className="flex-[3] min-w-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Produto</span>
                                        </div>
                                        <div className="flex-[2] min-w-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Anotações</span>
                                        </div>
                                        <div className="w-36 flex-shrink-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase text-center w-full block">Data Aquisição</span>
                                        </div>
                                        <div className="w-14 flex-shrink-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Qtd</span>
                                        </div>
                                        <div className="w-16 flex-shrink-0 text-center">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Unid.</span>
                                        </div>
                                        {!isMedico && (
                                            <div className="w-20 flex-shrink-0 text-right">
                                                <span className="text-xs font-semibold text-gray-600 uppercase">Total</span>
                                            </div>
                                        )}
                                        {!isReadOnly && <div className="w-8 flex-shrink-0"></div>}
                                    </div>
                                    <div className="space-y-1 mb-2">
                                        {data.itens.map((item, index) => {
                                            if (item.grupo !== 'recomendado') return null;

                                            const itemOriginal = receita?.itens?.find((i) => i.id === item.id);
                                            const ultimaAquisicao = item.ultima_aquisicao || itemOriginal?.ultima_aquisicao;
                                            const datasAquisicao = item.datas_aquisicao || itemOriginal?.datas_aquisicao || [];
                                            const temHistorico = datasAquisicao && datasAquisicao.length > 1;

                                            return (
                                                <ReceitaFormItemRow
                                                    key={index}
                                                    item={item}
                                                    index={index}
                                                    variant="recomendado"
                                                    produtos={produtos}
                                                    isReadOnly={isReadOnly}
                                                    isMedico={isMedico}
                                                    ultimaAquisicao={ultimaAquisicao}
                                                    datasAquisicao={datasAquisicao}
                                                    temHistorico={temHistorico}
                                                    onUpdateItem={updateItem}
                                                    onRemoveItem={removeItem}
                                                    isLastItem={index === data.itens.length - 1}
                                                    lastItemRef={lastItemRef}
                                                    formatItemTotal={(it) =>
                                                        it.imprimir
                                                            ? new Intl.NumberFormat('pt-BR', {
                                                                  style: 'currency',
                                                                  currency: 'BRL',
                                                              }).format(calcularSubtotalItem(it))
                                                            : '-'
                                                    }
                                                />
                                            );
                                        })}
                                    </div>
                                    </>
                                )}
                                
                                {/* Add Product Button - Always in Recomendados section */}
                                {!isReadOnly && (
                                    <button
                                        type="button"
                                        onClick={addItem}
                                        className="w-full px-3 py-2 border border-dashed border-emerald-300 text-emerald-600 rounded hover:bg-emerald-50 hover:border-emerald-400 transition-colors flex items-center justify-center gap-2 text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                        </svg>
                                        Adicionar Produto
                                    </button>
                                )}
                            </div>

                                {/* Produtos Complementares */}
                                {data.itens.some(item => item.grupo === 'opcional') && (
                                    <div>
                                        <div className="flex items-center gap-2 mb-2 pb-1 border-b border-gray-300">
                                            <div className="w-2.5 h-2.5 bg-gray-400 rounded-full"></div>
                                            <span className="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                                Complementares
                                            </span>
                                            <span className="text-xs text-gray-500">
                                            ({data.itens.filter(i => i.grupo === 'opcional' && i.imprimir).length})
                                        </span>
                                    </div>
                                    {/* Cabeçalhos da tabela (desktop) */}
                                    <div className="hidden lg:flex items-center gap-2 py-2 px-2 border-b border-gray-200 mb-1">
                                        <div className="w-4 flex-shrink-0"></div>
                                        <div className="flex-[3] min-w-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Produto</span>
                                        </div>
                                        <div className="flex-[2] min-w-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Anotações</span>
                                        </div>
                                        <div className="w-36 flex-shrink-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase text-center w-full block">Data Aquisição</span>
                                        </div>
                                        <div className="w-14 flex-shrink-0">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Qtd</span>
                                        </div>
                                        <div className="w-16 flex-shrink-0 text-center">
                                            <span className="text-xs font-semibold text-gray-600 uppercase">Unid.</span>
                                        </div>
                                        {!isMedico && (
                                            <div className="w-20 flex-shrink-0 text-right">
                                                <span className="text-xs font-semibold text-gray-600 uppercase">Total</span>
                                            </div>
                                        )}
                                        {!isReadOnly && <div className="w-8 flex-shrink-0"></div>}
                                    </div>
                                    <div className="space-y-1">
                                        {data.itens.map((item, index) => {
                                            if (item.grupo !== 'opcional') return null;

                                            const itemOriginal = receita?.itens?.find((i) => i.id === item.id);
                                            const ultimaAquisicao = item.ultima_aquisicao || itemOriginal?.ultima_aquisicao;
                                            const datasAquisicao = item.datas_aquisicao || itemOriginal?.datas_aquisicao || [];
                                            const temHistorico = datasAquisicao && datasAquisicao.length > 1;

                                            return (
                                                <ReceitaFormItemRow
                                                    key={index}
                                                    item={item}
                                                    index={index}
                                                    variant="opcional"
                                                    produtos={produtos}
                                                    isReadOnly={isReadOnly}
                                                    isMedico={isMedico}
                                                    ultimaAquisicao={ultimaAquisicao}
                                                    datasAquisicao={datasAquisicao}
                                                    temHistorico={temHistorico}
                                                    onUpdateItem={updateItem}
                                                    onRemoveItem={removeItem}
                                                    isLastItem={index === data.itens.length - 1}
                                                    lastItemRef={lastItemRef}
                                                    formatItemTotal={(it) =>
                                                        it.imprimir
                                                            ? new Intl.NumberFormat('pt-BR', {
                                                                  style: 'currency',
                                                                  currency: 'BRL',
                                                              }).format(calcularSubtotalItem(it))
                                                            : '-'
                                                    }
                                                />
                                            );
                                        })}
                                    </div>
                                </div>
                                )}
                        </div>

                        {/* Totais - Hidden for medico users */}
                        {data.itens.length > 0 && !isMedico && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Desconto e Frete */}
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Desconto (%)
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={data.desconto_percentual}
                                                    onChange={(e) => setData('desconto_percentual', parseFloat(e.target.value) || 0)}
                                                    disabled={isReadOnly}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Valor Frete
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={data.valor_frete}
                                                    onChange={(e) => setData('valor_frete', parseFloat(e.target.value) || 0)}
                                                    disabled={isReadOnly}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                        </div>
                                        {data.desconto_percentual > 0 && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Motivo do Desconto
                                                </label>
                                                <input
                                                    type="text"
                                                    value={data.desconto_motivo}
                                                    onChange={(e) => setData('desconto_motivo', e.target.value)}
                                                    disabled={isReadOnly}
                                                    placeholder="Ex: Primeira compra, fidelidade..."
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    {/* Resumo */}
                                    <div className="bg-gray-50 rounded-lg p-4">
                                        <div className="space-y-2">
                                            <div className="flex justify-between text-sm">
                                                <span className="text-gray-600">Subtotal:</span>
                                                <span className="font-medium">
                                                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularSubtotal())}
                                                </span>
                                            </div>
                                            {data.desconto_percentual > 0 && (
                                                <div className="flex justify-between text-sm text-red-600">
                                                    <span>Desconto ({data.desconto_percentual}%):</span>
                                                    <span>- {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularDesconto())}</span>
                                                </div>
                                            )}
                                            {data.valor_frete > 0 && (
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600">Frete:</span>
                                                    <span>+ {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(data.valor_frete)}</span>
                                                </div>
                                            )}
                                            <div className="flex justify-between pt-2 border-t border-gray-300">
                                                <span className="font-semibold text-gray-900">Total:</span>
                                                <span className="text-xl font-bold text-emerald-600">
                                                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(calcularTotal())}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                </form>

                {/* Receitas Anteriores - Accordion */}
                {receitasAnteriores.length > 0 && (
                    <div className="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3">Outras Receitas do Paciente</h2>
                        <div className="space-y-2">
                            {receitasAnteriores.map((r) => (
                                <div key={r.id} className="border border-gray-200 rounded-lg overflow-hidden">
                                    {/* Accordion Header */}
                                    <div
                                        className="flex flex-col gap-3 p-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors lg:flex-row lg:items-center lg:justify-between"
                                        onClick={() => toggleReceitaExpanded(r.id)}
                                    >
                                        <div className="flex items-start gap-3 min-w-0">
                                            <svg
                                                className={`w-4 h-4 text-gray-500 transition-transform flex-shrink-0 mt-0.5 ${expandedReceitas[r.id] ? 'rotate-90' : ''}`}
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                            </svg>
                                            <div className="flex flex-col gap-1 min-w-0 lg:flex-row lg:flex-wrap lg:items-baseline lg:gap-x-3 lg:gap-y-0">
                                                <span className="text-base font-medium text-gray-900 tabular-nums">
                                                    {tituloReceitaComSequencia('Receita', r.numero)}
                                                </span>
                                                <span className="text-sm text-gray-500">{new Date(r.data_receita).toLocaleDateString('pt-BR')}</span>
                                                {r.medico && (
                                                    <span
                                                        className="text-sm text-gray-600 break-words"
                                                        title={nomeExibicaoSemTitulo(r.medico.nome)}
                                                    >
                                                        {nomeExibicaoSemTitulo(r.medico.nome)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 pl-7 lg:pl-0">
                                            {!isMedico && r.valor_total > 0 && (
                                                <span className="text-sm font-medium text-gray-700">
                                                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(r.valor_total)}
                                                </span>
                                            )}
                                            <span
                                                className={`px-2 py-0.5 text-sm rounded ${
                                                    r.status === 'finalizada'
                                                        ? 'bg-green-100 text-green-700'
                                                        : r.status === 'cancelada'
                                                          ? 'bg-red-100 text-red-700'
                                                          : 'bg-gray-100 text-gray-600'
                                                }`}
                                            >
                                                {r.status === 'finalizada' ? 'Finalizada' : r.status === 'cancelada' ? 'Cancelada' : 'Aberta'}
                                            </span>
                                            <Link
                                                href={`/receitas/${r.id}`}
                                                onClick={(e) => e.stopPropagation()}
                                                className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                title="Ver receita"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>
                                            <Link
                                                href={`/receitas/${r.id}/edit`}
                                                onClick={(e) => e.stopPropagation()}
                                                className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
                                                title="Editar receita"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </Link>
                                        </div>
                                    </div>
                                    
                                    {/* Accordion Content */}
                                    {expandedReceitas[r.id] && (
                                        <div className="p-3 border-t border-gray-200 bg-white">
                                            {/* Anotações internas */}
                                            {r.anotacoes && (
                                                <div className="mb-3 text-sm">
                                                    <span className="font-medium text-gray-700">Anotações internas:</span>
                                                    <span className="text-gray-600 ml-1">{formatAnotacaoDisplay(r.anotacoes)}</span>
                                                </div>
                                            )}
                                            
                                            {/* Produtos */}
                                            {r.itens && r.itens.filter((item) => item.imprimir).length > 0 ? (
                                                <div className="space-y-1">
                                                    <div className="text-sm font-medium text-gray-700 mb-2">
                                                        Produtos ({r.itens.filter((item) => item.imprimir).length})
                                                    </div>
                                                    <ResponsiveEntityList
                                                        desktop={
                                                            <div className="overflow-x-auto">
                                                                <table className="w-full text-sm">
                                                                    <thead className="bg-gray-50">
                                                                        <tr>
                                                                            <th className="text-left px-2 py-1 font-medium text-gray-600">Tipo</th>
                                                                            <th className="text-left px-2 py-1 font-medium text-gray-600">Produto</th>
                                                                            <th className="text-center px-2 py-1 font-medium text-gray-600">Qtd</th>
                                                                            {!isMedico && (
                                                                                <th className="text-right px-2 py-1 font-medium text-gray-600">Valor</th>
                                                                            )}
                                                                            <th className="text-left px-2 py-1 font-medium text-gray-600">Última Aquisição</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {r.itens
                                                                            .filter((item) => item.imprimir)
                                                                            .map((item, idx) => (
                                                                                <tr key={idx} className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                                                                                    <td className="px-2 py-1 text-gray-600">
                                                                                        {formatLocalUso(item.local_uso || item.produto?.local_uso)}
                                                                                    </td>
                                                                                    <td className="px-2 py-1 text-gray-900">
                                                                                        {item.produto?.nome || 'Produto não encontrado'}
                                                                                    </td>
                                                                                    <td className="px-2 py-1 text-center text-gray-600">{item.quantidade}</td>
                                                                                    {!isMedico && (
                                                                                        <td className="px-2 py-1 text-right text-gray-600">
                                                                                            {new Intl.NumberFormat('pt-BR', {
                                                                                                style: 'currency',
                                                                                                currency: 'BRL',
                                                                                            }).format(item.valor_unitario * item.quantidade)}
                                                                                        </td>
                                                                                    )}
                                                                                    <td className="px-2 py-1 text-gray-500">
                                                                                        <OutrasReceitaAquisicaoBadge item={item} tippyAquisicaoProps={tippyAquisicaoProps} />
                                                                                    </td>
                                                                                </tr>
                                                                            ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        }
                                                        mobile={
                                                            <div className="space-y-2">
                                                                {r.itens
                                                                    .filter((item) => item.imprimir)
                                                                    .map((item, idx) => (
                                                                        <div key={idx} className="rounded-lg border border-gray-200 p-3 text-sm max-w-full">
                                                                            <div className="font-medium text-gray-900 break-words">
                                                                                {item.produto?.nome || 'Produto não encontrado'}
                                                                            </div>
                                                                            <div className="text-gray-600 mt-1">
                                                                                {formatLocalUso(item.local_uso || item.produto?.local_uso)}
                                                                            </div>
                                                                            <div className="grid grid-cols-2 gap-x-3 gap-y-1 mt-2 text-sm">
                                                                                <div>
                                                                                    <span className="text-gray-500">Qtd</span>{' '}
                                                                                    <span className="font-medium text-gray-800">{item.quantidade}</span>
                                                                                </div>
                                                                                {!isMedico && (
                                                                                    <div className="text-right">
                                                                                        <span className="text-gray-500">Valor</span>{' '}
                                                                                        <span className="font-medium text-gray-800">
                                                                                            {new Intl.NumberFormat('pt-BR', {
                                                                                                style: 'currency',
                                                                                                currency: 'BRL',
                                                                                            }).format(item.valor_unitario * item.quantidade)}
                                                                                        </span>
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                            <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                                                                                <span className="text-gray-500 shrink-0">Aquisição</span>
                                                                                <OutrasReceitaAquisicaoBadge item={item} tippyAquisicaoProps={tippyAquisicaoProps} />
                                                                            </div>
                                                                        </div>
                                                                    ))}
                                                            </div>
                                                        }
                                                    />
                                                </div>
                                            ) : (
                                                <p className="text-sm text-gray-500">Nenhum produto nesta receita.</p>
                                            )}
                                            
                                            {/* Data de criação */}
                                            <div className="mt-3 pt-2 border-t border-gray-100 text-sm text-gray-500">
                                                Criado em: {new Date(r.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Modal de Confirmação - Finalizar */}
                {showFinalizarModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div className="flex min-h-full items-center justify-center p-4">
                            {/* Backdrop */}
                            <div 
                                className="fixed inset-0 bg-black/50 transition-opacity"
                                onClick={() => setShowFinalizarModal(false)}
                            />
                            
                            {/* Modal */}
                            <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 transform transition-all">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900">Finalizar Receita</h3>
                                </div>
                                
                                <p className="text-gray-600 mb-6">
                                    Deseja finalizar esta receita? Após finalizada, ela será enviada para o Call Center e não poderá mais ser editada.
                                </p>
                                
                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setShowFinalizarModal(false)}
                                        className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={finalizarReceita}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        Confirmar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
                {/* Modal Duplicar */}
                {showDuplicarModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div className="flex min-h-full items-center justify-center p-4">
                            <div className="fixed inset-0 bg-black/50" onClick={() => setShowDuplicarModal(false)} />
                            <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900">Duplicar Receita</h3>
                                </div>
                                <p className="text-gray-600 mb-6">Deseja duplicar esta receita? Será criada uma nova receita com os mesmos produtos.</p>
                                <div className="flex justify-end gap-3">
                                    <button onClick={() => setShowDuplicarModal(false)} className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                                        Cancelar
                                    </button>
                                    <button onClick={handleDuplicar} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        Confirmar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Modal Cancelar */}
                {showCancelarModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div className="flex min-h-full items-center justify-center p-4">
                            <div className="fixed inset-0 bg-black/50" onClick={() => setShowCancelarModal(false)} />
                            <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                        <svg className="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900">Cancelar Receita</h3>
                                </div>
                                <p className="text-gray-600 mb-6">Deseja cancelar esta receita? Esta ação não pode ser desfeita.</p>
                                <div className="flex justify-end gap-3">
                                    <button onClick={() => setShowCancelarModal(false)} className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                                        Voltar
                                    </button>
                                    <button onClick={handleCancelar} className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Cancelar Receita
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
            {toast && (
                <Toast
                    key={toast.key ?? `${toast.type}-${toast.message}`}
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
            <UnsavedChangesModal
                open={showUnsavedModal}
                onCancel={handleUnsavedCancel}
                onDiscard={handleUnsavedDiscard}
                onSave={handleUnsavedSave}
                saving={savingBeforeLeave}
            />
            <PatientDrawer
                isOpen={patientDrawerOpen}
                onClose={() => setPatientDrawerOpen(false)}
                paciente={selectedPaciente?.id ? selectedPaciente : null}
                onSave={() => {
                    setPatientDrawerOpen(false);
                    reloadPacienteNaReceita();
                }}
                isAdmin={receitaFormIsAdmin}
                showMedicoField={receitaFormCanSelectMedico}
                medicos={medicosPacienteDrawer}
                medicoRequired={receitaFormIsSecretaria}
                enableAutoSave
            />
        </DashboardLayout>
    );
}
