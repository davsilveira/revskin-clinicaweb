import { useForm, router, usePage } from '@inertiajs/react';
import { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import { flushSync } from 'react-dom';
import Drawer from '@/Components/Drawer';
import Input from '@/Components/Form/Input';
import DatePickerField from '@/Components/Form/DatePickerField';
import MaskedInput from '@/Components/Form/MaskedInput';
import Select from '@/Components/Form/Select';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import PacientesEncontrados from '@/Components/PacientesEncontrados';
import usePacientesCandidatos from '@/hooks/usePacientesCandidatos';
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
    outro_documento: '',
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

/**
 * Só bloqueia por conteúdo inválido — a obrigatoriedade é regra separada (ver `cpfObrigatorio`).
 */
function cpfPreenchidoInvalido(cpf) {
    const digits = String(cpf || '').replace(/\D/g, '');
    return digits.length > 0 && !validateCPF(cpf);
}

function cpfVazio(cpf) {
    return String(cpf || '').replace(/\D/g, '').length === 0;
}

/**
 * E-mail é opcional (decisão do cliente): campo em branco é um estado válido. Só um
 * endereço mal formado é erro. O domínio NÃO pode ter underline — é o que trava hoje os
 * cadastros importados com `@cadastrar_email.com`, e o backend rejeita.
 */
const EMAIL_RE = /^[^\s@]+@[^\s@_]+\.[^\s@_]+$/;

function emailPreenchidoInvalido(email) {
    const valor = String(email || '').trim();
    return valor.length > 0 && !EMAIL_RE.test(valor);
}

const MSG_CPF_OBRIGATORIO = 'O CPF é obrigatório para pacientes no Brasil.';

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
    const [cpfTouched, setCpfTouched] = useState(false);
    const [currentPacienteId, setCurrentPacienteId] = useState(paciente?.id || null);
    const isFirstRender = useRef(true);
    const [fieldErrors, setFieldErrors] = useState({});
    const [autofillNotice, setAutofillNotice] = useState(false);
    const [lookupNotice, setLookupNotice] = useState(null);
    const guardBrowserAutofillRef = useRef(false);
    const autofillGuardTimerRef = useRef(null);

    const [candidatosDispensados, setCandidatosDispensados] = useState(false);
    /** Cadastro existente escolhido na busca por nome: manda no upsert do backend. */
    const [pacienteExistente, setPacienteExistente] = useState(null);
    /** Formulário como estava antes de escolher um cadastro (para o "x" do chip desfazer). */
    const formAntesDoCandidatoRef = useRef(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({ ...INITIAL_PACIENTE_FORM });

    /**
     * Busca por NOME — caminho principal contra cadastro duplicado (a busca por CPF nunca
     * acharia os clientes vindos do oList sem CPF). Só em cadastro novo.
     */
    const buscaCandidatosHabilitada = Boolean(
        isOpen && !paciente && !currentPacienteId && !candidatosDispensados && !pacienteExistente
    );
    const {
        candidatos,
        total: candidatosTotal,
        buscando: buscandoCandidatos,
        limpar: limparCandidatos,
    } = usePacientesCandidatos({
        termo: data.nome,
        habilitado: buscaCandidatosHabilitada,
        medicoId: data.medico_id || null,
    });

    // Medico search states (for admin)
    const [searchMedico, setSearchMedico] = useState('');
    const [medicoResults, setMedicoResults] = useState([]);
    const [showMedicoDropdown, setShowMedicoDropdown] = useState(false);
    const [selectedMedico, setSelectedMedico] = useState(null);
    const [loadingMedicos, setLoadingMedicos] = useState(false);
    const [auditNames, setAuditNames] = useState({ created: null, updated: null });
    const [formBaseline, setFormBaseline] = useState(null);

    // Opção 2: com 2+ vínculos, o "Médico responsável" travado só confunde — a lista por médico basta.
    const vinculosMedicoCount = useMemo(() => {
        const fromPrivados = Array.isArray(paciente?.privados_por_medico) ? paciente.privados_por_medico.length : 0;
        const fromMedicos = Array.isArray(paciente?.medicos) ? paciente.medicos.length : 0;
        return Math.max(fromPrivados, fromMedicos);
    }, [paciente?.privados_por_medico, paciente?.medicos]);
    const hideMedicoResponsavel = Boolean(paciente && vinculosMedicoCount > 1);
    const showMedicoResponsavelSection = showMedico && !hideMedicoResponsavel;

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
                    outro_documento: paciente.outro_documento || '',
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
            setCpfTouched(false);
            setFieldErrors({});
            setSearchMedico('');
            setCandidatosDispensados(false);
            setPacienteExistente(null);
            formAntesDoCandidatoRef.current = null;
            setLookupNotice(null);
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
        // Precisa ser merge: `setData` com objeto SUBSTITUI o form inteiro e derruba
        // nome/CPF/telefones, deixando inputs sem valor e o drawer em loop de render.
        setData((prev) => ({
            ...prev,
            pais: 'Brasil',
            cep: '',
            endereco: '',
            numero: '',
            complemento: '',
            bairro: '',
            cidade: '',
            uf: '',
        }));
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

    /**
     * CPF obrigatório no Brasil (feedback do cliente). Exceção: cadastro que JÁ está salvo
     * sem CPF — pacientes legados e clientes importados do oList (1 em cada 4 vem sem CPF)
     * não podem travar a edição nem o vínculo. Mesma regra do backend.
     */
    const cadastroExistenteSemCpf = Boolean(
        (paciente && cpfVazio(paciente.cpf)) || (pacienteExistente && cpfVazio(pacienteExistente.cpf))
    );
    const cpfObrigatorio = isBrazil && !cadastroExistenteSemCpf;
    const cpfFaltando = cpfObrigatorio && cpfVazio(data.cpf);

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
        if (!data.data_nascimento) return Promise.resolve();
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) {
            return Promise.resolve();
        }
        // E-mail é opcional; só um endereço mal digitado segura o rascunho (o backend
        // devolveria 422 a cada 2 s enquanto o campo estivesse pela metade).
        if (emailPreenchidoInvalido(data.email1)) return Promise.resolve();
        if (cpfPreenchidoInvalido(data.cpf) || cpfFaltando) return Promise.resolve();

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
    }, [data, currentPacienteId, medicoRequired, showMedico, enableAutoSave, paciente, runExclusive, csrfForRequests, cpfFaltando]);

    const canAutoSave = useCallback(() => {
        if (!enableAutoSave || !isOpen || !paciente) {
            return false;
        }
        if (!data.nome || data.nome.trim().length < 2) return false;
        if (!data.data_nascimento) return false;
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) return false;
        if (emailPreenchidoInvalido(data.email1)) return false;
        if (cpfPreenchidoInvalido(data.cpf) || cpfFaltando) return false;
        if (medicoRequired && showMedico && !data.medico_id) return false;
        return true;
    }, [enableAutoSave, isOpen, data, medicoRequired, showMedico, paciente, cpfFaltando]);

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

                if (cpfPreenchidoInvalido(data.cpf)) {
                    setCpfError('CPF inválido. Por favor, verifique os números digitados.');
                    newErrors.cpf = 'CPF inválido.';
                    hasErrors = true;
                } else if (cpfFaltando) {
                    setCpfError(MSG_CPF_OBRIGATORIO);
                    newErrors.cpf = MSG_CPF_OBRIGATORIO;
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

                if (emailPreenchidoInvalido(data.email1)) {
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
                            // Cadastro escolhido na busca por nome: o backend atualiza ESTE
                            // paciente e cria o vínculo, em vez de adivinhar pela identidade.
                            paciente_existente_id: id ? null : (pacienteExistente?.id ?? null),
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
                        // Passa o paciente salvo para quem chamou poder selecioná-lo (ex.: nova receita).
                        onSave?.(body?.paciente ?? null);
                        return true;
                    }

                    if (response.status === 422) {
                        if (body?.errors) {
                            const backendErrors = {};
                            Object.keys(body.errors).forEach((key) => {
                                backendErrors[key] = body.errors[key][0];
                            });
                            // Médico não vê o campo médico no formulário — sobe o erro para o alerta geral.
                            if (backendErrors.medico_id && !showMedico) {
                                backendErrors._form = backendErrors.medico_id;
                            }
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
    }, [data, paciente, medicoRequired, showMedico, onSave, cancelAutoSave, runExclusive, csrfForRequests, cpfFaltando, pacienteExistente]);

    const saveBeforeClose = useCallback(async () => persistPatient(), [persistPatient]);

    const isDirty = useMemo(() => {
        if (!isOpen || !formBaseline) return false;
        return !isEqual(normalizePatientData(data), formBaseline);
    }, [isOpen, formBaseline, data]);

    /** Mesmas regras que persistPatient / canAutoSave: só habilita Salvar com obrigatórios válidos (como Usuários). */
    /** Erro de CPF em tempo real: assim que 11 dígitos forem digitados e o CPF for inválido,
     *  mostra a mensagem — deixa claro por que o botão Salvar fica desabilitado. */
    const cpfLiveError = useMemo(() => {
        if (cpfVazio(data.cpf)) {
            // Obrigatório e vazio: só reclama depois de passar pelo campo, senão o drawer
            // abre já pintado de vermelho.
            return cpfObrigatorio && cpfTouched ? MSG_CPF_OBRIGATORIO : null;
        }
        if (!cpfPreenchidoInvalido(data.cpf)) return null;
        const digits = (data.cpf || '').replace(/\D/g, '');
        // Enquanto digita, só reclama com 11 dígitos; incompleto só depois de sair do campo,
        // senão o erro pisca a cada tecla.
        if (digits.length === 11) {
            return 'CPF inválido. Por favor, verifique os números digitados.';
        }
        return cpfTouched
            ? (cpfObrigatorio
                ? 'CPF incompleto. Preencha os 11 dígitos.'
                : 'CPF incompleto. Preencha os 11 dígitos ou deixe o campo em branco.')
            : null;
    }, [data.cpf, cpfTouched, cpfObrigatorio]);

    const isManualSaveValid = useMemo(() => {
        if (!isOpen) return false;
        if (!data.nome || data.nome.trim().length < 2) return false;
        if (cpfPreenchidoInvalido(data.cpf) || cpfFaltando) return false;
        if (!data.data_nascimento) return false;
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) return false;
        if (emailPreenchidoInvalido(data.email1)) return false;
        if (medicoRequired && showMedico && !data.medico_id) return false;
        return true;
    }, [isOpen, data, medicoRequired, showMedico, cpfFaltando]);

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
     * Opção 2: ao informar um identificador já cadastrado (por qualquer médico), pré-preenche
     * os DADOS COMPARTILHADOS do paciente. Os campos exclusivos deste médico ficam em branco
     * (a serem preenchidos). Só roda em cadastro novo (sem paciente carregado).
     *
     * `campo` diz por onde procurar: 'cpf' (11 dígitos), 'outro_documento' ou 'email'.
     */
    const doLookup = useCallback(async (campo, valor = null) => {
        if (paciente || currentPacienteIdRef.current) return; // só em cadastro novo

        const params = new URLSearchParams();
        if (campo === 'id') {
            if (!valor) return;
            params.set('id', String(valor));
        } else if (campo === 'cpf') {
            const digits = String(data.cpf || '').replace(/\D/g, '');
            if (digits.length !== 11) return;
            params.set('cpf', digits);
        } else if (campo === 'outro_documento') {
            const doc = String(data.outro_documento || '').trim();
            if (doc.length < 3) return;
            params.set('outro_documento', doc);
        } else {
            const email = String(data.email1 || '').trim();
            if (!EMAIL_RE.test(email)) return;
            params.set('email', email);
        }

        try {
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
            // Dados vindos do lookup são intencionais: o guard anti-autofill não deve
            // reverter para Brasil o país de um paciente estrangeiro já cadastrado.
            guardBrowserAutofillRef.current = false;
            const shared = {
                nome: p.nome || '', rg: p.rg || '', data_nascimento: p.data_nascimento || '',
                sexo: p.sexo || '', fototipo: p.fototipo || '',
                email1: p.email1 || '', email2: p.email2 || '',
                telefone1: p.telefone1 || '', celular: p.celular || '', telefone3: p.telefone3 || '',
                tipo_endereco: p.tipo_endereco || '', endereco: p.endereco || '', numero: p.numero || '',
                complemento: p.complemento || '', bairro: p.bairro || '', cidade: p.cidade || '',
                uf: p.uf || '', pais: p.pais || '', cep: p.cep || '',
                cpf: p.cpf || '', outro_documento: p.outro_documento || '',
            };
            // Match fraco (e-mail de família, por exemplo) apenas COMPLETA campos vazios:
            // substituir o nome e a data de nascimento já digitados trocaria a pessoa sendo
            // cadastrada — a mãe entraria no lugar da filha, sem o usuário perceber.
            const substituir = json.match_forte !== false;
            setData((prev) => {
                const merged = { ...prev };
                Object.entries(shared).forEach(([campo, valor]) => {
                    if (!valor) return;
                    if (substituir || !String(prev[campo] ?? '').trim()) {
                        merged[campo] = valor;
                    }
                });

                return merged;
            });

            if (json.match_por === 'id') {
                // Escolha explícita na busca por nome: o cadastro selecionado manda no upsert.
                // Sem aviso em faixa — o chip abaixo do Nome já diz o que está acontecendo.
                setPacienteExistente(p);
                setLookupNotice(null);
            } else if (json.ja_vinculado) {
                setLookupNotice('Este paciente já está cadastrado para este médico. Verifique antes de salvar novamente.');
            } else if (json.match_por === 'email') {
                setLookupNotice(
                    `Já existe um paciente com este e-mail (${p.nome}). Completamos apenas os campos que estavam `
                    + 'em branco — o que você digitou foi mantido. Se for a mesma pessoa, procure-a pelo nome e use '
                    + '"Usar este cadastro"; se for outra pessoa (e-mail de família), siga com o cadastro novo.'
                );
            } else if (json.match_por === 'outro_documento') {
                setLookupNotice('Já existe um paciente com este documento — os dados compartilhados foram pré-preenchidos.');
            } else {
                setLookupNotice(null);
            }
        } catch (_) {
            // silencioso: lookup é conveniência, não bloqueia o cadastro
        }
    }, [paciente, data.cpf, data.outro_documento, data.email1, data.medico_id, setData]);

    /** "Usar este cadastro": carrega os dados compartilhados e fixa o alvo do upsert. */
    const usarCandidato = useCallback((candidato) => {
        limparCandidatos();
        setCpfError(null);
        setFieldErrors({});
        // Guarda o que o usuário já tinha digitado: o "x" do chip desfaz a escolha em vez de
        // simplesmente apagar tudo.
        formAntesDoCandidatoRef.current = cloneDeep(data);
        doLookup('id', candidato.id);
    }, [doLookup, limparCandidatos, data]);

    /** "Nenhum destes": segue com cadastro novo e para de avisar para este nome. */
    const dispensarCandidatos = useCallback(() => {
        setCandidatosDispensados(true);
        limparCandidatos();
    }, [limparCandidatos]);

    /** "x" do chip: desfaz a escolha e devolve o formulário ao estado anterior. */
    const trocarParaNovoCadastro = useCallback(() => {
        const anterior = formAntesDoCandidatoRef.current;
        formAntesDoCandidatoRef.current = null;
        setPacienteExistente(null);
        setCandidatosDispensados(false);
        setLookupNotice(null);
        setCpfError(null);
        setCpfTouched(false);
        setFieldErrors({});
        setData(() => (anterior ?? { ...INITIAL_PACIENTE_FORM, medico_id: data.medico_id || '' }));
    }, [setData, data.medico_id]);

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
            closeOnBackdrop={false}
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
                    {/* Legenda do asterisco: sem ela o `*` só faz sentido para quem já
                        conhece a convenção — e agora que o e-mail deixou de ser obrigatório
                        a diferença entre os campos precisa ficar explícita. */}
                    <p className="text-xs text-gray-500">
                        Campos marcados com <span className="text-red-500">*</span> são obrigatórios.
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Input
                                label="Nome Completo"
                                value={data.nome}
                                onChange={(e) => {
                                    setData('nome', e.target.value);
                                    setFieldErrors(prev => ({ ...prev, nome: null }));
                                    setCandidatosDispensados(false);
                                }}
                                error={fieldErrors.nome || errors.nome}
                                required
                                loading={buscandoCandidatos}
                                {...NO_AUTOFILL}
                                name="revskin_paciente_nome"
                            />
                            {/* Cadastro escolhido na lista: um chip discreto basta — o aviso em
                                faixa duplicava a informação e empurrava o formulário para baixo. */}
                            {pacienteExistente && (
                                <div className="mt-1.5 inline-flex max-w-full items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 py-1 pl-2.5 pr-1 text-xs text-emerald-900">
                                    <span className="truncate">
                                        Usando o cadastro <span className="font-medium">#{pacienteExistente.id}</span>
                                        {' — '}será vinculado a você ao salvar
                                    </span>
                                    <button
                                        type="button"
                                        onClick={trocarParaNovoCadastro}
                                        title="Remover o vínculo com este cadastro e voltar ao que você tinha digitado"
                                        aria-label="Remover cadastro vinculado"
                                        className="shrink-0 rounded-full p-0.5 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-900"
                                    >
                                        <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            )}
                            {/* Busca por nome: avisa de cadastro existente ANTES de duplicar. */}
                            {!paciente && !currentPacienteId && !pacienteExistente && (
                                <PacientesEncontrados
                                    candidatos={candidatos}
                                    total={candidatosTotal}
                                    nomeDigitado={String(data.nome || '').trim()}
                                    onSelecionar={usarCandidato}
                                    onIgnorar={dispensarCandidatos}
                                />
                            )}
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
                            <div>
                                <MaskedInput
                                    label="CPF"
                                    mask="000.000.000-00"
                                    value={data.cpf}
                                    required={cpfObrigatorio}
                                    onChange={(e) => {
                                        setData('cpf', e.target.value);
                                        setCpfError(null);
                                        setFieldErrors((prev) => ({ ...prev, cpf: null }));
                                        if (lookupNotice && !pacienteExistente) setLookupNotice(null);
                                    }}
                                    onBlur={() => {
                                        setCpfTouched(true);
                                        if (!pacienteExistente) doLookup('cpf');
                                    }}
                                    error={cpfError || cpfLiveError || fieldErrors.cpf || errors.cpf}
                                    placeholder="000.000.000-00"
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_cpf"
                                />
                                {/* Cadastro antigo/importado sem CPF: pedimos, mas não travamos o salvamento. */}
                                {cadastroExistenteSemCpf && cpfVazio(data.cpf) && (
                                    <p className="mt-1 text-xs text-amber-700">
                                        Este cadastro veio sem CPF. Preencha quando tiver — não é obrigatório para salvar.
                                    </p>
                                )}
                            </div>
                        ) : (
                            <Input
                                label="Documento"
                                value={data.outro_documento}
                                onChange={(e) => {
                                    setData('outro_documento', e.target.value);
                                    setFieldErrors((prev) => ({ ...prev, outro_documento: null }));
                                    if (lookupNotice && !pacienteExistente) setLookupNotice(null);
                                }}
                                onBlur={() => { if (!pacienteExistente) doLookup('outro_documento'); }}
                                error={fieldErrors.outro_documento || errors.outro_documento}
                                placeholder="Passaporte ou documento local"
                                {...NO_AUTOFILL}
                                name="revskin_paciente_outro_documento"
                            />
                        )}
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
                                    if (lookupNotice && !pacienteExistente) setLookupNotice(null);
                                }}
                                /** Rede de segurança extra: e-mail já cadastrado também avisa. */
                                onBlur={() => { if (!pacienteExistente) doLookup('email'); }}
                                error={fieldErrors.email1 || errors.email1}
                                {...NO_AUTOFILL}
                                inputMode="email"
                                name="revskin_paciente_email"
                            />
                        </div>
                        {/* CPF de paciente estrangeiro que já tinha o campo preenchido: não esconder o dado.
                            Continua montado se o usuário apagar o valor, senão o campo desaparece
                            no meio da digitação e não há como corrigir. */}
                        {!isBrazil && (data.cpf || paciente?.cpf || pacienteExistente?.cpf) ? (
                            <div className="col-span-2 sm:col-span-1">
                                <MaskedInput
                                    label="CPF (opcional)"
                                    mask="000.000.000-00"
                                    value={data.cpf}
                                    onChange={(e) => {
                                        setData('cpf', e.target.value);
                                        setCpfError(null);
                                        setFieldErrors((prev) => ({ ...prev, cpf: null }));
                                    }}
                                    onBlur={() => setCpfTouched(true)}
                                    error={cpfError || cpfLiveError || fieldErrors.cpf || errors.cpf}
                                    {...NO_AUTOFILL}
                                    name="revskin_paciente_cpf"
                                />
                            </div>
                        ) : null}
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

                    {/* Medico Responsável (admin e secretária; oculto para médico; oculto se 2+ vínculos) */}
                    {showMedicoResponsavelSection && (
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
                                            placeholder="Selecione o médico"
                                            options={medicos.map((m) => ({
                                                value: String(m.id),
                                                label:
                                                    nomeExibicaoSemTitulo(m.nome || m.linked_user?.name) || `Médico ${m.id}`,
                                            }))}
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
                        {isAdmin && paciente ? (
                            <div className="space-y-5">
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-900">Médicos do paciente</h3>
                                    <p className="mt-1 text-xs text-gray-500">
                                        Indicado por, Nº Registro e Observações são privados de cada médico. Em modo admin, a visualização é somente leitura.
                                    </p>
                                </div>
                                {(paciente.privados_por_medico || []).length > 0 ? (
                                    (paciente.privados_por_medico || []).map((vinculo) => (
                                        <div key={vinculo.medico_id} className="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <h4 className="text-sm font-semibold text-gray-900">
                                                    {nomeExibicaoSemTitulo(vinculo.medico_nome) || `Médico #${vinculo.medico_id}`}
                                                </h4>
                                                {vinculo.ativo === false && (
                                                    <span className="text-xs font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded">
                                                        Vínculo inativo
                                                    </span>
                                                )}
                                            </div>
                                            <dl className="space-y-2 text-sm">
                                                <div className="grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-1 sm:gap-3">
                                                    <dt className="text-gray-500">Indicado por</dt>
                                                    <dd className="text-gray-900">{vinculo.indicado_por?.trim() ? vinculo.indicado_por : '—'}</dd>
                                                </div>
                                                <div className="grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-1 sm:gap-3">
                                                    <dt className="text-gray-500">Nº Registro</dt>
                                                    <dd className="text-gray-900 tabular-nums">{vinculo.codigo?.trim() ? vinculo.codigo : '—'}</dd>
                                                </div>
                                                <div className="grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-1 sm:gap-3">
                                                    <dt className="text-gray-500">Observações</dt>
                                                    <dd className="text-gray-900 whitespace-pre-wrap">{vinculo.anotacoes?.trim() ? vinculo.anotacoes : '—'}</dd>
                                                </div>
                                            </dl>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-gray-500">Nenhum médico vinculado a este paciente.</p>
                                )}
                            </div>
                        ) : (
                            <>
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
                            </>
                        )}
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
