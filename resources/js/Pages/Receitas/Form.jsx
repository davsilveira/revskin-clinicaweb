import { useForm, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PatientDrawer from '@/Components/PatientDrawer';
import PacientesEncontrados from '@/Components/PacientesEncontrados';
import usePacientesCandidatos from '@/hooks/usePacientesCandidatos';
import { vincularPacienteAoMedico } from '@/utils/vincularPaciente';
import { documentoPaciente } from '@/utils/documentoPaciente';
import Toast from '@/Components/Toast';
import DatePickerField from '@/Components/Form/DatePickerField';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import debounce from 'lodash/debounce';
import useAutoSave from '@/hooks/useAutoSave';
import ReceitaFormItemRow from '@/Components/Receita/ReceitaFormItemRow';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import ReceitasIndexBackLink from '@/Components/ReceitasIndexBackLink';
import ClinicalToggleSwitch from '@/Components/AssistenteReceita/ClinicalToggleSwitch';
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

const duplicarBloqueioTippyContent = (
    <div className="text-xs text-gray-800 max-w-xs">
        Esta receita foi criada por duplicação. Finalize-a antes de criar outra cópia a partir dela.
    </div>
);

const tippyTexto = (texto) => (
    <div className="text-xs text-gray-800 max-w-xs">{texto}</div>
);

const pendenciaFinalizarTippyContent = tippyTexto(
    'Resolva as pendências abaixo para finalizar.',
);

const finalizarSemPersistirTippyContent = tippyTexto(
    'Salve a receita antes de finalizar.',
);

const finalizarSemItensTippyContent = tippyTexto(
    'Adicione ao menos um produto para finalizar.',
);

const pendenciaSalvarTippyContent = tippyTexto(
    'Resolva as pendências de produtos descontinuados para salvar.',
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

const localPacienteLabel = (paciente) => {
    if (!paciente) return '';
    const cidade = String(paciente.cidade || '').trim();
    const uf = String(paciente.uf || '').trim();
    if (cidade && uf) return `${cidade}/${uf}`;
    return cidade || uf;
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
    receitaFormIsCallcenter = false,
    receitaFormCanSelectMedico = true,
    defaultMedicoId,
    receitasAnteriores = [],
    bloqueadaParaEdicao = false,
    permiteEditarAnotacoesInternasItens = false,
    viewMode: initialViewMode = false,
    casoClinico = null,
}) {
    const { auth, flash, errors: pageErrors } = usePage().props;
    const isMedico = auth.user.role === 'medico';
    const isCallcenter = receitaFormIsCallcenter || auth.user.role === 'callcenter';
    const [toast, setToast] = useState(null);
    const isEditing = !!receita;
    const [viewMode, setViewMode] = useState(initialViewMode && isEditing);
    const isReadOnly = bloqueadaParaEdicao || viewMode || isCallcenter;
    const annotationsEditable =
        !!permiteEditarAnotacoesInternasItens && receita?.status === 'finalizada';
    const annotationsReadOnly = isReadOnly && !annotationsEditable;
    const [currentReceitaId, setCurrentReceitaId] = useState(receita?.id || null);
    const isFirstRender = useRef(true);
    const suppressAutosaveOnceRef = useRef(false);
    const [showFinalizarModal, setShowFinalizarModal] = useState(false);
    const [showDuplicarModal, setShowDuplicarModal] = useState(false);
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const [showCancelarBloqueadoModal, setShowCancelarBloqueadoModal] = useState(false);
    const [cancelarBloqueioMotivo, setCancelarBloqueioMotivo] = useState('');
    const [checkingCancelar, setCheckingCancelar] = useState(false);
    const [showEditarBloqueadoModal, setShowEditarBloqueadoModal] = useState(false);
    const [expandedReceitas, setExpandedReceitas] = useState(() => {
        const list = receitasAnteriores ?? [];
        const newest = list[0];
        return newest?.id != null ? { [newest.id]: true } : {};
    });
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
        cortesia: !!receita?.cortesia,
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
    const [noResultsPaciente, setNoResultsPaciente] = useState(false);
    const lastItemRef = useRef(null);
    const legadoRowRefs = useRef({});

    const produtoLegadoIds = useMemo(() => {
        const ids = new Set();
        (produtos || []).forEach((p) => {
            if (p?.legado_somente_leitura) ids.add(Number(p.id));
        });
        return ids;
    }, [produtos]);

    const itensLegadoIdx = useMemo(
        () =>
            data.itens
                .map((item, idx) => (item.produto_id && produtoLegadoIds.has(Number(item.produto_id)) ? idx : -1))
                .filter((idx) => idx >= 0),
        [data.itens, produtoLegadoIds],
    );
    const temPendenciaLegado = itensLegadoIdx.length > 0;

    const revelarPendenciasLegado = useCallback(() => {
        const idx = itensLegadoIdx[0];
        if (idx == null) return;
        const el = legadoRowRefs.current[idx];
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('ring-2', 'ring-red-400');
        setTimeout(() => el.classList.remove('ring-2', 'ring-red-400'), 1800);
        setToast({
            message: 'Substitua os produtos descontinuados (em vermelho) antes de salvar ou finalizar.',
            type: 'error',
            key: Date.now(),
        });
    }, [itensLegadoIdx]);

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

    const annotationFirstRenderRef = useRef(true);

    const performAnnotationSave = useCallback(async () => {
        if (!receita?.id) return;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(`/api/receitas/${receita.id}/itens-anotacoes`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({
                anotacoes: data.anotacoes ?? '',
                itens: data.itens
                    .filter((row) => row.id)
                    .map((row) => ({ id: row.id, anotacoes: row.anotacoes ?? '' })),
            }),
        });
        if (!response.ok) {
            let msg = 'Falha ao salvar anotações';
            try {
                const err = await response.json();
                if (err.message) msg = err.message;
            } catch {
                /* ignore */
            }
            throw new Error(msg);
        }
    }, [receita?.id, data.itens, data.anotacoes]);

    const canAnnotationAutoSave = annotationsEditable && !!receita?.id;

    const {
        lastSavedText: annotationLastSavedText,
        isSaving: isAnnotationSaving,
        hasUnsavedChanges: hasAnnotationUnsavedChanges,
        triggerAutoSave: triggerAnnotationAutoSave,
        cancelAutoSave: cancelAnnotationAutoSave,
        saveNow: saveAnnotationsNow,
        clearDirtyState: clearAnnotationDirtyState,
    } = useAutoSave(performAnnotationSave, 2000, canAnnotationAutoSave);

    const anotacoesKey = useMemo(
        () => JSON.stringify({
            receita: data.anotacoes ?? '',
            itens: data.itens.map((i) => [i.id, i.anotacoes ?? '']),
        }),
        [data.anotacoes, data.itens],
    );

    useEffect(() => {
        if (!annotationsEditable || !receita?.id) return;
        if (annotationFirstRenderRef.current) {
            annotationFirstRenderRef.current = false;
            return;
        }
        triggerAnnotationAutoSave();
    }, [anotacoesKey, annotationsEditable, receita?.id, triggerAnnotationAutoSave]);

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
    /** Visita pendente para modal «alterações não salvas». */
    const pendingVisitRef = useRef(null);
    /** Visita pendente para modais «finalizar / renovação». */
    const workflowExitVisitRef = useRef(null);
    /** Deixa passar o próximo GET (ex.: após “Salvar e sair” / “Sair sem salvar”) sem reabrir a modal se o dirty voltar no mesmo tick. */
    const skipUnsavedGuardOnceRef = useRef(false);
    const skipWorkflowExitGuardOnceRef = useRef(false);
    /** Saída intencional (cancelar / cancelar-e-duplicar): não bloquear Inertia nem beforeunload. */
    const allowLeaveWithoutGuardsRef = useRef(false);
    const [workflowExitKind, setWorkflowExitKind] = useState(null);

    /** Fechar aba: alerta igual à lógica de saída (finalizar / renovação em só leitura). */
    const needsWorkflowUnloadGuard =
        !!isEditing &&
        data.status !== 'cancelada' &&
        (data.status === 'aberta' || (data.status === 'finalizada' && isMedico && viewMode));

    const allowLeaveWithoutGuards = () => {
        allowLeaveWithoutGuardsRef.current = true;
        skipUnsavedGuardOnceRef.current = true;
        skipWorkflowExitGuardOnceRef.current = true;
        cancelAutoSave();
        cancelAnnotationAutoSave();
        clearDirtyState();
        clearAnnotationDirtyState();
    };

    // Intercept Inertia navigation: alterações não salvas e lembretes de fluxo (finalizar / nova receita)
    useEffect(() => {
        const removeListener = router.on('before', (event) => {
            if (allowLeaveWithoutGuardsRef.current) {
                return;
            }
            if (skipUnsavedGuardOnceRef.current) {
                skipUnsavedGuardOnceRef.current = false;
                return;
            }
            if (skipWorkflowExitGuardOnceRef.current) {
                skipWorkflowExitGuardOnceRef.current = false;
                return;
            }

            const visit = event.detail?.visit;
            const method = String(visit?.method ?? 'get').toLowerCase();
            if (!visit || method !== 'get') {
                return;
            }

            const anyUnsaved =
                (!isReadOnly && hasUnsavedChanges) ||
                (annotationsEditable && hasAnnotationUnsavedChanges);
            if (anyUnsaved) {
                event.preventDefault();
                cancelAutoSave();
                cancelAnnotationAutoSave();
                pendingVisitRef.current = visit;
                setShowUnsavedModal(true);
                return;
            }

            if (!isEditing) {
                return;
            }

            const status = data.status ?? receita?.status;
            if (status === 'cancelada') {
                return;
            }

            const medicoSomenteVisualizacao = isMedico && viewMode;

            if (status === 'aberta' && medicoSomenteVisualizacao) {
                event.preventDefault();
                workflowExitVisitRef.current = visit;
                setWorkflowExitKind('finalize_and_renew');
                return;
            }

            if (status === 'aberta') {
                event.preventDefault();
                workflowExitVisitRef.current = visit;
                setWorkflowExitKind('finalize');
                return;
            }

            if (status === 'finalizada' && medicoSomenteVisualizacao) {
                event.preventDefault();
                workflowExitVisitRef.current = visit;
                setWorkflowExitKind('renew');
            }
        });

        return removeListener;
    }, [
        hasUnsavedChanges,
        hasAnnotationUnsavedChanges,
        isReadOnly,
        annotationsEditable,
        cancelAutoSave,
        cancelAnnotationAutoSave,
        isEditing,
        data.status,
        receita?.status,
        isMedico,
        viewMode,
    ]);

    // Browser tab/close
    useEffect(() => {
        const anyUnsaved =
            (!isReadOnly && hasUnsavedChanges) ||
            (annotationsEditable && hasAnnotationUnsavedChanges);
        if (!anyUnsaved && !needsWorkflowUnloadGuard) {
            return;
        }

        const handler = (e) => {
            if (allowLeaveWithoutGuardsRef.current) {
                return;
            }
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [
        hasUnsavedChanges,
        hasAnnotationUnsavedChanges,
        isReadOnly,
        annotationsEditable,
        needsWorkflowUnloadGuard,
    ]);

    const handleWorkflowExitCancel = () => {
        setWorkflowExitKind(null);
        workflowExitVisitRef.current = null;
    };

    const handleWorkflowExitConfirm = () => {
        const visit = workflowExitVisitRef.current;
        workflowExitVisitRef.current = null;
        setWorkflowExitKind(null);
        if (visit) {
            skipWorkflowExitGuardOnceRef.current = true;
            router.visit(visit.url, { ...visit, onBefore: undefined });
        }
    };

    const handleUnsavedCancel = () => {
        setShowUnsavedModal(false);
        pendingVisitRef.current = null;
    };

    const handleUnsavedDiscard = () => {
        setShowUnsavedModal(false);
        cancelAutoSave();
        cancelAnnotationAutoSave();
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
            if (!isReadOnly && hasUnsavedChanges) {
                await saveNow();
            }
            if (annotationsEditable && hasAnnotationUnsavedChanges) {
                await saveAnnotationsNow();
            }
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
                setNoResultsPaciente(false);
                return;
            }
            setLoadingPacientes(true);
            setNoResultsPaciente(false);
            try {
                const response = await fetch(`/api/pacientes/search?q=${encodeURIComponent(term)}`);
                const results = await response.json();
                setPacienteResults(results);
                setShowPacienteDropdown(results.length > 0);
                setNoResultsPaciente(results.length === 0);
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
        setNoResultsPaciente(false);
    };

    /**
     * "Meus pacientes" (busca acima) não acha quem ainda não é meu — inclusive os clientes
     * importados do oList. Esta busca varre o sistema todo pelo nome; escolher um cria o
     * vínculo na hora, sem recadastrar.
     */
    const {
        candidatos: candidatosGlobais,
        total: totalCandidatosGlobais,
        buscando: buscandoCandidatosGlobais,
        limpar: limparCandidatosGlobais,
    } = usePacientesCandidatos({
        termo: searchPaciente,
        habilitado: !selectedPaciente && !isReadOnly,
        medicoId: data.medico_id || null,
    });

    /** Há cadastro do sistema listado como opção (fora os que já são meus e já apareceram acima). */
    const temCandidatosGlobais = candidatosGlobais.some(
        (c) => !c.ja_vinculado && !pacienteResults.some((p) => p.id === c.id)
    );

    const vinculandoRef = useRef(false);

    const usarPacienteDoSistema = async (candidato) => {
        if (vinculandoRef.current) return;
        vinculandoRef.current = true;
        try {
            const { paciente: vinculado, erro } = await vincularPacienteAoMedico(candidato.id, {
                medicoId: data.medico_id || null,
            });
            if (vinculado) {
                limparCandidatosGlobais();
                selectPaciente(vinculado);
            } else {
                setToast({ message: erro, type: 'error' });
            }
        } finally {
            vinculandoRef.current = false;
        }
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

    const firstInertiaErrorMessage = (errors) => {
        if (!errors || typeof errors !== 'object') return null;
        if (typeof errors === 'string') return errors;
        for (const value of Object.values(errors)) {
            if (typeof value === 'string' && value.trim()) return value;
            if (Array.isArray(value) && value[0]) return String(value[0]);
        }
        return null;
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

        const finalizarErrorHandler = (errors) => {
            markUnsaved();
            setToast({
                message:
                    firstInertiaErrorMessage(errors) ||
                    'Não foi possível finalizar a receita. Verifique os dados e tente novamente.',
                type: 'error',
                key: Date.now(),
            });
        };

        if (isCallcenter) {
            router.post(
                `/receitas/${receitaId}/finalizar`,
                {},
                {
                    ...inertiaPersistReceitaOptions,
                    onSuccess: () => {
                        setShowFinalizarModal(false);
                        bumpLastSaved();
                        setData('status', 'finalizada');
                        setViewMode(true);
                        setToast({ message: 'Receita finalizada com sucesso!', type: 'success' });
                    },
                    onError: finalizarErrorHandler,
                }
            );
            return;
        }

        router.put(`/receitas/${receitaId}`, {
            ...data,
            status: 'finalizada',
        }, {
            ...inertiaPersistReceitaOptions,
            onSuccess: () => {
                setShowFinalizarModal(false);
                bumpLastSaved();
                setData('status', 'finalizada');
                setViewMode(true);
                setToast({ message: 'Receita finalizada com sucesso!', type: 'success' });
            },
            onError: finalizarErrorHandler,
        });
    };

    const handleAbrirFinalizar = () => {
        if (temPendenciaLegado) {
            revelarPendenciasLegado();
            return;
        }
        setShowFinalizarModal(true);
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
            router.post(`/receitas/${receita.id}/copiar`, {}, { preserveScroll: true });
        };
        if (!viewMode && isEditing && !isCallcenter) {
            put(`/receitas/${receita.id}`, {
                ...inertiaPersistReceitaOptions,
                onSuccess: doCopy,
            });
        } else {
            doCopy();
        }
    };

    const handleCancelar = () => {
        const doCancel = () => {
            setShowCancelarModal(false);
            allowLeaveWithoutGuards();
            router.delete(`/receitas/${receita.id}`, {
                onError: (errors) => {
                    allowLeaveWithoutGuardsRef.current = false;
                    const msg =
                        errors?.message ||
                        errors?.reason ||
                        (typeof errors === 'string' ? errors : null) ||
                        'Não foi possível cancelar esta receita.';
                    setCancelarBloqueioMotivo(msg);
                    setShowCancelarBloqueadoModal(true);
                },
            });
        };
        if (!viewMode && isEditing && !isCallcenter) {
            put(`/receitas/${receita.id}`, { ...inertiaPersistReceitaOptions, onSuccess: doCancel });
        } else {
            doCancel();
        }
    };

    const verificarPodeCancelarOlist = async () => {
        const response = await fetch(`/api/receitas/${receita.id}/pode-cancelar`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            return {
                allowed: false,
                reason:
                    payload.reason ||
                    payload.message ||
                    'Não foi possível verificar se a receita pode ser cancelada.',
            };
        }
        if (payload.allowed === false) {
            return {
                allowed: false,
                reason:
                    payload.reason ||
                    'Esta receita não pode ser cancelada porque o pedido no oList já foi faturado ou entregue.',
            };
        }
        return { allowed: true, reason: null };
    };

    const abrirCancelarReceita = async () => {
        if (!receita?.id || checkingCancelar) {
            return;
        }
        setCheckingCancelar(true);
        try {
            const check = await verificarPodeCancelarOlist();
            if (!check.allowed) {
                setCancelarBloqueioMotivo(check.reason);
                setShowCancelarBloqueadoModal(true);
                return;
            }
            setShowCancelarModal(true);
        } catch {
            setCancelarBloqueioMotivo(
                'Não foi possível verificar o status do pedido no oList. Tente novamente em instantes.'
            );
            setShowCancelarBloqueadoModal(true);
        } finally {
            setCheckingCancelar(false);
        }
    };

    /**
     * Ações a partir da modal de edição bloqueada: não abre a confirmação de cancelar de novo
     * (evita “modal em cima de modal”); só checa oList e executa.
     */
    const executarAcaoDesdeEditarBloqueado = async (acao) => {
        if (!receita?.id || checkingCancelar) {
            return;
        }
        setShowEditarBloqueadoModal(false);
        setCheckingCancelar(true);
        try {
            const check = await verificarPodeCancelarOlist();
            if (!check.allowed) {
                setCancelarBloqueioMotivo(check.reason);
                setShowCancelarBloqueadoModal(true);
                return;
            }
            if (acao === 'cancelar_e_duplicar') {
                allowLeaveWithoutGuards();
                router.post(`/receitas/${receita.id}/cancelar-e-duplicar`, {}, {
                    onError: () => {
                        allowLeaveWithoutGuardsRef.current = false;
                    },
                });
                return;
            }
            allowLeaveWithoutGuards();
            router.delete(`/receitas/${receita.id}`, {
                onError: (errors) => {
                    allowLeaveWithoutGuardsRef.current = false;
                    const msg =
                        errors?.message ||
                        errors?.reason ||
                        (typeof errors === 'string' ? errors : null) ||
                        'Não foi possível cancelar esta receita.';
                    setCancelarBloqueioMotivo(msg);
                    setShowCancelarBloqueadoModal(true);
                },
            });
        } catch {
            setCancelarBloqueioMotivo(
                'Não foi possível verificar o status do pedido no oList. Tente novamente em instantes.'
            );
            setShowCancelarBloqueadoModal(true);
        } finally {
            setCheckingCancelar(false);
        }
    };

    const handleClickEditarReceita = () => {
        if (isMedico && receita?.status === 'finalizada') {
            setShowEditarBloqueadoModal(true);
            return;
        }
        setViewMode(false);
    };

    const handleSalvarAnotacoes = async () => {
        try {
            await saveAnnotationsNow();
            setToast({
                message: 'Anotações salvas.',
                type: 'success',
                key: Date.now(),
            });
        } catch (e) {
            setToast({
                message: e?.message || 'Não foi possível salvar as anotações.',
                type: 'error',
                key: Date.now(),
            });
        }
    };

    // Médicos e admins podem editar receitas em aberto; call center permanece somente leitura.
    const canEdit = isEditing && receita.status === 'aberta' && !bloqueadaParaEdicao && !isCallcenter;
    /** Médico em receita finalizada: botão Editar permanece visível, mas abre modal de bloqueio. */
    const showEditarReceitaButton =
        isEditing &&
        viewMode &&
        !isCallcenter &&
        receita.status !== 'cancelada' &&
        (canEdit || (isMedico && receita.status === 'finalizada'));
    const canChangeMedico =
        receitaFormIsAdmin && isEditing && receita.status === 'aberta' && !bloqueadaParaEdicao && !isCallcenter;
    const canCancel =
        isEditing &&
        !isCallcenter &&
        receita.status !== 'cancelada';

    const showMainSaveIndicator = !isReadOnly && (isAutoSaving || lastSavedText);
    const showAnnotationSaveIndicator =
        annotationsEditable && (isAnnotationSaving || annotationLastSavedText);
    const savingReceitaOuAnotacoes = isAutoSaving || isAnnotationSaving;
    const salvoAnotacoesOuReceitaText = lastSavedText || annotationLastSavedText;

    const pacienteCabecalho = receita?.paciente || selectedPaciente;
    const codigoRegistroPaciente =
        pacienteCabecalho?.codigo != null && String(pacienteCabecalho.codigo).trim() !== ''
            ? String(pacienteCabecalho.codigo).trim()
            : '';

    const pacienteIdParaAssistente =
        selectedPaciente?.id || receita?.paciente_id || initialPaciente?.id || null;
    const canOpenAssistenteReceita = (receitaFormIsAdmin || isMedico) && !!pacienteIdParaAssistente;

    const canDuplicar = isEditing && !(receita.receita_origem_id && data.status === 'aberta');
    const receitaOrigem = receita?.receita_origem;
    const duplicadaToastFiredRef = useRef(false);

    /** Receita já criada no servidor (edição ou autosave/salvar na criação). */
    const receitaJaPersistida = !!(isEditing || currentReceitaId);
    /** Mostrar Finalizar desde a criação (aberta), não só depois do 1º save — evita o botão “surgir” do nada. */
    const showFinalizarButton =
        data.status === 'aberta' && (!isReadOnly || receitaJaPersistida || isCallcenter);
    const podeFinalizar =
        receitaJaPersistida && data.itens.length > 0 && !processing && !temPendenciaLegado;
    const finalizarTippyContent = !receitaJaPersistida
        ? finalizarSemPersistirTippyContent
        : data.itens.length === 0
          ? finalizarSemItensTippyContent
          : pendenciaFinalizarTippyContent;

    useEffect(() => {
        if (duplicadaToastFiredRef.current) {
            return;
        }
        const params = new URLSearchParams(window.location.search);
        if (params.get('duplicada') === '1') {
            duplicadaToastFiredRef.current = true;
            setToast({ message: 'Receita criada a partir de uma cópia.', type: 'success', key: 'duplicada-hint' });
            params.delete('duplicada');
            const u = new URL(window.location.href);
            u.search = params.toString() ? `?${params.toString()}` : '';
            window.history.replaceState({}, '', u.pathname + u.search);
        }
    }, []);

    useEffect(() => {
        if (pageErrors?.copiar) {
            setToast({ message: pageErrors.copiar, type: 'error', key: `copiar-err-${Date.now()}` });
        }
    }, [pageErrors?.copiar]);

    const openDuplicarModal = () => {
        if (!canDuplicar) {
            return;
        }
        setShowDuplicarModal(true);
    };

    const duplicarTippy = canDuplicar ? duplicarReceitaTippyContent : duplicarBloqueioTippyContent;

    return (
        <DashboardLayout
            title={
                isEditing
                    ? tituloReceitaComSequencia(viewMode ? 'Receita' : 'Editar Receita', receita?.numero)
                    : 'Nova Receita'
            }
        >
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
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <h1 className="text-3xl font-bold text-gray-900">
                                    {!isEditing
                                        ? 'Assistente de Receita'
                                        : viewMode
                                          ? tituloReceitaComSequencia('Receita', receita.numero)
                                          : tituloReceitaComSequencia('Editar Receita', receita.numero)}
                                </h1>
                                {receitaFormIsAdmin && receita?.numero_origem && (
                                    <span className="text-sm text-gray-500">
                                        Nº origem CLW2:{' '}
                                        <span className="font-medium text-gray-700 tabular-nums">
                                            {receita.numero_origem}
                                        </span>
                                    </span>
                                )}
                                {(showMainSaveIndicator || showAnnotationSaveIndicator) && (
                                    <div className="text-xs text-gray-500 flex items-center gap-1 shrink-0">
                                        {savingReceitaOuAnotacoes ? (
                                            <>
                                                <svg className="animate-spin h-3 w-3 text-emerald-600" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                <span>Salvando...</span>
                                            </>
                                        ) : salvoAnotacoesOuReceitaText ? (
                                            <>
                                                <svg className="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span>Salvo às {salvoAnotacoesOuReceitaText}</span>
                                            </>
                                        ) : null}
                                    </div>
                                )}
                            </div>
                            {receitaOrigem && isEditing && (
                                <div className="mt-2 text-sm text-gray-600 flex flex-wrap items-center gap-1.5">
                                    <span className="text-gray-500">Duplicada de</span>
                                    <Link
                                        href={`/receitas/${receitaOrigem.id}`}
                                        className="font-medium text-amber-700 hover:text-amber-800 hover:underline"
                                    >
                                        {tituloReceitaComSequencia('Receita', receitaOrigem.numero)}
                                    </Link>
                                </div>
                            )}
                            {isEditing && codigoRegistroPaciente !== '' && (
                                <p className="mt-1.5 text-sm text-gray-600">
                                    <span className="text-gray-500">Nº registro </span>
                                    <span className="font-semibold text-gray-900 tabular-nums">{codigoRegistroPaciente}</span>
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col gap-2 w-full lg:w-auto lg:justify-end">
                            {/* Mobile: ações principais + "Mais ações" */}
                            <div className="flex flex-col gap-2 lg:hidden w-full">
                                {showEditarReceitaButton && (
                                    <button
                                        type="button"
                                        onClick={handleClickEditarReceita}
                                        className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-800 bg-white hover:bg-gray-50 transition-colors"
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
                                {annotationsEditable && (
                                    <button
                                        type="button"
                                        onClick={handleSalvarAnotacoes}
                                        disabled={isAnnotationSaving}
                                        className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 text-sm border border-emerald-600 text-emerald-900 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors disabled:opacity-60 font-medium"
                                    >
                                        {isAnnotationSaving ? (
                                            <>
                                                <svg className="animate-spin h-4 w-4 text-emerald-700" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Salvando anotações…
                                            </>
                                        ) : (
                                            <>
                                                <svg className="w-4 h-4 shrink-0 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                Salvar anotações
                                            </>
                                        )}
                                    </button>
                                )}
                                {!isReadOnly && (
                                    <Tippy
                                        content={pendenciaSalvarTippyContent}
                                        disabled={!temPendenciaLegado}
                                        placement="top"
                                        theme="light-border"
                                        {...tippyAquisicaoProps}
                                    >
                                        <span className="w-full inline-flex">
                                            <button
                                                type="button"
                                                onClick={handleSubmit}
                                                disabled={processing || data.itens.length === 0 || temPendenciaLegado}
                                                className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-800 bg-white hover:bg-gray-50 transition-colors disabled:opacity-50"
                                            >
                                                {processing ? (
                                                    <>
                                                        <svg className="animate-spin h-4 w-4 text-emerald-600" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                        </svg>
                                                        Salvando...
                                                    </>
                                                ) : (
                                                    <>
                                                        <svg className="w-4 h-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        Salvar
                                                    </>
                                                )}
                                            </button>
                                        </span>
                                    </Tippy>
                                )}
                                {showFinalizarButton && (
                                    <Tippy
                                        content={finalizarTippyContent}
                                        disabled={podeFinalizar}
                                        placement="top"
                                        theme="light-border"
                                        {...tippyAquisicaoProps}
                                    >
                                        <span className="w-full inline-flex">
                                            <button
                                                type="button"
                                                onClick={handleAbrirFinalizar}
                                                disabled={!podeFinalizar}
                                                className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 text-sm"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Finalizar
                                            </button>
                                        </span>
                                    </Tippy>
                                )}
                                {isEditing && canOpenAssistenteReceita && (
                                    <Link
                                        href={`/assistente-receita?paciente_id=${pacienteIdParaAssistente}`}
                                        className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                        </svg>
                                        Assistente de Receita
                                    </Link>
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
                                                content={duplicarTippy}
                                                placement="top"
                                                theme="light-border"
                                                {...tippyAquisicaoProps}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={openDuplicarModal}
                                                    aria-disabled={!canDuplicar}
                                                    className={`min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-amber-600 text-white rounded-lg text-sm ${
                                                        canDuplicar ? 'hover:bg-amber-700' : 'opacity-50 cursor-not-allowed'
                                                    }`}
                                                >
                                                    Duplicar Receita
                                                </button>
                                            </Tippy>
                                            {canCancel && (
                                                <button
                                                    type="button"
                                                    onClick={abrirCancelarReceita}
                                                    disabled={checkingCancelar}
                                                    className="min-h-[44px] flex w-full justify-center items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm disabled:opacity-60"
                                                >
                                                    {checkingCancelar ? 'Verificando…' : 'Cancelar Receita'}
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

                                {annotationsEditable && (
                                    <button
                                        type="button"
                                        onClick={handleSalvarAnotacoes}
                                        disabled={isAnnotationSaving}
                                        className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 text-sm border border-emerald-600 text-emerald-900 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors disabled:opacity-60 font-medium"
                                    >
                                        {isAnnotationSaving ? (
                                            <>
                                                <svg className="animate-spin h-4 w-4 text-emerald-700" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Salvando anotações…
                                            </>
                                        ) : (
                                            <>
                                                <svg className="w-4 h-4 shrink-0 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                Salvar anotações
                                            </>
                                        )}
                                    </button>
                                )}

                                {showEditarReceitaButton && (
                                    <button
                                        type="button"
                                        onClick={handleClickEditarReceita}
                                        className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-800 bg-white hover:bg-gray-50 transition-colors"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Editar Receita
                                    </button>
                                )}

                                {!isReadOnly && (
                                    <Tippy
                                        content={pendenciaSalvarTippyContent}
                                        disabled={!temPendenciaLegado}
                                        placement="top"
                                        theme="light-border"
                                        {...tippyAquisicaoProps}
                                    >
                                        <span className="inline-flex">
                                            <button
                                                type="button"
                                                onClick={handleSubmit}
                                                disabled={processing || data.itens.length === 0 || temPendenciaLegado}
                                                className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-800 bg-white hover:bg-gray-50 transition-colors disabled:opacity-50"
                                            >
                                                {processing ? (
                                                    <>
                                                        <svg className="animate-spin h-4 w-4 text-emerald-600" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                        </svg>
                                                        Salvando...
                                                    </>
                                                ) : (
                                                    <>
                                                        <svg className="w-4 h-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        Salvar
                                                    </>
                                                )}
                                            </button>
                                        </span>
                                    </Tippy>
                                )}

                                {showFinalizarButton && (
                                    <Tippy
                                        content={finalizarTippyContent}
                                        disabled={podeFinalizar}
                                        placement="top"
                                        theme="light-border"
                                        {...tippyAquisicaoProps}
                                    >
                                        <span className="inline-flex">
                                            <button
                                                type="button"
                                                onClick={handleAbrirFinalizar}
                                                disabled={!podeFinalizar}
                                                className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 text-sm"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Finalizar
                                            </button>
                                        </span>
                                    </Tippy>
                                )}

                                {isEditing && canOpenAssistenteReceita && (
                                    <Link
                                        href={`/assistente-receita?paciente_id=${pacienteIdParaAssistente}`}
                                        className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                        </svg>
                                        Assistente de Receita
                                    </Link>
                                )}

                                {isEditing && (
                                    <>
                                        <Tippy
                                            content={duplicarTippy}
                                            placement="top"
                                            theme="light-border"
                                            {...tippyAquisicaoProps}
                                        >
                                            <button
                                                type="button"
                                                onClick={openDuplicarModal}
                                                aria-disabled={!canDuplicar}
                                                className={`flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-amber-600 text-white rounded-lg transition-colors text-sm ${
                                                    canDuplicar ? 'hover:bg-amber-700' : 'opacity-50 cursor-not-allowed'
                                                }`}
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
                                                onClick={abrirCancelarReceita}
                                                disabled={checkingCancelar}
                                                className="flex sm:w-auto justify-center items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm disabled:opacity-60"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                {checkingCancelar ? 'Verificando…' : 'Cancelar Receita'}
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
                                    <span className="text-lg font-semibold text-gray-900 break-words">{selectedPaciente.nome}</span>
                                    {documentoPaciente(selectedPaciente) ? (
                                        <span className="text-gray-400">({documentoPaciente(selectedPaciente)})</span>
                                    ) : null}
                                </div>
                                <div className="flex flex-col gap-1 min-w-0 w-full sm:flex-row sm:items-center sm:gap-2 lg:w-auto sm:max-w-[11rem]">
                                    <span className="text-gray-500 flex-shrink-0">Data:</span>
                                    <DatePickerField
                                        value={data.data_receita}
                                        onChange={(v) => setData('data_receita', v)}
                                        disabled={isReadOnly}
                                        compact
                                        error={errors.data_receita}
                                        allowType
                                    />
                                </div>
                                {!isMedico && (
                                    <div className="flex flex-col gap-1 min-w-0 w-full lg:flex-row lg:items-center lg:gap-2 lg:w-auto lg:max-w-full">
                                        <span className="text-gray-500 flex-shrink-0">Médico:</span>
                                        {canChangeMedico ? (
                                            <select
                                                value={data.medico_id}
                                                onChange={(e) => setData('medico_id', e.target.value)}
                                                disabled={isReadOnly || !medicos?.length}
                                                className="min-h-[32px] px-2 py-1 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 max-w-full w-full sm:w-auto min-w-[12rem] disabled:bg-gray-100 disabled:cursor-not-allowed"
                                            >
                                                <option value="">Selecione</option>
                                                {medicos?.map((medico) => (
                                                    <option key={medico.id} value={medico.id}>
                                                        {nomeExibicaoSemTitulo(medico.nome)}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            <span className="font-medium text-gray-900 break-words">
                                                {nomeExibicaoSemTitulo(
                                                    medicos?.find((m) => String(m.id) === String(data.medico_id))?.nome
                                                        || receita?.medico?.nome
                                                ) || '-'}
                                            </span>
                                        )}
                                    </div>
                                )}
                                {receitaFormIsAdmin && (
                                    <div className="flex items-center gap-2 shrink-0">
                                        <ClinicalToggleSwitch
                                            checked={!!data.cortesia}
                                            onChange={(checked) => setData('cortesia', checked)}
                                            disabled={isReadOnly}
                                            aria-label="Cortesia"
                                        />
                                        <span className="text-sm font-medium text-gray-700">Cortesia</span>
                                    </div>
                                )}
                                <div className="flex flex-col gap-1 min-w-0 w-full sm:flex-row sm:items-center sm:gap-2 lg:w-auto">
                                    <span className="text-gray-500 flex-shrink-0">Cidade:</span>
                                    <span className="font-medium text-gray-900 break-words">
                                        {localPacienteLabel(selectedPaciente) || '—'}
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
                        </div>
                    ) : (
                        /* Dados da receita (criação e edição sem paciente vinculado).
                           Mesma lógica do Assistente de Receita: o médico responsável é
                           escolhido PRIMEIRO (admin/secretária) e depois o paciente. O
                           médico logado não escolhe médico nem data — só o paciente. */
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados da Receita</h2>

                            <div className="space-y-4">
                                {/* Médico responsável — só para quem pode escolher (admin/secretária); vem antes do paciente */}
                                {!isMedico && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Médico responsável <span className="text-red-500">*</span>
                                        </label>
                                        {isEditing && !canChangeMedico ? (
                                            <div className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                                {medicos?.find((m) => String(m.id) === String(data.medico_id))?.nome
                                                    || receita?.medico?.nome
                                                    || '-'}
                                            </div>
                                        ) : (
                                            <select
                                                value={data.medico_id}
                                                onChange={(e) => setData('medico_id', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                                disabled={isReadOnly || medicos?.length === 1}
                                            >
                                                <option value="">Selecione um médico...</option>
                                                {medicos?.map((medico) => (
                                                    <option key={medico.id} value={medico.id}>
                                                        {nomeExibicaoSemTitulo(medico.nome)}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        {errors.medico_id && <p className="mt-1 text-sm text-red-600">{errors.medico_id}</p>}
                                    </div>
                                )}

                                {/* Paciente */}
                                <div className="relative">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Paciente *</label>
                                    {selectedPaciente ? (
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 gap-2">
                                                <div className="min-w-0">
                                                    <span className="font-medium text-gray-900">{selectedPaciente.nome}</span>
                                                    <span className="text-sm text-gray-500 ml-2">{documentoPaciente(selectedPaciente)}</span>
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
                                                    className="w-full px-3 py-2 pr-9 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                                {/* Gira também durante a busca no sistema todo, senão some antes do resultado. */}
                                                {(loadingPacientes || buscandoCandidatosGlobais) && (
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
                                                            <span className="text-sm text-gray-500 ml-2">{documentoPaciente(paciente)}</span>
                                                            {/* Diferencia homônimos sem precisar abrir o cadastro */}
                                                            <span className="block text-xs text-gray-500">
                                                                {[
                                                                    paciente.data_nascimento
                                                                        ? `Nasc. ${String(paciente.data_nascimento).split('T')[0].split('-').reverse().join('/')}`
                                                                        : null,
                                                                    paciente.celular,
                                                                    paciente.email1,
                                                                ].filter(Boolean).join(' · ')}
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}

                                            {/* Existe no sistema (outro médico ou vindo do oList) mas ainda não é meu paciente */}
                                            {!isReadOnly && (
                                                <PacientesEncontrados
                                                    candidatos={candidatosGlobais}
                                                    total={totalCandidatosGlobais}
                                                    ocultarIds={pacienteResults.map((p) => p.id)}
                                                    ocultarVinculados
                                                    titulo="Já cadastrados no sistema, ainda não são seus pacientes"
                                                    rotuloAcao="Selecionar"
                                                    onSelecionar={usarPacienteDoSistema}
                                                />
                                            )}

                                            {/* Não encontrou o paciente → cadastrar (mesmo recurso do passo 1 do Assistente) */}
                                            {noResultsPaciente && !loadingPacientes && !isReadOnly && (
                                                <div className="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                    <div>
                                                        {/* Com candidatos listados acima, "nenhum encontrado" se contradiz. */}
                                                        <p className="text-sm text-amber-800 font-medium">
                                                            {temCandidatosGlobais ? 'Não é nenhum dos acima?' : 'Nenhum paciente encontrado'}
                                                        </p>
                                                        <p className="text-sm text-amber-700">Deseja cadastrar um novo paciente?</p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => setPatientDrawerOpen(true)}
                                                        className="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm shrink-0"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        Cadastrar paciente
                                                    </button>
                                                </div>
                                            )}
                                        </>
                                    )}
                                    {errors.paciente_id && <p className="mt-1 text-sm text-red-600">{errors.paciente_id}</p>}
                                </div>

                                {receitaFormIsAdmin && (
                                    <div className="flex items-center gap-2 pt-1">
                                        <ClinicalToggleSwitch
                                            checked={!!data.cortesia}
                                            onChange={(checked) => setData('cortesia', checked)}
                                            disabled={isReadOnly}
                                            aria-label="Cortesia"
                                        />
                                        <span className="text-sm font-medium text-gray-700">Cortesia</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Anotações internas (equipe e médico; não vão ao PDF) */}
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Anotações internas</label>
                        <textarea
                            value={data.anotacoes}
                            onChange={(e) => setData('anotacoes', e.target.value)}
                            disabled={annotationsReadOnly}
                            rows={3}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="Uso interno da equipe (não aparece no PDF da receita)."
                        />
                    </div>

                    {/* Itens da Receita */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-3 sm:p-4">
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

                        <div className="space-y-3">
                            {/* Produtos Recomendados */}
                            <div>
                                {data.itens.some(item => item.grupo === 'recomendado') && (
                                    <>
                                    {/* Cabeçalhos da tabela (desktop) */}
                                    <div className="hidden lg:flex items-center gap-2 py-1.5 px-2 border-b border-gray-200 mb-0.5">
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
                                    <div className="space-y-1 mb-1">
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
                                                    annotationsReadOnly={annotationsReadOnly}
                                                    isMedico={isMedico}
                                                    ultimaAquisicao={ultimaAquisicao}
                                                    datasAquisicao={datasAquisicao}
                                                    temHistorico={temHistorico}
                                                    onUpdateItem={updateItem}
                                                    onRemoveItem={removeItem}
                                                    isLastItem={index === data.itens.length - 1}
                                                    lastItemRef={lastItemRef}
                                                    registerRef={(i, el) => { legadoRowRefs.current[i] = el; }}
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
                                                    annotationsReadOnly={annotationsReadOnly}
                                                    isMedico={isMedico}
                                                    ultimaAquisicao={ultimaAquisicao}
                                                    datasAquisicao={datasAquisicao}
                                                    temHistorico={temHistorico}
                                                    onUpdateItem={updateItem}
                                                    onRemoveItem={removeItem}
                                                    isLastItem={index === data.itens.length - 1}
                                                    lastItemRef={lastItemRef}
                                                    registerRef={(i, el) => { legadoRowRefs.current[i] = el; }}
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
                                            {r.itens && r.itens.length > 0 ? (() => {
                                                const recomendados = r.itens.filter((item) => item.grupo !== 'opcional');
                                                const complementares = r.itens.filter((item) => item.grupo === 'opcional');
                                                const produtoLabel = (item) => {
                                                    const codigo = item.produto?.codigo;
                                                    const nome = item.produto?.nome || 'Produto não encontrado';
                                                    return codigo ? `${codigo} - ${nome}` : nome;
                                                };
                                                const renderDesktopRows = (itens, startIdx = 0) => itens.map((item, idx) => (
                                                    <tr key={startIdx + idx} className={(startIdx + idx) % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                                                        <td className="px-2 py-1 w-6 text-center">
                                                            <input type="checkbox" checked={!!item.imprimir} disabled className="rounded border-gray-300 text-emerald-600 cursor-default" />
                                                        </td>
                                                        <td className="px-2 py-1 text-gray-900">
                                                            {produtoLabel(item)}
                                                        </td>
                                                        <td className="px-2 py-1 text-center text-gray-600">{item.quantidade}</td>
                                                        {!isMedico && (
                                                            <td className="px-2 py-1 text-right text-gray-600">
                                                                {item.imprimir
                                                                    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.valor_unitario * item.quantidade)
                                                                    : '-'}
                                                            </td>
                                                        )}
                                                        <td className="px-2 py-1 text-gray-500">
                                                            <OutrasReceitaAquisicaoBadge item={item} tippyAquisicaoProps={tippyAquisicaoProps} />
                                                        </td>
                                                    </tr>
                                                ));
                                                const renderMobileCards = (itens) => itens.map((item, idx) => (
                                                    <div key={idx} className="rounded-lg border border-gray-200 p-3 text-sm max-w-full">
                                                        <div className="flex items-start gap-2">
                                                            <input type="checkbox" checked={!!item.imprimir} disabled className="mt-0.5 rounded border-gray-300 text-emerald-600 cursor-default" />
                                                            <div className="flex-1 min-w-0">
                                                                <div className="font-medium text-gray-900 break-words">
                                                                    {produtoLabel(item)}
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
                                                                                {item.imprimir
                                                                                    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.valor_unitario * item.quantidade)
                                                                                    : '-'}
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                                                                    <span className="text-gray-500 shrink-0">Aquisição</span>
                                                                    <OutrasReceitaAquisicaoBadge item={item} tippyAquisicaoProps={tippyAquisicaoProps} />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ));
                                                return (
                                                <div className="space-y-1">
                                                    <div className="text-sm font-medium text-gray-700 mb-2">
                                                        Produtos ({r.itens.length})
                                                    </div>
                                                    <ResponsiveEntityList
                                                        desktop={
                                                            <div className="overflow-x-auto">
                                                                <table className="w-full text-sm">
                                                                    <thead className="bg-gray-50">
                                                                        <tr>
                                                                            <th className="w-6 px-2 py-1"></th>
                                                                            <th className="text-left px-2 py-1 font-medium text-gray-600">Produto</th>
                                                                            <th className="text-center px-2 py-1 font-medium text-gray-600">Qtd</th>
                                                                            {!isMedico && (
                                                                                <th className="text-right px-2 py-1 font-medium text-gray-600">Valor</th>
                                                                            )}
                                                                            <th className="text-left px-2 py-1 font-medium text-gray-600">Última Aquisição</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {renderDesktopRows(recomendados)}
                                                                        {complementares.length > 0 && (
                                                                            <tr>
                                                                                <td colSpan={isMedico ? 4 : 5} className="px-2 py-1.5 text-xs font-semibold text-gray-500 uppercase bg-gray-100 border-t border-gray-200">
                                                                                    Complementares
                                                                                </td>
                                                                            </tr>
                                                                        )}
                                                                        {renderDesktopRows(complementares, recomendados.length)}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        }
                                                        mobile={
                                                            <div className="space-y-2">
                                                                {renderMobileCards(recomendados)}
                                                                {complementares.length > 0 && (
                                                                    <div className="text-xs font-semibold text-gray-500 uppercase py-1 border-t border-gray-200 mt-2">
                                                                        Complementares
                                                                    </div>
                                                                )}
                                                                {renderMobileCards(complementares)}
                                                            </div>
                                                        }
                                                    />
                                                </div>
                                                );
                                            })() : (
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
                                    Deseja finalizar esta receita? Após finalizada, ela será enviada ao Call Center. Os produtos e valores prescritos não poderão mais ser alterados; você poderá seguir editando as anotações internas da receita e por produto quando precisar.
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

                {/* Modal: cancelamento bloqueado pelo oList */}
                {showCancelarBloqueadoModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div className="flex min-h-full items-center justify-center p-4">
                            <div className="fixed inset-0 bg-black/50" onClick={() => setShowCancelarBloqueadoModal(false)} />
                            <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                        <svg className="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900">Cancelamento não permitido</h3>
                                </div>
                                <p className="text-gray-600 mb-6">
                                    {cancelarBloqueioMotivo ||
                                        'Esta receita não pode ser cancelada porque o pedido no oList já foi faturado ou entregue.'}
                                </p>
                                <div className="flex justify-end">
                                    <button
                                        type="button"
                                        onClick={() => setShowCancelarBloqueadoModal(false)}
                                        className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                                    >
                                        Entendi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Modal: edição bloqueada (receita finalizada) */}
                {showEditarBloqueadoModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto">
                        <div className="flex min-h-full items-center justify-center p-4">
                            <div className="fixed inset-0 bg-black/50" onClick={() => setShowEditarBloqueadoModal(false)} />
                            <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                        <svg className="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900">Edição não disponível</h3>
                                </div>
                                <p className="text-gray-600 mb-6">
                                    Esta receita não pode mais ser editada pelo ClinicaWeb. Para alterar, solicite
                                    diretamente ao suporte do ClinicaWeb ou cancele o pedido atual e crie uma
                                    duplicata.
                                </p>
                                <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setShowEditarBloqueadoModal(false)}
                                        disabled={checkingCancelar}
                                        className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-60"
                                    >
                                        Entendi
                                    </button>
                                    {canCancel && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={() => executarAcaoDesdeEditarBloqueado('cancelar')}
                                                disabled={checkingCancelar}
                                                className="px-4 py-2 border border-red-600 text-red-700 bg-white rounded-lg hover:bg-red-50 disabled:opacity-60"
                                            >
                                                {checkingCancelar ? 'Verificando…' : 'Cancelar'}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => executarAcaoDesdeEditarBloqueado('cancelar_e_duplicar')}
                                                disabled={checkingCancelar}
                                                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-60"
                                            >
                                                {checkingCancelar ? 'Verificando…' : 'Cancelar e Duplicar'}
                                            </button>
                                        </>
                                    )}
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
            {workflowExitKind && (
                <div className="fixed inset-0 z-[65] flex items-center justify-center overflow-y-auto p-4">
                    <button
                        type="button"
                        aria-label="Fechar"
                        className="fixed inset-0 z-[66] bg-black/50 transition-opacity cursor-default border-0 p-0"
                        onClick={handleWorkflowExitCancel}
                    />
                    <div className="relative z-[67] w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="flex-shrink-0 w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                <svg className="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900">
                                {workflowExitKind === 'renew'
                                    ? 'Sair sem nova receita?'
                                    : 'Sair sem finalizar receita?'}
                            </h3>
                        </div>
                        {(workflowExitKind === 'finalize' || workflowExitKind === 'finalize_and_renew') && (
                            <p
                                className={`text-gray-600 ${workflowExitKind === 'finalize' ? 'mb-6' : 'mb-4'}`}
                            >
                                Deseja sair sem finalizar receita? Para registrar a receita, é necessário clicar em{' '}
                                <strong>Finalizar</strong> — ao sair da tela sem finalizar, a receita não será enviada.
                            </p>
                        )}
                        {(workflowExitKind === 'renew' || workflowExitKind === 'finalize_and_renew') && (
                            <p className="text-gray-600 mb-6">
                                Deseja sair da página sem receitar nova receita? O call center não será acionado para revenda até
                                que seja emitida uma receita nova (por exemplo duplicando ou criando outra), mesmo que seja igual
                                à anterior.
                            </p>
                        )}
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                            <button
                                type="button"
                                onClick={handleWorkflowExitCancel}
                                className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                Permanecer
                            </button>
                            <button
                                type="button"
                                onClick={handleWorkflowExitConfirm}
                                className="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors"
                            >
                                Sair mesmo assim
                            </button>
                        </div>
                    </div>
                </div>
            )}
            <PatientDrawer
                isOpen={patientDrawerOpen}
                onClose={() => setPatientDrawerOpen(false)}
                paciente={selectedPaciente?.id ? selectedPaciente : null}
                onSave={(savedPaciente) => {
                    setPatientDrawerOpen(false);
                    // Paciente recém-cadastrado no fluxo de criação → seleciona automaticamente.
                    if (!selectedPaciente?.id && savedPaciente?.id) {
                        selectPaciente(savedPaciente);
                    } else {
                        reloadPacienteNaReceita();
                    }
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
