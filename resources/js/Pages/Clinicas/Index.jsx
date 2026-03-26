import { Head, useForm, router } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';
import MaskedInput from '@/Components/Form/MaskedInput';
import debounce from 'lodash/debounce';
import { validateCNPJ } from '@/utils/validations';

export default function ClinicasIndex({ clinicas, filters }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingClinica, setEditingClinica] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');
    const [loadingCep, setLoadingCep] = useState(false);

    // Medico search states
    const [searchMedico, setSearchMedico] = useState('');
    const [medicoResults, setMedicoResults] = useState([]);
    const [showMedicoDropdown, setShowMedicoDropdown] = useState(false);
    const [selectedMedicos, setSelectedMedicos] = useState([]);
    const [loadingMedicos, setLoadingMedicos] = useState(false);
    const [cnpjError, setCnpjError] = useState('');

    const { data, setData, processing, errors, reset } = useForm({
        nome: '',
        cnpj: '',
        email: '',
        telefone: '',
        cep: '',
        endereco: '',
        numero: '',
        complemento: '',
        bairro: '',
        cidade: '',
        uf: '',
        ativo: true,
        medico_ids: [],
        logo: null,
        remover_logo: false,
    });

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
                // Filter out already selected medicos
                const filtered = results.filter(m => !selectedMedicos.find(s => s.id === m.id));
                setMedicoResults(filtered);
                setShowMedicoDropdown(true);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingMedicos(false);
            }
        }, 300),
        [selectedMedicos]
    );

    useEffect(() => {
        if (searchMedico) {
            searchMedicosApi(searchMedico);
        }
    }, [searchMedico, searchMedicosApi]);

    const addMedico = (medico) => {
        const newMedicos = [...selectedMedicos, medico];
        setSelectedMedicos(newMedicos);
        setData('medico_ids', newMedicos.map(m => m.id));
        setSearchMedico('');
        setShowMedicoDropdown(false);
    };

    const removeMedico = (medicoId) => {
        const newMedicos = selectedMedicos.filter(m => m.id !== medicoId);
        setSelectedMedicos(newMedicos);
        setData('medico_ids', newMedicos.map(m => m.id));
    };

    const openCreateDrawer = () => {
        reset();
        setEditingClinica(null);
        setSelectedMedicos([]);
        setSearchMedico('');
        setShowDeleteConfirm(false);
        setDrawerOpen(true);
    };

    const openEditDrawer = (clinica) => {
        setEditingClinica(clinica);
        setShowDeleteConfirm(false);
        setSelectedMedicos(clinica.medicos || []);
        setSearchMedico('');
        setData({
            nome: clinica.nome || '',
            cnpj: clinica.cnpj || '',
            email: clinica.email || '',
            telefone: clinica.telefone || '',
            cep: clinica.cep || '',
            endereco: clinica.endereco || '',
            numero: clinica.numero || '',
            complemento: clinica.complemento || '',
            bairro: clinica.bairro || '',
            cidade: clinica.cidade || '',
            uf: clinica.uf || '',
            ativo: clinica.ativo ?? true,
            medico_ids: clinica.medicos?.map(m => m.id) || [],
            logo: null,
            remover_logo: false,
        });
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingClinica(null);
        setSelectedMedicos([]);
        setShowDeleteConfirm(false);
        setCnpjError('');
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Validate CNPJ before submitting
        if (data.cnpj && data.cnpj.replace(/\D/g, '').length > 0 && !validateCNPJ(data.cnpj)) {
            setCnpjError('CNPJ inválido');
            return;
        }

        const submitData = { ...data };
        const options = {
            forceFormData: true,
            onSuccess: () => {
                closeDrawer();
                setToast({
                    message: editingClinica ? 'Clinica atualizada!' : 'Clinica cadastrada!',
                    type: 'success',
                });
            },
        };

        if (editingClinica) {
            router.post(`/clinicas/${editingClinica.id}`, {
                ...submitData,
                _method: 'PUT',
            }, options);
        } else {
            router.post('/clinicas', submitData, options);
        }
    };

    const handleDelete = () => {
        if (editingClinica) {
            router.delete(`/clinicas/${editingClinica.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Clinica excluida!', type: 'success' }); },
            });
        }
    };

    const handleToggleStatus = (clinica) => {
        router.put(`/clinicas/${clinica.id}`, {
            ...clinica,
            ativo: !clinica.ativo,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setToast({ 
                    message: clinica.ativo ? 'Clínica desativada!' : 'Clínica ativada!', 
                    type: 'success' 
                });
            },
        });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/clinicas', { search }, { preserveState: true });
    };

    const buscarCep = useCallback(async () => {
        const cepLimpo = data.cep?.replace(/\D/g, '');
        if (!cepLimpo || cepLimpo.length < 8) return;
        setLoadingCep(true);
        try {
            const response = await fetch(`/api/cep/${cepLimpo}`);
            const result = await response.json();
            if (result.success) {
                setData(prev => ({ ...prev, endereco: result.data.logradouro || '', bairro: result.data.bairro || '', cidade: result.data.localidade || '', uf: result.data.uf || '' }));
            }
        } catch (e) { console.error(e); } finally { setLoadingCep(false); }
    }, [data.cep]);

    const handleCnpjBlur = () => {
        if (data.cnpj && data.cnpj.replace(/\D/g, '').length > 0) {
            if (!validateCNPJ(data.cnpj)) {
                setCnpjError('CNPJ inválido');
            } else {
                setCnpjError('');
            }
        } else {
            setCnpjError('');
        }
    };

    const clinicasList = clinicas?.data || clinicas || [];

    return (
        <DashboardLayout>
            <Head title="Clinicas" />
            <div className="py-4 lg:py-6 px-0">
                <PageHeader
                    title="Clinicas"
                    description="Gerencie as clinicas cadastradas"
                    actions={
                        <button
                            type="button"
                            onClick={openCreateDrawer}
                            className="w-full sm:w-auto justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 flex items-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            Nova Clinica
                        </button>
                    }
                />

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex flex-col gap-3 sm:flex-row sm:items-stretch sm:gap-4 min-w-0">
                        <input
                            type="text"
                            placeholder="Buscar por nome ou CNPJ..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full min-w-0 flex-1 px-4 py-2.5 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        />
                        <button
                            type="submit"
                            className="w-full sm:w-auto min-h-[44px] shrink-0 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                        >
                            Buscar
                        </button>
                    </form>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <ResponsiveEntityList
                        desktop={
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CNPJ</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cidade</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {clinicasList.length > 0 ? (
                                        clinicasList.map((clinica) => (
                                            <tr key={clinica.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 text-sm font-medium text-gray-900">{clinica.nome}</td>
                                                <td className="px-6 py-4 text-sm text-gray-500">{clinica.cnpj || '-'}</td>
                                                <td className="px-6 py-4 text-sm text-gray-500">{clinica.cidade ? `${clinica.cidade}/${clinica.uf}` : '-'}</td>
                                                <td className="px-6 py-4">
                                                    <span className={`px-2 py-1 text-xs rounded-full ${clinica.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                        {clinica.ativo ? 'Ativo' : 'Inativo'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditDrawer(clinica)}
                                                        className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                        title="Editar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="5" className="px-6 py-12 text-center text-gray-500">
                                                Nenhuma clinica encontrada
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        }
                        mobile={
                            <div className="divide-y divide-gray-200">
                                {clinicasList.length > 0 ? (
                                    clinicasList.map((clinica) => (
                                        <div key={clinica.id} className="p-4 max-w-full">
                                            <div className="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                                <button
                                                    type="button"
                                                    className="w-full text-left p-3 min-h-[44px] hover:bg-gray-50 transition-colors"
                                                    onClick={() => openEditDrawer(clinica)}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <div className="font-medium text-gray-900 break-words">{clinica.nome}</div>
                                                            {(clinica.cnpj || clinica.cidade) && (
                                                                <div className="mt-1 space-y-0.5">
                                                                    {clinica.cnpj ? (
                                                                        <p className="text-sm text-gray-600">{clinica.cnpj}</p>
                                                                    ) : null}
                                                                    {clinica.cidade ? (
                                                                        <p className="text-sm text-gray-500">
                                                                            {clinica.uf ? `${clinica.cidade}/${clinica.uf}` : clinica.cidade}
                                                                        </p>
                                                                    ) : null}
                                                                </div>
                                                            )}
                                                        </div>
                                                        <span className={`flex-shrink-0 px-2 py-1 text-xs rounded-full ${clinica.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                            {clinica.ativo ? 'Ativo' : 'Inativo'}
                                                        </span>
                                                    </div>
                                                </button>
                                                <div className="flex flex-wrap items-center justify-end gap-1 px-2 py-2 border-t border-gray-100 bg-gray-50/60">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditDrawer(clinica)}
                                                        className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                        aria-label="Editar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="px-4 py-12 text-center text-gray-500">Nenhuma clinica encontrada</div>
                                )}
                            </div>
                        }
                    />
                </div>
            </div>

            <Drawer isOpen={drawerOpen} onClose={closeDrawer} title={editingClinica ? 'Editar Clinica' : 'Nova Clinica'} size="lg">
                <form onSubmit={handleSubmit} className="flex flex-col h-full">
                    <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                        <Input label="Nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} error={errors.nome} required />
                        <MaskedInput 
                            label="CNPJ" 
                            value={data.cnpj} 
                            onAccept={(value) => setData('cnpj', value)}
                            onBlur={handleCnpjBlur}
                            mask="00.000.000/0000-00"
                            placeholder="00.000.000/0000-00"
                            error={cnpjError || errors.cnpj}
                        />
                        <Input label="E-mail" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        <MaskedInput 
                            label="Telefone" 
                            value={data.telefone} 
                            onAccept={(value) => setData('telefone', value)}
                            mask={[
                                { mask: '(00) 0000-0000' },
                                { mask: '(00) 00000-0000' }
                            ]}
                            placeholder="(00) 0000-0000"
                        />

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Logo da clínica (imagem)</label>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                onChange={(e) => {
                                    setData('logo', e.target.files[0] || null);
                                    setData('remover_logo', false);
                                }}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg"
                            />
                            {errors.logo && <p className="mt-1 text-sm text-red-600">{errors.logo}</p>}
                            {editingClinica?.logo_url && !data.remover_logo && (
                                <div className="mt-2 flex items-center gap-2">
                                    <img src={editingClinica.logo_url} alt="Logo" className="h-16 max-w-[120px] object-contain border border-gray-200 rounded-lg" />
                                    <button
                                        type="button"
                                        onClick={() => setData('remover_logo', true)}
                                        className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Remover logo"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            )}
                            {data.remover_logo && (
                                <div className="mt-2 flex items-center gap-3 text-sm text-gray-500">
                                    <span>Logo será removida ao salvar</span>
                                    <button
                                        type="button"
                                        onClick={() => setData('remover_logo', false)}
                                        className="text-emerald-600 hover:underline"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            )}
                        </div>
                        
                        <div className="border-t pt-6">
                            <h3 className="text-sm font-medium text-gray-900 mb-4">Endereco</h3>
                            <div className="grid grid-cols-6 gap-4">
                                <div className="col-span-2">
                                    <MaskedInput 
                                        label="CEP" 
                                        value={data.cep} 
                                        onAccept={(value) => setData('cep', value)}
                                        onBlur={buscarCep}
                                        mask="00000-000"
                                        placeholder="00000-000"
                                    />
                                    {loadingCep && <span className="text-xs text-gray-500">Buscando...</span>}
                                </div>
                                <div className="col-span-4"><Input label="Endereco" value={data.endereco} onChange={(e) => setData('endereco', e.target.value)} /></div>
                                <div className="col-span-1"><Input label="Numero" value={data.numero} onChange={(e) => setData('numero', e.target.value)} /></div>
                                <div className="col-span-2"><Input label="Complemento" value={data.complemento} onChange={(e) => setData('complemento', e.target.value)} /></div>
                                <div className="col-span-3"><Input label="Bairro" value={data.bairro} onChange={(e) => setData('bairro', e.target.value)} /></div>
                                <div className="col-span-4"><Input label="Cidade" value={data.cidade} onChange={(e) => setData('cidade', e.target.value)} /></div>
                                <div className="col-span-2"><Select label="UF" value={data.uf} onChange={(e) => setData('uf', e.target.value)} options={[{ value: '', label: 'UF' }, ...['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => ({ value: uf, label: uf }))]} /></div>
                            </div>
                        </div>
                        {/* Medicos Section */}
                        <div className="border-t pt-6">
                            <h3 className="text-sm font-medium text-gray-900 mb-4">Médicos Vinculados</h3>
                            
                            {/* Selected Medicos */}
                            {selectedMedicos.length > 0 && (
                                <div className="flex flex-wrap gap-2 mb-4">
                                    {selectedMedicos.map((medico) => (
                                        <div
                                            key={medico.id}
                                            className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-100 text-blue-800 rounded-full text-sm"
                                        >
                                            <span>{medico.nome}</span>
                                            {medico.crm && <span className="text-blue-600 text-xs">CRM: {medico.crm}</span>}
                                            <button
                                                type="button"
                                                onClick={() => removeMedico(medico.id)}
                                                className="text-blue-600 hover:text-blue-800"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            
                            {/* Medico Search */}
                            <div className="relative">
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
                                                onClick={() => addMedico(medico)}
                                                className="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                            >
                                                <div className="font-medium text-gray-900">{medico.nome}</div>
                                                <div className="text-sm text-gray-500">{medico.crm} - {medico.especialidade}</div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {editingClinica && <Select label="Status" value={data.ativo ? '1' : '0'} onChange={(e) => setData('ativo', e.target.value === '1')} options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />}
                    </div>
                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div>
                                {editingClinica && !showDeleteConfirm && (
                                    <button type="button" onClick={() => setShowDeleteConfirm(true)} className="flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Excluir
                                    </button>
                                )}
                                {showDeleteConfirm && <div className="flex items-center gap-2"><span className="text-sm">Confirmar?</span><button type="button" onClick={handleDelete} className="px-3 py-1 bg-red-600 text-white rounded">Sim</button><button type="button" onClick={() => setShowDeleteConfirm(false)} className="px-3 py-1 bg-gray-200 rounded">Nao</button></div>}
                            </div>
                            <div className="flex gap-3">
                                <button type="button" onClick={closeDrawer} className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                                <button type="submit" disabled={processing} className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">{processing ? 'Salvando...' : 'Salvar'}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </Drawer>
            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
