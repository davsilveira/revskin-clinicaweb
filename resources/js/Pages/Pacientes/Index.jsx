import { Head, useForm, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import MaskedInput from '@/Components/Form/MaskedInput';
import Select from '@/Components/Form/Select';
import { validateCPF } from '@/utils/validations';

export default function PacientesIndex({ pacientes, filters }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingPaciente, setEditingPaciente] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');
    const [loadingCep, setLoadingCep] = useState(false);
    const [cpfError, setCpfError] = useState(null);

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
        anotacoes: '',
        ativo: true,
    });

    const openCreateDrawer = () => {
        reset();
        setEditingPaciente(null);
        setShowDeleteConfirm(false);
        setDrawerOpen(true);
    };

    const openEditDrawer = (paciente) => {
        setEditingPaciente(paciente);
        setShowDeleteConfirm(false);
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
            anotacoes: paciente.anotacoes || '',
            ativo: paciente.ativo ?? true,
        });
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingPaciente(null);
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
                    setToast({ message: 'Paciente excluído com sucesso!', type: 'success' });
                },
            });
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/pacientes', { search }, { preserveState: true });
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
                            <MaskedInput
                                label="Telefone"
                                mask="(00) 0000-0000"
                                value={data.telefone1}
                                onChange={(e) => setData('telefone1', e.target.value)}
                                placeholder="(00) 0000-0000"
                            />
                            <MaskedInput
                                label="Celular"
                                mask="(00) 00000-0000"
                                value={data.telefone2}
                                onChange={(e) => setData('telefone2', e.target.value)}
                                placeholder="(00) 00000-0000"
                            />
                        </div>

                        <div className="border-t pt-6">
                            <h3 className="text-sm font-medium text-gray-900 mb-4">Endereço</h3>
                            <div className="grid grid-cols-6 gap-4">
                                <div className="col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                    <div className="flex gap-2">
                                        <MaskedInput
                                            mask="00000-000"
                                            value={data.cep}
                                            onChange={(e) => setData('cep', e.target.value)}
                                            onBlur={buscarCep}
                                            placeholder="00000-000"
                                            className="flex-1"
                                        />
                                        <button
                                            type="button"
                                            onClick={buscarCep}
                                            disabled={loadingCep}
                                            className="px-3 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50"
                                        >
                                            {loadingCep ? '...' : '🔍'}
                                        </button>
                                    </div>
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
                                    <Select
                                        label="UF"
                                        value={data.uf}
                                        onChange={(e) => setData('uf', e.target.value)}
                                        options={[
                                            { value: '', label: 'UF' },
                                            ...['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => ({ value: uf, label: uf }))
                                        ]}
                                    />
                                </div>
                            </div>
                        </div>

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
                            <div>
                                {editingPaciente && !showDeleteConfirm && (
                                    <button type="button" onClick={() => setShowDeleteConfirm(true)} className="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">
                                        Excluir
                                    </button>
                                )}
                                {showDeleteConfirm && (
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm">Confirmar exclusão?</span>
                                        <button type="button" onClick={handleDelete} className="px-3 py-1 bg-red-600 text-white rounded">Sim</button>
                                        <button type="button" onClick={() => setShowDeleteConfirm(false)} className="px-3 py-1 bg-gray-200 rounded">Não</button>
                                    </div>
                                )}
                            </div>
                            <div className="flex gap-3">
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
