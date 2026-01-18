import { Head, useForm, router } from '@inertiajs/react';
import { useState, useCallback, useEffect, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import MaskedInput from '@/Components/Form/MaskedInput';
import Select from '@/Components/Form/Select';
import { validateCPF } from '@/utils/validations';
import useAutoSave from '@/hooks/useAutoSave';
import countries from '@/utils/countries';
import debounce from 'lodash/debounce';

export default function PacientesIndex({ pacientes, medicos = [], tiposTelefone = {}, isAdmin = false, filters }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingPaciente, setEditingPaciente] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.ativo ?? '1');
    const [loadingCep, setLoadingCep] = useState(false);
    const [cpfError, setCpfError] = useState(null);
    const [currentPacienteId, setCurrentPacienteId] = useState(null);
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
    } = useAutoSave(performAutoSave, 2000, drawerOpen && data.nome.length >= 2);

    // Trigger autosave when data changes
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (drawerOpen && data.nome.length >= 2) {
            triggerAutoSave();
        }
    }, [data, drawerOpen]);

    const openCreateDrawer = () => {
        reset();
        setEditingPaciente(null);
        setCurrentPacienteId(null);
        setSelectedMedico(null);
        setSearchMedico('');
        setShowDeleteConfirm(false);
        isFirstRender.current = true;
        setDrawerOpen(true);
    };

    const openEditDrawer = (paciente) => {
        setEditingPaciente(paciente);
        setCurrentPacienteId(paciente.id);
        setShowDeleteConfirm(false);
        isFirstRender.current = true;
        
        // Set medico if exists
        if (paciente.medico) {
            setSelectedMedico(paciente.medico);
        } else {
            setSelectedMedico(null);
        }
        setSearchMedico('');
        
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
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        cancelAutoSave();
        setDrawerOpen(false);
        setEditingPaciente(null);
        setCurrentPacienteId(null);
        setSelectedMedico(null);
        setShowDeleteConfirm(false);
        setCpfError(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        // Validar CPF se preenchido
        if (data.cpf && data.cpf.replace(/\D/g, '').length > 0) {
            if (!validateCPF(data.cpf)) {
                setCpfError('CPF inválido. Por favor, verifique os números digitados.');
                return;
            }
        }
        setCpfError(null);
        
        if (editingPaciente) {
            put(`/pacientes/${editingPaciente.id}`, {
                onSuccess: () => {
                    closeDrawer();
                    setToast({ message: 'Paciente atualizado com sucesso!', type: 'success' });
                },
            });
        } else {
            post('/pacientes', {
                onSuccess: () => {
                    closeDrawer();
                    setToast({ message: 'Paciente cadastrado com sucesso!', type: 'success' });
                },
            });
        }
    };

    const handleDelete = () => {
        if (editingPaciente) {
            router.delete(`/pacientes/${editingPaciente.id}`, {
                onSuccess: () => {
                    closeDrawer();
                    setToast({ message: 'Paciente desativado com sucesso!', type: 'success' });
                },
            });
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        const params = { search, ativo: status };
        router.get('/pacientes', params, { preserveState: true });
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

    const pacientesList = pacientes?.data || pacientes || [];

    return (
        <DashboardLayout>
            <Head title="Pacientes" />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Pacientes</h1>
                        <p className="text-gray-600 mt-1">Gerencie os pacientes cadastrados</p>
                    </div>
                    <button
                        onClick={openCreateDrawer}
                        className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Novo Paciente
                    </button>
                </div>

                {/* Search */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex gap-4">
                        <input
                            type="text"
                            placeholder="Buscar por nome ou CPF..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        />
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="">Todos</option>
                            <option value="1">Ativos</option>
                            <option value="0">Inativos</option>
                        </select>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Buscar
                        </button>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefone</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cidade</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {pacientesList.length > 0 ? (
                                pacientesList.map((paciente) => (
                                    <tr key={paciente.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mr-3">
                                                    <span className="text-sm font-medium text-emerald-700">
                                                        {paciente.nome?.charAt(0).toUpperCase()}
                                                    </span>
                                                </div>
                                                <div className="text-sm font-medium text-gray-900">{paciente.nome}</div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.cpf || '-'}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.telefone2 || paciente.telefone1 || '-'}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.cidade ? `${paciente.cidade}/${paciente.uf}` : '-'}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-1 text-xs font-medium rounded-full ${paciente.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                {paciente.ativo ? 'Ativo' : 'Inativo'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right">
                                            <button
                                                onClick={() => openEditDrawer(paciente)}
                                                className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors"
                                            >
                                                Editar
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                        Nenhum paciente encontrado
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {pacientes?.links && pacientes.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                            <div className="text-sm text-gray-500">
                                Mostrando {pacientes.from} a {pacientes.to} de {pacientes.total}
                            </div>
                            <div className="flex gap-1">
                                {pacientes.links.map((link, i) => (
                                    <button
                                        key={i}
                                        onClick={() => link.url && router.get(link.url)}
                                        disabled={!link.url}
                                        className={`px-3 py-1 rounded text-sm ${link.active ? 'bg-emerald-600 text-white' : link.url ? 'bg-gray-100 hover:bg-gray-200' : 'bg-gray-50 text-gray-400'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Drawer */}
            <Drawer
                isOpen={drawerOpen}
                onClose={closeDrawer}
                title={editingPaciente ? 'Editar Paciente' : 'Novo Paciente'}
                size="lg"
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
                                <div className="col-span-6">
                                    <Select
                                        label="País"
                                        value={data.pais}
                                        onChange={(e) => setData('pais', e.target.value)}
                                        options={countries}
                                    />
                                </div>
                            </div>
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
                            {data.telefones.length > 0 ? (
                                <div className="space-y-3">
                                    {data.telefones.map((telefone, index) => (
                                        <div key={index} className="flex items-center gap-2">
                                            <div className="flex-1">
                                                {isBrazil ? (
                                                    <MaskedInput
                                                        mask="(00) 00000-0000"
                                                        value={telefone.numero}
                                                        onChange={(e) => updateTelefone(index, 'numero', e.target.value)}
                                                        placeholder="(00) 00000-0000"
                                                    />
                                                ) : (
                                                    <Input
                                                        value={telefone.numero}
                                                        onChange={(e) => updateTelefone(index, 'numero', e.target.value)}
                                                        placeholder="Número com código"
                                                    />
                                                )}
                                            </div>
                                            <div className="w-32">
                                                <Select
                                                    value={telefone.tipo}
                                                    onChange={(e) => updateTelefone(index, 'tipo', e.target.value)}
                                                    options={Object.entries(tiposTelefone).map(([value, label]) => ({ value, label }))}
                                                />
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => removeTelefone(index)}
                                                className="p-2 text-red-600 hover:bg-red-50 rounded"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500">Clique em "Adicionar" para incluir mais telefones</p>
                            )}
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

                        <div className="border-t pt-6">
                            <Input
                                label="Observações"
                                value={data.anotacoes}
                                onChange={(e) => setData('anotacoes', e.target.value)}
                                multiline
                                rows={3}
                            />
                        </div>

                        {editingPaciente && (
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

                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                {editingPaciente && editingPaciente.ativo && !showDeleteConfirm && (
                                    <button type="button" onClick={() => setShowDeleteConfirm(true)} className="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">
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
                                {(isAutoSaving || lastSavedText) && (
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
                                <button type="button" onClick={closeDrawer} className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" disabled={processing} className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                                    {processing ? 'Salvando...' : 'Salvar'}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </Drawer>

            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
