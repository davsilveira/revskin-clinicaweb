import { useForm, router } from '@inertiajs/react';
import { useState, useCallback, useEffect, useRef } from 'react';
import Drawer from '@/Components/Drawer';
import Input from '@/Components/Form/Input';
import MaskedInput from '@/Components/Form/MaskedInput';
import Select from '@/Components/Form/Select';
import { validateCPF } from '@/utils/validations';
import useAutoSave from '@/hooks/useAutoSave';
import countries from '@/utils/countries';
import debounce from 'lodash/debounce';

/**
 * PatientDrawer - Drawer reutilizável para edição de pacientes
 * 
 * Props:
 * - isOpen: boolean - controla se o drawer está aberto
 * - onClose: function - callback ao fechar
 * - paciente: object|null - paciente para editar (null para novo)
 * - onSave: function - callback após salvar com sucesso
 * - isAdmin: boolean - mostrar campos de admin (médico responsável)
 * - enableAutoSave: boolean - habilitar autosave (default: true)
 */
export default function PatientDrawer({
    isOpen,
    onClose,
    paciente = null,
    onSave,
    isAdmin = false,
    enableAutoSave = true,
}) {
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [loadingCep, setLoadingCep] = useState(false);
    const [cpfError, setCpfError] = useState(null);
    const [currentPacienteId, setCurrentPacienteId] = useState(paciente?.id || null);
    const isFirstRender = useRef(true);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nome: '',
        cpf: '',
        data_nascimento: '',
        sexo: '',
        email1: '',
        telefone1: '',
        telefone2: '',
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
    });

    // Medico search states (for admin)
    const [searchMedico, setSearchMedico] = useState('');
    const [medicoResults, setMedicoResults] = useState([]);
    const [showMedicoDropdown, setShowMedicoDropdown] = useState(false);
    const [selectedMedico, setSelectedMedico] = useState(null);
    const [loadingMedicos, setLoadingMedicos] = useState(false);

    // Initialize form data when paciente changes
    useEffect(() => {
        if (isOpen) {
            if (paciente) {
                setCurrentPacienteId(paciente.id);
                setSelectedMedico(paciente.medico || null);
                setData({
                    nome: paciente.nome || '',
                    cpf: paciente.cpf || '',
                    data_nascimento: paciente.data_nascimento ? paciente.data_nascimento.split('T')[0] : '',
                    sexo: paciente.sexo || '',
                    email1: paciente.email1 || '',
                    telefone1: paciente.telefone1 || '',
                    telefone2: paciente.telefone2 || '',
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
                });
            } else {
                reset();
                setCurrentPacienteId(null);
                setSelectedMedico(null);
            }
            setShowDeleteConfirm(false);
            setCpfError(null);
            setSearchMedico('');
            isFirstRender.current = true;
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

    useEffect(() => {
        if (isAdmin && searchMedico) {
            searchMedicosApi(searchMedico);
        }
    }, [searchMedico, searchMedicosApi, isAdmin]);

    const selectMedico = (medico) => {
        setSelectedMedico(medico);
        setData('medico_id', medico.id);
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

    // Autosave function
    const performAutoSave = useCallback(async () => {
        if (!data.nome || data.nome.trim().length < 2) return;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Filter out empty telefones
        const telefonesValidos = data.telefones.filter(t => t.numero && t.numero.trim());
        
        const response = await fetch('/api/pacientes/autosave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                id: currentPacienteId,
                ...data,
                telefones: telefonesValidos,
            }),
        });
        
        if (!response.ok) throw new Error('Autosave failed');
        
        const result = await response.json();
        if (result.id && !currentPacienteId) {
            setCurrentPacienteId(result.id);
        }
        
        return result;
    }, [data, currentPacienteId]);

    const { 
        lastSavedText, 
        isSaving: isAutoSaving, 
        triggerAutoSave, 
        cancelAutoSave 
    } = useAutoSave(performAutoSave, 2000, enableAutoSave && isOpen && data.nome.length >= 2);

    // Trigger autosave when data changes
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (enableAutoSave && isOpen && data.nome.length >= 2) {
            triggerAutoSave();
        }
    }, [data, isOpen, enableAutoSave]);

    const handleClose = () => {
        cancelAutoSave();
        onClose?.();
    };

    const [isSaving, setIsSaving] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        // Validar CPF se preenchido
        if (data.cpf && data.cpf.replace(/\D/g, '').length > 0) {
            if (!validateCPF(data.cpf)) {
                setCpfError('CPF inválido. Por favor, verifique os números digitados.');
                return;
            }
        }
        setCpfError(null);
        setIsSaving(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const telefonesValidos = data.telefones.filter(t => t.numero && t.numero.trim());
            
            const url = paciente ? `/pacientes/${paciente.id}` : '/pacientes';
            const method = paciente ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    ...data,
                    telefones: telefonesValidos,
                }),
            });

            if (response.ok) {
                onSave?.();
            } else {
                const errorData = await response.json();
                console.error('Error saving patient:', errorData);
            }
        } catch (error) {
            console.error('Error saving patient:', error);
        } finally {
            setIsSaving(false);
        }
    };

    const handleDelete = () => {
        if (paciente) {
            router.delete(`/pacientes/${paciente.id}`, {
                onSuccess: () => {
                    onSave?.();
                    handleClose();
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
        <Drawer
            isOpen={isOpen}
            onClose={handleClose}
            title={paciente ? 'Editar Paciente' : 'Novo Paciente'}
            width="w-[700px]"
        >
            <form onSubmit={handleSubmit} className="flex flex-col h-full">
                <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Input
                                label="Nome Completo"
                                value={data.nome}
                                onChange={(e) => setData('nome', e.target.value)}
                                error={errors.nome}
                                required
                            />
                        </div>
                        <MaskedInput
                            label="CPF"
                            mask="000.000.000-00"
                            value={data.cpf}
                            onChange={(e) => {
                                setData('cpf', e.target.value);
                                setCpfError(null);
                            }}
                            error={cpfError || errors.cpf}
                            placeholder="000.000.000-00"
                        />
                        <Input
                            label="Data de Nascimento"
                            type="date"
                            value={data.data_nascimento}
                            onChange={(e) => setData('data_nascimento', e.target.value)}
                            error={errors.data_nascimento}
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
                        <Input
                            label="E-mail"
                            type="email"
                            value={data.email1}
                            onChange={(e) => setData('email1', e.target.value)}
                            error={errors.email1}
                        />
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
                                label="Telefone Principal"
                                mask="(00) 0000-0000"
                                value={data.telefone1}
                                onChange={(e) => setData('telefone1', e.target.value)}
                                placeholder="(00) 0000-0000"
                            />
                        ) : (
                            <Input
                                label="Telefone Principal"
                                value={data.telefone1}
                                onChange={(e) => setData('telefone1', e.target.value)}
                                placeholder="Número com código do país"
                            />
                        )}
                        {isBrazil ? (
                            <MaskedInput
                                label="Celular"
                                mask="(00) 00000-0000"
                                value={data.telefone2}
                                onChange={(e) => setData('telefone2', e.target.value)}
                                placeholder="(00) 00000-0000"
                            />
                        ) : (
                            <Input
                                label="Celular"
                                value={data.telefone2}
                                onChange={(e) => setData('telefone2', e.target.value)}
                                placeholder="Número com código do país"
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

                    {/* Medico Search (Admin only) */}
                    {isAdmin && (
                        <div className="border-t pt-6">
                            <h3 className="text-sm font-medium text-gray-900 mb-4">Médico Responsável</h3>
                            <div className="relative">
                                {selectedMedico ? (
                                    <div className="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                                        <div>
                                            <div className="font-medium text-gray-900">{selectedMedico.nome}</div>
                                            {selectedMedico.crm && <div className="text-sm text-gray-500">CRM: {selectedMedico.crm}</div>}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setSelectedMedico(null);
                                                setData('medico_id', '');
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
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
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
                                                        <div className="font-medium text-gray-900">{medico.nome}</div>
                                                        <div className="text-sm text-gray-500">{medico.crm} - {medico.especialidade}</div>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
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
                            <button type="button" onClick={handleClose} className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" disabled={isSaving} className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                                {isSaving ? 'Salvando...' : 'Salvar'}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </Drawer>
    );
}
