import { Head, useForm, router } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
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

    const { data, setData, post, put, processing, errors, reset } = useForm({
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
        
        if (editingClinica) {
            put(`/clinicas/${editingClinica.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Clinica atualizada!', type: 'success' }); },
            });
        } else {
            post('/clinicas', {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Clinica cadastrada!', type: 'success' }); },
            });
        }
    };

    const handleDelete = () => {
        if (editingClinica) {
            router.delete(`/clinicas/${editingClinica.id}`, {
                onSuccess: () => { closeDrawer(); setToast({ message: 'Clinica excluida!', type: 'success' }); },
            });
        }
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
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Clinicas</h1>
                        <p className="text-gray-600 mt-1">Gerencie as clinicas cadastradas</p>
                    </div>
                    <button onClick={openCreateDrawer} className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 flex items-center gap-2">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Nova Clinica
                    </button>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <form onSubmit={handleSearch} className="flex gap-4">
                        <input type="text" placeholder="Buscar por nome ou CNPJ..." value={search} onChange={(e) => setSearch(e.target.value)} className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500" />
                        <button type="submit" className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Buscar</button>
                    </form>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
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
                            {clinicasList.length > 0 ? clinicasList.map((clinica) => (
                                <tr key={clinica.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center">
                                            <div className="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                                <span className="text-sm font-medium text-purple-700">{clinica.nome?.charAt(0)}</span>
                                            </div>
                                            <div className="text-sm font-medium text-gray-900">{clinica.nome}</div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{clinica.cnpj || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{clinica.cidade ? `${clinica.cidade}/${clinica.uf}` : '-'}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 text-xs rounded-full ${clinica.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {clinica.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button onClick={() => openEditDrawer(clinica)} className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg">Editar</button>
                                    </td>
                                </tr>
                            )) : <tr><td colSpan="5" className="px-6 py-12 text-center text-gray-500">Nenhuma clinica encontrada</td></tr>}
                        </tbody>
                    </table>
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
                                {editingClinica && !showDeleteConfirm && <button type="button" onClick={() => setShowDeleteConfirm(true)} className="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Excluir</button>}
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
