import { useForm, router, usePage } from '@inertiajs/react';
import { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import { flushSync } from 'react-dom';
import Drawer from '@/Components/Drawer';
import Input from '@/Components/Form/Input';
import DatePickerField from '@/Components/Form/DatePickerField';
import MaskedInput from '@/Components/Form/MaskedInput';
import Select from '@/Components/Form/Select';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import { validateCPF } from '@/utils/validations';
import useAutoSave from '@/hooks/useAutoSave';
import useDrawerUnsavedChanges from '@/hooks/useDrawerUnsavedChanges';
import countries from '@/utils/countries';
import debounce from 'lodash/debounce';
import cloneDeep from 'lodash/cloneDeep';
import isEqual from 'lodash/isEqual';
import { nomeExibicaoSemTitulo } from '@/utils/nomeExibicao';

const INITIAL_PACIENTE_FORM = {
    nome: '',
    codigo: '',
    indicado_por: '',
    cpf: '',
    data_nascimento: '',
    sexo: '',
    email1: '',
    celular: '',
    cep: '',
    endereco: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    uf: '',
    pais: 'Brasil',
    anotacoes: '',
    ativo: true,
    medico_id: '',
    telefones: [],
};

/** Chrome often ignores autocomplete="off"; non-standard tokens reduce address-profile autofill. */
const NO_AUTOFILL = {
    autoComplete: 'one-time-code',
    'data-lpignore': 'true',
    'data-1p-ignore': 'true',
    'data-form-type': 'other',
};

function normalizePatientData(d) {
    return {
        ...d,
        medico_id: d.medico_id === '' || d.medico_id == null ? '' : String(d.medico_id),
        telefones: (d.telefones || []).map((t) => ({
            numero: String(t.numero || '').trim(),
            tipo: t.tipo || '',
        })),
    };
}

/**
 * PatientDrawer - Drawer reutilizável para edição de pacientes
 * 
 * Props:
 * - isOpen: boolean - controla se o drawer está aberto
 * - onClose: function - callback ao fechar
 * - paciente: object|null - paciente para editar (null para novo)
 * - onSave: function - callback após salvar com sucesso
 * - isAdmin: boolean - mostrar campos de admin (médico responsável) - retrocompat
 * - showMedicoField: boolean - mostrar campo médico (admin e secretária)
 * - medicos: array - lista de médicos para Select (quando fornecida, usa Select em vez de busca)
 * - medicoRequired: boolean - tornar médico obrigatório (secretária) - bloqueia autosave e salvar sem médico
 * - enableAutoSave: boolean - em edição (com `paciente`), habilita autosave debounced. Em cadastro novo, não se usa.
 */
export default function PatientDrawer({
    isOpen,
    onClose,
    paciente = null,
    onSave,
    isAdmin = false,
    showMedicoField = null,
    medicos = [],
    medicoRequired = false,
    enableAutoSave = true,
}) {
    const showMedico = showMedicoField ?? isAdmin;
    const { props: pageProps } = usePage();
    const csrfForRequests = useMemo(() => {
        const t = pageProps?.csrfToken;
        if (typeof t === 'string' && t.length > 0) {
            return t;
        }
        if (typeof document === 'undefined') {
            return '';
        }
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }, [pageProps?.csrfToken]);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [loadingCep, setLoadingCep] = useState(false);
    const [cpfError, setCpfError] = useState(null);
    const [currentPacienteId, setCurrentPacienteId] = useState(paciente?.id || null);
    const isFirstRender = useRef(true);
    const [fieldErrors, setFieldErrors] = useState({});
    const [autofillNotice, setAutofillNotice] = useState(false);
    const [lookupNotice, setLookupNotice] = useState(null);
    const guardBrowserAutofillRef = useRef(false);
    const autofillGuardTimerRef = useRef(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({ ...INITIAL_PACIENTE_FORM });

    // Medico search states (for admin)
    const [searchMedico, setSearchMedico] = useState('');
    const [medicoResults, setMedicoResults] = useState([]);
    const [showMedicoDropdown, setShowMedicoDropdown] = useState(false);
    const [selectedMedico, setSelectedMedico] = useState(null);
    const [loadingMedicos, setLoadingMedicos] = useState(false);
    const [auditNames, setAuditNames] = useState({ created: null, updated: null });
    const [formBaseline, setFormBaseline] = useState(null);

    const currentPacienteIdRef = useRef(paciente?.id ?? null);
    const persistQueueRef = useRef(Promise.resolve());
    const isManualPersistRef = useRef(false);

    const runExclusive = useCallback((fn) => {
        const p = persistQueueRef.current
            .catch(() => {})
            .then(() => fn());
        persistQueueRef.current = p;
        return p;
    }, []);

    useEffect(() => {
        currentPacienteIdRef.current = currentPacienteId;
    }, [currentPacienteId]);

    // Initialize form data when paciente changes
    useEffect(() => {
        if (isOpen) {
            if (paciente) {
                guardBrowserAutofillRef.current = false;
                setAutofillNotice(false);
                setCurrentPacienteId(paciente.id);
                currentPacienteIdRef.current = paciente.id;
                setSelectedMedico(paciente.medico || null);
                setAuditNames({
                    created: paciente.created_by?.name ?? paciente.createdBy?.name ?? null,
                    updated: paciente.updated_by?.name ?? paciente.updatedBy?.name ?? null,
                });
                const initial = {
                    nome: paciente.nome || '',
                    codigo: paciente.codigo || '',
                    indicado_por: paciente.indicado_por || '',
                    cpf: paciente.cpf || '',
                    data_nascimento: paciente.data_nascimento ? paciente.data_nascimento.split('T')[0] : '',
                    sexo: paciente.sexo || '',
                    email1: paciente.email1 || '',
                    celular: paciente.celular || '',
                    cep: paciente.cep || '',
                    endereco: paciente.endereco || '',
                    numero: paciente.numero || '',
                    complemento: paciente.complemento || '',
                    bairro: paciente.bairro || '',
                    cidade: paciente.cidade || '',
                    uf: paciente.uf || '',
                    pais: paciente.pais || 'Brasil',
                    anotacoes: paciente.anotacoes || '',
                    ativo: paciente.ativo ?? true,
                    medico_id: paciente.medico_id || '',
                    telefones: paciente.telefones?.map(t => ({ numero: t.numero, tipo: t.tipo })) || [],
                };
                setData(initial);
                setFormBaseline(cloneDeep(normalizePatientData(initial)));
            } else {
                reset();
                setCurrentPacienteId(null);
                currentPacienteIdRef.current = null;
                setSelectedMedico(null);
                setAuditNames({ created: null, updated: null });
                setFormBaseline(cloneDeep(normalizePatientData(INITIAL_PACIENTE_FORM)));
                // Browser may dump a saved address (e.g. US) right after open; watch briefly and restore Brasil.
                guardBrowserAutofillRef.current = true;
                setAutofillNotice(false);
                if (autofillGuardTimerRef.current) {
                    clearTimeout(autofillGuardTimerRef.current);
                }
                autofillGuardTimerRef.current = setTimeout(() => {
                    guardBrowserAutofillRef.current = false;
                    autofillGuardTimerRef.current = null;
                }, 2000);
            }
            setShowDeleteConfirm(false);
            setCpfError(null);
            setFieldErrors({});
            setSearchMedico('');
            isFirstRender.current = true;
        } else {
            setFormBaseline(null);
            guardBrowserAutofillRef.current = false;
            setAutofillNotice(false);
            if (autofillGuardTimerRef.current) {
                clearTimeout(autofillGuardTimerRef.current);
                autofillGuardTimerRef.current = null;
            }
        }
    }, [isOpen, paciente]);

    // New patient only: if Chrome autofills a non-BR address profile, snap back to Brasil defaults.
    useEffect(() => {
        if (!isOpen || paciente || !guardBrowserAutofillRef.current) {
            return;
        }
        if (!data.pais || data.pais === 'Brasil') {
            return;
        }
        guardBrowserAutofillRef.current = false;
        if (autofillGuardTimerRef.current) {
            clearTimeout(autofillGuardTimerRef.current);
            autofillGuardTimerRef.current = null;
        }
        setData({
            pais: 'Brasil',
            cep: '',
            endereco: '',
            numero: '',
            complemento: '',
            bairro: '',
            cidade: '',
            uf: '',
        });
        setAutofillNotice(true);
    }, [isOpen, paciente, data.pais, setData]);

    // Debounced search for medicos
    const searchMedicosApi = useCallback(
        debounce(async (term) => {
            if (term.length < 2) {
                setMedicoResults([]);
                setShowMedicoDropdown(false);
                return;
            }
            setLoadingMedicos(true);
            try {
                const response = await fetch(`/api/medicos/search?q=${encodeURIComponent(term)}`);
                const results = await response.json();
                setMedicoResults(results);
                setShowMedicoDropdown(true);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingMedicos(false);
            }
        }, 300),
        []
    );

    const shouldShowMedicoField = showMedicoField ?? isAdmin;

    useEffect(() => {
        if (shouldShowMedicoField && !medicos?.length && searchMedico) {
            searchMedicosApi(searchMedico);
        }
    }, [searchMedico, searchMedicosApi, shouldShowMedicoField, medicos?.length]);

    const selectMedico = (medico) => {
        setSelectedMedico(medico);
        setData('medico_id', medico.id);
        setFieldErrors(prev => ({ ...prev, medico_id: null }));
        setSearchMedico('');
        setShowMedicoDropdown(false);
    };

    // Telefone management
    const addTelefone = () => {
        setData('telefones', [...data.telefones, { numero: '', tipo: 'Celular' }]);
    };

    const removeTelefone = (index) => {
        const newTelefones = [...data.telefones];
        newTelefones.splice(index, 1);
        setData('telefones', newTelefones);
    };

    const updateTelefone = (index, field, value) => {
        const newTelefones = [...data.telefones];
        newTelefones[index] = { ...newTelefones[index], [field]: value };
        setData('telefones', newTelefones);
    };

    const isBrazil = data.pais === 'Brasil';

    const persistedPacienteId = currentPacienteId ?? paciente?.id ?? null;
    const medicoLocked = Boolean(persistedPacienteId && data.medico_id);

    /** Só em edição: cadastro novo nunca chama o endpoint de rascunho. */
    const performAutoSave = useCallback(() => {
        if (!enableAutoSave || !paciente) {
            return Promise.resolve(undefined);
        }
        if (medicoRequired && showMedico && !data.medico_id) {
            return Promise.resolve(undefined);
        }
        if (!data.nome || data.nome.trim().length < 2) return Promise.resolve();
        if (!data.cpf || data.cpf.replace(/\D/g, '').length === 0) {
            return Promise.resolve();
        }
        if (!data.data_nascimento) return Promise.resolve();
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) {
            return Promise.resolve();
        }
        if (!data.email1 || !data.email1.trim()) return Promise.resolve();
        if (data.cpf && !validateCPF(data.cpf)) return Promise.resolve();

        return runExclusive(async () => {
            const telefonesValidos = data.telefones.filter((t) => t.numero && t.numero.trim());

            const response = await fetch('/api/pacientes/autosave', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfForRequests,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    _token: csrfForRequests,
                    id: paciente.id,
                    ...data,
                    telefones: telefonesValidos,
                }),
            });

            if (!response.ok) {
                if (response.status === 422) {
                    const errData = await response.json();
                    if (errData?.errors) {
                        setFieldErrors((prev) => {
                            const merged = { ...prev };
                            ['medico_id', 'cpf', 'codigo'].forEach((k) => {
                                if (errData.errors[k]?.[0]) {
                                    merged[k] = errData.errors[k][0];
                                }
                            });
                            return merged;
                        });
                        if (errData.errors.cpf?.[0]) {
                            setCpfError(errData.errors.cpf[0]);
                        }
                    } else if (errData?.message) {
                        setFieldErrors((prev) => ({ ...prev, _form: errData.message }));
                    }
                    return undefined;
                }
                throw new Error('Autosave failed');
            }

            const result = await response.json();
            if (result.id) {
                if (!currentPacienteId) {
                    setCurrentPacienteId(result.id);
                }
                currentPacienteIdRef.current = result.id;
            }
            if (result.created_by_name != null || result.updated_by_name != null) {
                setAuditNames((prev) => ({
                    created: result.created_by_name ?? prev.created,
                    updated: result.updated_by_name ?? prev.updated,
                }));
            }

            setFormBaseline(cloneDeep(normalizePatientData(data)));

            return result;
        });
    }, [data, currentPacienteId, medicoRequired, showMedico, enableAutoSave, paciente, runExclusive, csrfForRequests]);

    const canAutoSave = useCallback(() => {
        if (!enableAutoSave || !isOpen || !paciente) {
            return false;
        }
        if (!data.nome || data.nome.trim().length < 2) return false;
        if (!data.cpf || data.cpf.replace(/\D/g, '').length === 0) return false;
        if (!data.data_nascimento) return false;
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) return false;
        if (!data.email1 || !data.email1.trim()) return false;
        if (data.cpf && !validateCPF(data.cpf)) return false;
        if (medicoRequired && showMedico && !data.medico_id) return false;
        return true;
    }, [enableAutoSave, isOpen, data, medicoRequired, showMedico, paciente]);

    const {
        lastSaved,
        lastSavedText,
        isSaving: isAutoSaving,
        triggerAutoSave,
        cancelAutoSave,
    } = useAutoSave(performAutoSave, 2000, canAutoSave());

    // Trigger autosave when data changes
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (canAutoSave()) {
            triggerAutoSave();
        }
    }, [data, isOpen, enableAutoSave, canAutoSave, triggerAutoSave]);

    const forceClose = useCallback(() => {
        cancelAutoSave();
        onClose?.();
        if (lastSaved) {
            router.reload({ only: ['pacientes'] });
        }
    }, [cancelAutoSave, onClose, lastSaved]);

    const [isSaving, setIsSaving] = useState(false);

    const persistPatient = useCallback(() => {
        if (isManualPersistRef.current) {
            return Promise.resolve(false);
        }
        isManualPersistRef.current = true;

        return runExclusive(async () => {
            try {
                cancelAutoSave();
                setCpfError(null);
                const newErrors = {};
                let hasErrors = false;

                if (!data.nome || data.nome.trim().length < 2) {
                    newErrors.nome = 'O nome completo é obrigatório.';
                    hasErrors = true;
                }

                if (!data.cpf || data.cpf.replace(/\D/g, '').length === 0) {
                    setCpfError('CPF é obrigatório.');
                    newErrors.cpf = 'CPF é obrigatório.';
                    hasErrors = true;
                } else if (!validateCPF(data.cpf)) {
                    setCpfError('CPF inválido. Por favor, verifique os números digitados.');
                    newErrors.cpf = 'CPF inválido.';
                    hasErrors = true;
                }

                if (!data.data_nascimento) {
                    newErrors.data_nascimento = 'Data de nascimento é obrigatória.';
                    hasErrors = true;
                }

                if (!data.celular || data.celular.replace(/\D/g, '').length < 10) {
                    newErrors.celular = 'Celular é obrigatório.';
                    hasErrors = true;
                }

                if (!data.email1 || !data.email1.trim()) {
                    newErrors.email1 = 'E-mail é obrigatório.';
                    hasErrors = true;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email1.trim())) {
                    newErrors.email1 = 'Informe um e-mail válido.';
                    hasErrors = true;
                }
                if (medicoRequired && showMedico && !data.medico_id) {
                    newErrors.medico_id = 'Selecione o médico responsável.';
                    hasErrors = true;
                }
                if (hasErrors) {
                    setFieldErrors(newErrors);
                    return false;
                }

                setFieldErrors({});
                setIsSaving(true);

                try {
                    const telefonesValidos = data.telefones.filter((t) => t.numero && t.numero.trim());
                    const id = paciente?.id ?? null;
                    const url = id ? `/pacientes/${id}` : '/pacientes';
                    const method = id ? 'PUT' : 'POST';

                    if (import.meta.env.DEV) {
                        // eslint-disable-next-line no-console
                        console.groupCollapsed('[paciente] save', { id, method, url });
                    }

                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfForRequests,
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        redirect: 'manual',
                        body: JSON.stringify({
                            _token: csrfForRequests,
                            ...data,
                            telefones: telefonesValidos,
                        }),
                    });

                    const contentType = response.headers.get('content-type') || '';
                    let body = null;
                    if (contentType.includes('application/json')) {
                        try {
                            body = await response.json();
                        } catch {
                            body = null;
                        }
                    }

                    if (import.meta.env.DEV) {
                        // eslint-disable-next-line no-console
                        console.log({ status: response.status, redirected: response.redirected, body });
                        // eslint-disable-next-line no-console
                        console.groupEnd();
                    }

                    if (response.status === 419) {
                        setFieldErrors({
                            _form: 'Não foi possível validar o pedido (sessão/CSRF). Se voltar a acontecer, recarregue a página (F5) e tente de novo.',
                        });
                        return false;
                    }

                    if (response.redirected) {
                        setFieldErrors({
                            _form: 'Não foi possível salvar. Verifique os dados (ex.: CPF) e tente novamente.',
                        });
                        return false;
                    }

                    if (response.ok && body?.success === true) {
                        setFieldErrors({});
                        setCpfError(null);
                        flushSync(() => {
                            setFormBaseline(cloneDeep(normalizePatientData(data)));
                        });
                        onSave?.();
                        return true;
                    }

                    if (response.status === 422) {
                        if (body?.errors) {
                            const backendErrors = {};
                            Object.keys(body.errors).forEach((key) => {
                                backendErrors[key] = body.errors[key][0];
                            });
                            setFieldErrors((prev) => ({ ...prev, ...backendErrors }));
                            if (backendErrors.cpf) {
                                setCpfError(backendErrors.cpf);
                            }
                        } else if (body?.message) {
                            setFieldErrors((prev) => ({ ...prev, _form: body.message }));
                        }
                        return false;
                    }

                    if (body?.message) {
                        setFieldErrors((prev) => ({ ...prev, _form: body.message }));
                        return false;
                    }

                    setFieldErrors((prev) => ({
                        ...prev,
                        _form: 'Não foi possível salvar. Tente novamente.',
                    }));
                    return false;
                } catch (error) {
                    console.error('Error saving patient:', error);
                    setFieldErrors((prev) => ({
                        ...prev,
                        _form: 'Não foi possível salvar. Tente novamente.',
                    }));
                    return false;
                } finally {
                    setIsSaving(false);
                }
            } finally {
                isManualPersistRef.current = false;
            }
        });
    }, [data, paciente, medicoRequired, showMedico, onSave, cancelAutoSave, runExclusive, csrfForRequests]);

    const saveBeforeClose = useCallback(async () => persistPatient(), [persistPatient]);

    const isDirty = useMemo(() => {
        if (!isOpen || !formBaseline) return false;
        return !isEqual(normalizePatientData(data), formBaseline);
    }, [isOpen, formBaseline, data]);

    /** Mesmas regras que persistPatient / canAutoSave: só habilita Salvar com obrigatórios válidos (como Usuários). */
    const isManualSaveValid = useMemo(() => {
        if (!isOpen) return false;
        if (!data.nome || data.nome.trim().length < 2) return false;
        if (!data.cpf || data.cpf.replace(/\D/g, '').length === 0) return false;
        if (!validateCPF(data.cpf)) return false;
        if (!data.data_nascimento) return false;
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) return false;
        if (!data.email1 || !data.email1.trim()) return false;
        if (medicoRequired && showMedico && !data.medico_id) return false;
        return true;
    }, [isOpen, data, medicoRequired, showMedico]);

    const {
        requestClose,
        showUnsavedModal,
        savingBeforeLeave,
        handleUnsavedCancel,
        handleUnsavedDiscard,
        handleUnsavedSave,
    } = useDrawerUnsavedChanges({
        isOpen,
        /** Só avisar ao sair com alterações pendentes na edição; inclusão (novo) não abre o modal. */
        isDirty: isDirty && Boolean(paciente),
        onConfirmClose: forceClose,
        saveBeforeClose,
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        await persistPatient();
    };

    /**
     * Opção 2: ao informar um CPF já cadastrado (por qualquer médico), pré-preenche os
     * DADOS COMPARTILHADOS do paciente. Os campos exclusivos deste médico ficam em
     * branco (a serem preenchidos). Só roda em cadastro novo (sem paciente carregado).
     */
    const doCpfLookup = useCallback(async () => {
        if (paciente || currentPacienteIdRef.current) return; // só em cadastro novo
        const digits = String(data.cpf || '').replace(/\D/g, '');
        if (digits.length !== 11) return;

        try {
            const params = new URLSearchParams({ cpf: digits });
            if (data.medico_id) params.set('medico_id', String(data.medico_id));
            const resp = await fetch(`/api/pacientes/lookup?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json.found || !json.paciente) {
                setLookupNotice(null);
                return;
            }

            const p = json.paciente;
            const shared = {
                nome: p.nome || '', rg: p.rg || '', data_nascimento: p.data_nascimento || '',
                sexo: p.sexo || '', fototipo: p.fototipo || '',
                email1: p.email1 || '', email2: p.email2 || '',
                telefone1: p.telefone1 || '', celular: p.celular || '', telefone3: p.telefone3 || '',
                tipo_endereco: p.tipo_endereco || '', endereco: p.endereco || '', numero: p.numero || '',
                complemento: p.complemento || '', bairro: p.bairro || '', cidade: p.cidade || '',
                uf: p.uf || '', pais: p.pais || '', cep: p.cep || '',
            };
            setData((prev) => ({ ...prev, ...shared }));

            setLookupNotice(
                json.ja_vinculado
                    ? 'Este paciente já está cadastrado para este médico. Verifique antes de salvar novamente.'
                    : 'Paciente já cadastrado no sistema — os dados principais foram carregados. Preencha abaixo os dados exclusivos deste médico.'
            );
        } catch (_) {
            // silencioso: lookup é conveniência, não bloqueia o cadastro
        }
    }, [paciente, data.cpf, data.medico_id, setData]);

    const handleDelete = () => {
        if (paciente) {
            router.delete(`/pacientes/${paciente.id}`, {
                onSuccess: () => {
                    onSave?.();
                    forceClose();
                },
            });
        }
    };

    const buscarCep = useCallback(async () => {
        const cepLimpo = data.cep?.replace(/\D/g, '');
        if (!cepLimpo || cepLimpo.length < 8) return;

        setLoadingCep(true);
        try {
            const response = await fetch(`/api/cep/${cepLimpo}`);
            const result = await response.json();
            if (result.success) {
                setData(prev => ({
                    ...prev,
                    endereco: result.data.logradouro || '',
                    bairro: result.data.bairro || '',
                    cidade: result.data.localidade || '',
                    uf: result.data.uf || '',
                }));
            }
        } catch (error) {
            console.error('Erro ao buscar CEP:', error);
        } finally {
            setLoadingCep(false);
        }
    }, [data.cep]);

    return (
        <>
        <Drawer
            isOpen={isOpen}
            onClose={requestClose}
            title={paciente ? 'Editar Paciente' : 'Novo Paciente'}
        >
            <form onSubmit={handleSubmit} className="flex flex-col h-full" autoComplete="off">
                <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                    {fieldErrors._form && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                            {fieldErrors._form}
                        </div>
                    )}
                    {lookupNotice && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                            {lookupNotice}
                            <button
                                type="button"
                                className="ml-2 underline hover:no-underline"
                                onClick={() => setLookupNotice(null)}
                            >
                                Ok
                            </button>
                        </div>
                    )}
                    {autofillNotice && (
                        <div className="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">
                            O navegador tentou preencher um endereço salvo (ex.: EUA). Mantivemos <strong>Brasil</strong> como padrão.
                            Você pode alterar o país manualmente se precisar.
                            <button
                                type="button"
                                className="ml-2 underline hover:no-underline"
                                onClick={() => setAutofillNotice(false)}
                            >
                                Fechar
                            </button>
                        </div>
                    )}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Input
                                label="Nome Completo"
                                value={data.nome}
                                onChange={(e) => {
                                    setData('nome', e.target.value);
                                    setFieldErrors(prev => ({ ...prev, nome: null }));
                                }}
                                error={fieldErrors.nome || errors.nome}
                                required
                                {...NO_AUTOFILL}
                                name="revskin_paciente_nome"
                            />
                        </div>
                        <MaskedInput
                            label="CPF"
                            mask="000.000.000-00"
                            value={data.cpf}
                            onChange={(e) => {
                                setData('cpf', e.target.value);
                                setCpfError(null);
                                setFieldErrors((prev) => ({ ...prev, cpf: null }));
                                if (lookupNotice) setLookupNotice(null);
                            }}
                            onBlur={doCpfLookup}
                            error={cpfError || fieldErrors.cpf || errors.cpf}
                            placeholder="000.000.000-00"
                            required
                            {...NO_AUTOFILL}
                            name="revskin_paciente_cpf"
                        />
                        <DatePickerField
                            label="Data de Nascimento"
                            value={data.data_nascimento}
                            onChange={(v) => {
                                setData('data_nascimento', v);
                                setFieldErrors((prev) => ({ ...prev, data_nascimento: null }));
                            }}
                            error={fieldErrors.data_nascimento || errors.data_nascimento}
                            required
                            allowType
                        />
                        <Select
                            label="Sexo"
                            value={data.sexo}
                            onChange={(e) => setData('sexo', e.target.value)}
                            options={[
                                { value: '', label: 'Selecione' },
                                { value: 'M', label: 'Masculino' },
                                { value: 'F', label: 'Feminino' },
                            ]}
                            {...NO_AUTOFILL}
                            name="revskin_paciente_sexo"
                        />
                        <div className="col-span-2 sm:col-span-1">
                            <Input
                                label="E-mail"
                                type="text"
                                value={data.email1}
                                onChange={(e) => {
                                    setData('email1', e.target.value);
                                    setFieldErrors(prev => ({ ...prev, email1: null }));
                                }}
                                error={fieldErrors.email1 || errors.email1}
                                required
                                {...NO_AUTOFILL}
                                inputMode="email"
                                name="revskin_paciente_email"
                            />
                        </div>
                        <div className="col-span-2">
                            <Select
                                label="País"
                                value={data.pais}
                                onChange={(e) => {
                                    // Manual country change — stop treating as browser autofill.
                                    guardBrowserAutofillRef.current = false;
                                    setData('pais', e.target.value);
                                }}
                                onFocus={() => {
                                    guardBrowserAutofillRef.current = false;
                                }}
                                onPointerDown={() => {
                                    guardBrowserAutofillRef.current = false;
                                }}
                                options={countries}
                                {...NO_AUTOFILL}
                                name="revskin_paciente_pais"
                            />
                        </div>
                        {isBrazil ? (
                            <MaskedInput
                                label="Celular"
                                mask="(00) 00000-0000"
                                value={data.celular}
                                onChange={(e) => {
                                    setData('celular', e.target.value);
                                    setFieldErrors(prev => ({ ...prev, celular: null }));
                                }}
                                placeholder="(00) 00000-0000"
                                error={fieldErrors.celular || errors.celular}
                                required
                                {...NO_AUTOFILL}
                                name="revskin_paciente_celular"
                            />
                        ) : (
                            <Input
                                label="Celular"
                                value={data.celular}
                                onChange={(e) => {
                                    setData('celular', e.target.value);
                                    setFieldErrors(prev => ({ ...prev, celular: null }));
                                }}
                                placeholder="Número com código do país"
                                error={fieldErrors.celular || errors.celular}
                                required
                                {...NO_AUTOFILL}
                                name="revskin_paciente_celular"
                            />
                        )}
                    </div>

                    {/* Multiple Phones Section */}
                    <div className="border-t pt-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-medium text-gray-900">Telefones Adicionais</h3>
                            <button
                                type="button"
                                onClick={addTelefone}
                                className="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Adicionar
                            </button>
                        </div>
                        {data.telefones?.length > 0 ? (
                            <div className="space-y-3">
                                {data.telefones.map((tel, index) => (
                                    <div key={index} className="flex gap-2 items-end">
                                        <div className="flex-1">
                                            <Select
                                                label={index === 0 ? "Tipo" : ""}
                                                value={tel.tipo}
                                                onChange={(e) => updateTelefone(index, 'tipo', e.target.value)}
                                                options={[
                                                    { value: '', label: 'Tipo' },
                                                    { value: 'Residencial', label: 'Residencial' },
                                                    { value: 'Comercial', label: 'Comercial' },
                                                    { value: 'Celular', label: 'Celular' },
                                                    { value: 'WhatsApp', label: 'WhatsApp' },
                                                    { value: 'Recado', label: 'Recado' },
                                                    { value: 'Outro', label: 'Outro' },
                                                ]}
                                                autoComplete="off"
                                                name={`revskin_paciente_tel_tipo_${index}`}
                                            />
                                        </div>
                                        <div className="flex-[2]">
                                            {isBrazil ? (
                                                <MaskedInput
                                                    label={index === 0 ? "Número" : ""}
                                                    mask={[{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }]}
                                                    dispatch={(appended, dynamicMasked) => {
                                                        const number = (dynamicMasked.value + appended).replace(/\D/g, '');
                                                        return dynamicMasked.compiledMasks[number.length > 10 ? 1 : 0];
                                                    }}
                                                    value={tel.numero}
                                                    onAccept={(value) => updateTelefone(index, 'numero', value)}
                                                    placeholder="(00) 00000-0000"
                                                    autoComplete="off"
                                                    name={`revskin_paciente_tel_extra_${index}`}
                                                />
                                            ) : (
                                                <Input
                                                    label={index === 0 ? "Número" : ""}
                                                    value={tel.numero}
                                                    onChange={(e) => updateTelefone(index, 'numero', e.target.value)}
                                                    placeholder="Número com código do país"
                                                    autoComplete="off"
                                                    name={`revskin_paciente_tel_extra_${index}`}
                                                />
                                            )}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeTelefone(index)}
                                            className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">Clique em "Adicionar" para incluir mais telefones</p>
                        )}
                    </div>

                    {/* Address Section */}
                    <div className="border-t pt-6">
                        <h3 className="text-sm font-medium text-gray-900 mb-4">Endereço</h3>
                        <div className="grid grid-cols-6 gap-4">
                            <div className="col-span-2">
                                <MaskedInput
                                    label="CEP"
                                    mask="00000-000"
                                    value={data.cep}
                                    onChange={(e) => setData('cep', e.target.value)}
                                    onBlur={buscarCep}
                                    placeholder="00000-000"
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_cep"
                                />
                                {loadingCep && <span className="text-xs text-gray-500">Buscando...</span>}
                            </div>
                            <div className="col-span-4">
                                <Input
                                    label="Endereço"
                                    value={data.endereco}
                                    onChange={(e) => setData('endereco', e.target.value)}
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_logradouro"
                                />
                            </div>
                            <div className="col-span-1">
                                <Input
                                    label="Número"
                                    value={data.numero}
                                    onChange={(e) => setData('numero', e.target.value)}
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_numero"
                                />
                            </div>
                            <div className="col-span-2">
                                <Input
                                    label="Complemento"
                                    value={data.complemento}
                                    onChange={(e) => setData('complemento', e.target.value)}
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_complemento"
                                />
                            </div>
                            <div className="col-span-3">
                                <Input
                                    label="Bairro"
                                    value={data.bairro}
                                    onChange={(e) => setData('bairro', e.target.value)}
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_bairro"
                                />
                            </div>
                            <div className="col-span-4">
                                <Input
                                    label="Cidade"
                                    value={data.cidade}
                                    onChange={(e) => setData('cidade', e.target.value)}
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_cidade"
                                />
                            </div>
                            <div className="col-span-2">
                                {isBrazil ? (
                                    <Select
                                        label="UF"
                                        value={data.uf}
                                        onChange={(e) => setData('uf', e.target.value)}
                                        options={[
                                            { value: '', label: 'UF' },
                                            ...['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => ({ value: uf, label: uf }))
                                        ]}
                                        {...NO_AUTOFILL}
                                        name="revskin_paciente_uf"
                                    />
                                ) : (
                                    <Input
                                        label="Estado/Província"
                                        value={data.uf}
                                        onChange={(e) => setData('uf', e.target.value)}
                                        {...NO_AUTOFILL}
                                        name="revskin_paciente_uf"
                                    />
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Medico Responsável (admin e secretária; oculto para médico) */}
                    {showMedico && (
                        <div className="border-t pt-6">
                            <h3 className="text-sm font-medium text-gray-900 mb-4">Médico Responsável {medicoRequired && <span className="text-red-500">*</span>}</h3>
                            {medicos?.length > 0 ? (
                                <div>
                                    {medicoLocked ? (
                                        <div className="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                                            {nomeExibicaoSemTitulo(
                                                medicos.find((m) => String(m.id) === String(data.medico_id))?.nome
                                                    || selectedMedico?.nome
                                                    || paciente?.medico?.nome
                                            ) || '—'}
                                        </div>
                                    ) : (
                                        <Select
                                            label=""
                                            value={data.medico_id ? String(data.medico_id) : ''}
                                            onChange={(e) => {
                                                setData('medico_id', e.target.value ? Number(e.target.value) : '');
                                                setFieldErrors(prev => ({ ...prev, medico_id: null }));
                                            }}
                                            options={[
                                                { value: '', label: 'Selecione o médico' },
                                                ...medicos.map((m) => ({
                                                    value: String(m.id),
                                                    label:
                                                        nomeExibicaoSemTitulo(m.nome || m.linked_user?.name) || `Médico ${m.id}`,
                                                })),
                                            ]}
                                            error={fieldErrors.medico_id || errors.medico_id}
                                            autoComplete="off"
                                            name="revskin_paciente_medico_id"
                                        />
                                    )}
                                </div>
                            ) : (
                                <div>
                                    <div className="relative">
                                        {medicoLocked && (selectedMedico || data.medico_id) ? (
                                            <div className="bg-gray-50 border border-gray-300 rounded-lg px-4 py-3">
                                                <div className="font-medium text-gray-900">
                                                    {nomeExibicaoSemTitulo(
                                                        selectedMedico?.nome || paciente?.medico?.nome
                                                    ) || '—'}
                                                </div>
                                                {(selectedMedico?.crm || paciente?.medico?.crm) && (
                                                    <div className="text-sm text-gray-500">CRM: {selectedMedico?.crm || paciente?.medico?.crm}</div>
                                                )}
                                            </div>
                                        ) : selectedMedico ? (
                                            <div className="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                                                <div>
                                                    <div className="font-medium text-gray-900">
                                                        {nomeExibicaoSemTitulo(selectedMedico.nome)}
                                                    </div>
                                                    {selectedMedico.crm && <div className="text-sm text-gray-500">CRM: {selectedMedico.crm}</div>}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setSelectedMedico(null);
                                                        setData('medico_id', '');
                                                        setFieldErrors(prev => ({ ...prev, medico_id: null }));
                                                    }}
                                                    className="text-gray-400 hover:text-gray-600"
                                                >
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <div className="relative">
                                                    <input
                                                        type="text"
                                                        placeholder="Buscar médico pelo nome ou CRM..."
                                                        value={searchMedico}
                                                        onChange={(e) => setSearchMedico(e.target.value)}
                                                        className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 ${(fieldErrors.medico_id || errors.medico_id) ? 'border-red-400 bg-red-50' : 'border-gray-300'}`}
                                                        autoComplete="off"
                                                        name="revskin_paciente_medico_busca"
                                                    />
                                                    {loadingMedicos && (
                                                        <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                                            <svg className="animate-spin h-5 w-5 text-gray-400" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                            </svg>
                                                        </div>
                                                    )}
                                                </div>
                                                {showMedicoDropdown && medicoResults.length > 0 && (
                                                    <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-auto">
                                                        {medicoResults.map((medico) => (
                                                            <button
                                                                key={medico.id}
                                                                type="button"
                                                                onClick={() => selectMedico(medico)}
                                                                className="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                                            >
                                                                <div className="font-medium text-gray-900">
                                                                    {nomeExibicaoSemTitulo(medico.nome)}
                                                                </div>
                                                                <div className="text-sm text-gray-500">{medico.crm} - {medico.especialidade}</div>
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>
                                    {(fieldErrors.medico_id || errors.medico_id) && (
                                        <p className="mt-1 text-sm text-red-600">{fieldErrors.medico_id || errors.medico_id}</p>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Campos privados do vínculo médico–paciente */}
                    <div className="border-t pt-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Input
                                label="Indicado por"
                                value={data.indicado_por}
                                onChange={(e) => setData('indicado_por', e.target.value)}
                                placeholder="Opcional"
                                autoComplete="off"
                                name="revskin_paciente_indicado_por"
                            />
                            <Input
                                label="Nº Registro"
                                value={data.codigo}
                                onChange={(e) => {
                                    setData('codigo', e.target.value);
                                    setFieldErrors((prev) => ({ ...prev, codigo: null }));
                                }}
                                error={fieldErrors.codigo || errors.codigo}
                                placeholder="Opcional"
                                autoComplete="off"
                                name="revskin_paciente_codigo"
                            />
                        </div>
                        <div className="mt-4">
                            <Input
                                label="Observações"
                                value={data.anotacoes}
                                onChange={(e) => setData('anotacoes', e.target.value)}
                                multiline
                                rows={3}
                                autoComplete="off"
                                name="revskin_paciente_anotacoes"
                            />
                        </div>
                    </div>

                    {/* Status (edit only) */}
                    {paciente && (
                        <div className="border-t pt-6">
                            <Select
                                label="Status"
                                value={data.ativo ? '1' : '0'}
                                onChange={(e) => setData('ativo', e.target.value === '1')}
                                options={[
                                    { value: '1', label: 'Ativo' },
                                    { value: '0', label: 'Inativo' },
                                ]}
                                autoComplete="off"
                                name="revskin_paciente_ativo"
                            />
                        </div>
                    )}

                    {(paciente || currentPacienteId) && (
                        <div className="border-t pt-6 text-xs text-gray-500 space-y-1.5">
                            <p>
                                <span className="font-medium text-gray-600">Cadastrado por:</span>{' '}
                                {auditNames.created ?? '—'}
                            </p>
                            <p>
                                <span className="font-medium text-gray-600">Última edição por:</span>{' '}
                                {auditNames.updated ?? '—'}
                            </p>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="border-t border-gray-200 p-6 bg-gray-50">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            {paciente && paciente.ativo && !showDeleteConfirm && (
                                <button type="button" onClick={() => setShowDeleteConfirm(true)} className="flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    Desativar
                                </button>
                            )}
                            {showDeleteConfirm && (
                                <div className="flex items-center gap-2">
                                    <span className="text-sm">Confirmar desativação?</span>
                                    <button type="button" onClick={handleDelete} className="px-3 py-1 bg-red-600 text-white rounded">Sim</button>
                                    <button type="button" onClick={() => setShowDeleteConfirm(false)} className="px-3 py-1 bg-gray-200 rounded">Não</button>
                                </div>
                            )}
                        </div>
                        <div className="flex items-center gap-3">
                            {/* Autosave indicator */}
                            {enableAutoSave && (isAutoSaving || lastSavedText) && (
                                <div className="text-xs text-gray-500 flex items-center gap-1">
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
                            <button type="button" onClick={requestClose} className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={isSaving || !isManualSaveValid}
                                className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {isSaving ? 'Salvando...' : 'Salvar'}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </Drawer>
        <UnsavedChangesModal
            open={showUnsavedModal}
            onCancel={handleUnsavedCancel}
            onDiscard={handleUnsavedDiscard}
            onSave={handleUnsavedSave}
            saving={savingBeforeLeave}
        />
        </>
    );
}
