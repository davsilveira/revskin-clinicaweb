import { useForm, router } from '@inertiajs/react';
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
 * - enableAutoSave: boolean - habilitar autosave (default: true)
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
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [loadingCep, setLoadingCep] = useState(false);
    const [cpfError, setCpfError] = useState(null);
    const [currentPacienteId, setCurrentPacienteId] = useState(paciente?.id || null);
    const isFirstRender = useRef(true);
    const [fieldErrors, setFieldErrors] = useState({});

    const { data, setData, post, put, processing, errors, reset } = useForm({ ...INITIAL_PACIENTE_FORM });

    // Medico search states (for admin)
    const [searchMedico, setSearchMedico] = useState('');
    const [medicoResults, setMedicoResults] = useState([]);
    const [showMedicoDropdown, setShowMedicoDropdown] = useState(false);
    const [selectedMedico, setSelectedMedico] = useState(null);
    const [loadingMedicos, setLoadingMedicos] = useState(false);
    const [auditNames, setAuditNames] = useState({ created: null, updated: null });
    const [formBaseline, setFormBaseline] = useState(null);

    // Initialize form data when paciente changes
    useEffect(() => {
        if (isOpen) {
            if (paciente) {
                setCurrentPacienteId(paciente.id);
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
                setSelectedMedico(null);
                setAuditNames({ created: null, updated: null });
                setFormBaseline(cloneDeep(normalizePatientData(INITIAL_PACIENTE_FORM)));
            }
            setShowDeleteConfirm(false);
            setCpfError(null);
            setFieldErrors({});
            setSearchMedico('');
            isFirstRender.current = true;
        } else {
            setFormBaseline(null);
        }
    }, [isOpen, paciente]);

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

    // Autosave function
    const performAutoSave = useCallback(async () => {
        if (isSavingRef.current) return;
        // Médico obrigatório para secretária: bloquear se vazio
        if (medicoRequired && showMedico && !data.medico_id) return;
        // Validar campos obrigatórios antes de tentar autosave
        if (!data.nome || data.nome.trim().length < 2) return;
        if (!data.cpf || data.cpf.replace(/\D/g, '').length === 0) return;
        if (!data.data_nascimento) return;
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) return;
        if (!data.email1 || !data.email1.trim()) return;
        
        // Validar CPF se preenchido
        if (data.cpf && !validateCPF(data.cpf)) return;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Filter out empty telefones
        const telefonesValidos = data.telefones.filter(t => t.numero && t.numero.trim());
        
        const response = await fetch('/api/pacientes/autosave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                id: currentPacienteId,
                ...data,
                telefones: telefonesValidos,
            }),
        });
        
        if (!response.ok) {
            if (response.status === 422) {
                const errData = await response.json();
                if (errData?.errors?.medico_id) {
                    setFieldErrors(prev => ({ ...prev, medico_id: errData.errors.medico_id[0] }));
                }
                return;
            }
            throw new Error('Autosave failed');
        }
        
        const result = await response.json();
        if (result.id && !currentPacienteId) {
            setCurrentPacienteId(result.id);
        }
        if (result.created_by_name != null || result.updated_by_name != null) {
            setAuditNames((prev) => ({
                created: result.created_by_name ?? prev.created,
                updated: result.updated_by_name ?? prev.updated,
            }));
        }

        setFormBaseline(cloneDeep(normalizePatientData(data)));

        return result;
    }, [data, currentPacienteId, medicoRequired, showMedico]);

    // Verificar se todos os campos obrigatórios estão preenchidos para habilitar autosave
    const canAutoSave = useCallback(() => {
        if (!enableAutoSave || !isOpen) return false;
        if (!data.nome || data.nome.trim().length < 2) return false;
        if (!data.cpf || data.cpf.replace(/\D/g, '').length === 0) return false;
        if (!data.data_nascimento) return false;
        if (!data.celular || data.celular.replace(/\D/g, '').length < 10) return false;
        if (!data.email1 || !data.email1.trim()) return false;
        if (data.cpf && !validateCPF(data.cpf)) return false;
        if (medicoRequired && showMedico && !data.medico_id) return false;
        return true;
    }, [enableAutoSave, isOpen, data, medicoRequired, showMedico]);

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
    const isSavingRef = useRef(false);

    const persistPatient = useCallback(async () => {
        cancelAutoSave();
        isSavingRef.current = true;

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
        }
        if (medicoRequired && showMedico && !data.medico_id) {
            newErrors.medico_id = 'Selecione o médico responsável.';
            hasErrors = true;
        }
        if (hasErrors) {
            setFieldErrors(newErrors);
            isSavingRef.current = false;
            return false;
        }

        setFieldErrors({});
        setIsSaving(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const telefonesValidos = data.telefones.filter((t) => t.numero && t.numero.trim());

            const existingId = paciente?.id || currentPacienteId;
            const url = existingId ? `/pacientes/${existingId}` : '/pacientes';
            const method = existingId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ...data,
                    telefones: telefonesValidos,
                }),
            });

            if (response.ok) {
                setFieldErrors({});
                setCpfError(null);
                // Garante que isDirty vire false e o router.on('before') seja removido antes de onSave()
                // disparar router.get (ex.: refresh na lista de receitas filtradas por paciente).
                flushSync(() => {
                    setFormBaseline(cloneDeep(normalizePatientData(data)));
                });
                onSave?.();
                return true;
            }

            if (response.status === 419) {
                window.location.reload();
                return false;
            }

            const errorData = await response.json();
            console.error('Error saving patient:', errorData);

            if (response.status === 422 && errorData.errors) {
                const backendErrors = {};
                Object.keys(errorData.errors).forEach((key) => {
                    backendErrors[key] = errorData.errors[key][0];
                });
                setFieldErrors(backendErrors);
                if (backendErrors.cpf) {
                    setCpfError(backendErrors.cpf);
                }
            }
            return false;
        } catch (error) {
            console.error('Error saving patient:', error);
            return false;
        } finally {
            setIsSaving(false);
            isSavingRef.current = false;
        }
    }, [data, paciente?.id, currentPacienteId, medicoRequired, showMedico, onSave, cancelAutoSave]);

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
        isDirty,
        onConfirmClose: forceClose,
        saveBeforeClose,
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        await persistPatient();
    };

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
            <form onSubmit={handleSubmit} className="flex flex-col h-full">
                <div className="flex-1 p-6 space-y-6 overflow-y-auto">
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
                                autoComplete="name"
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
                            }}
                            error={cpfError || fieldErrors.cpf || errors.cpf}
                            placeholder="000.000.000-00"
                            required
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
                        />
                        <div className="col-span-2 sm:col-span-1">
                            <Input
                                label="E-mail"
                                type="email"
                                value={data.email1}
                                onChange={(e) => {
                                    setData('email1', e.target.value);
                                    setFieldErrors(prev => ({ ...prev, email1: null }));
                                }}
                                error={fieldErrors.email1 || errors.email1}
                                required
                                autoComplete="email"
                                inputMode="email"
                            />
                            <p className="mt-1.5 text-xs text-gray-500">
                                Se o browser preencher país ou endereço ao digitar o e-mail, desative o autofill para este site ou use outro perfil — não é o CW3 que grava esses campos automaticamente.
                            </p>
                        </div>
                        <div className="col-span-2">
                            <Select
                                label="País"
                                value={data.pais}
                                onChange={(e) => setData('pais', e.target.value)}
                                options={countries}
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
                                                />
                                            ) : (
                                                <Input
                                                    label={index === 0 ? "Número" : ""}
                                                    value={tel.numero}
                                                    onChange={(e) => updateTelefone(index, 'numero', e.target.value)}
                                                    placeholder="Número com código do país"
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
                                />
                                {loadingCep && <span className="text-xs text-gray-500">Buscando...</span>}
                            </div>
                            <div className="col-span-4">
                                <Input label="Endereço" value={data.endereco} onChange={(e) => setData('endereco', e.target.value)} />
                            </div>
                            <div className="col-span-1">
                                <Input label="Número" value={data.numero} onChange={(e) => setData('numero', e.target.value)} />
                            </div>
                            <div className="col-span-2">
                                <Input label="Complemento" value={data.complemento} onChange={(e) => setData('complemento', e.target.value)} />
                            </div>
                            <div className="col-span-3">
                                <Input label="Bairro" value={data.bairro} onChange={(e) => setData('bairro', e.target.value)} />
                            </div>
                            <div className="col-span-4">
                                <Input label="Cidade" value={data.cidade} onChange={(e) => setData('cidade', e.target.value)} />
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
                                    />
                                ) : (
                                    <Input
                                        label="Estado/Província"
                                        value={data.uf}
                                        onChange={(e) => setData('uf', e.target.value)}
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

                    {/* Notes */}
                    <div className="border-t pt-6">
                        <Input
                            label="Observações"
                            value={data.anotacoes}
                            onChange={(e) => setData('anotacoes', e.target.value)}
                            multiline
                            rows={3}
                        />
                    </div>

                    <div className="border-t pt-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Input
                                label="Nº Registro"
                                value={data.codigo}
                                onChange={(e) => {
                                    setData('codigo', e.target.value);
                                    setFieldErrors((prev) => ({ ...prev, codigo: null }));
                                }}
                                error={fieldErrors.codigo || errors.codigo}
                                placeholder="Opcional"
                            />
                            <Input
                                label="Indicado por"
                                value={data.indicado_por}
                                onChange={(e) => setData('indicado_por', e.target.value)}
                                placeholder="Opcional"
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
