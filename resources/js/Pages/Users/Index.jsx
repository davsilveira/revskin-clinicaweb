import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useCallback, useRef } from 'react';
import debounce from 'lodash/debounce';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import useDrawerUnsavedChanges from '@/hooks/useDrawerUnsavedChanges';
import cloneDeep from 'lodash/cloneDeep';
import isEqual from 'lodash/isEqual';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';
import MedicoFormFields from '@/Components/MedicoFormFields';
import Pagination from '@/Components/Pagination';

function userFormComparable(d) {
    return {
        name: d.name ?? '',
        email: d.email ?? '',
        password: d.password ?? '',
        role: d.role,
        clinica_id: d.clinica_id ?? '',
        is_active: d.is_active,
        crm: d.crm ?? '',
        uf_crm: d.uf_crm ?? '',
        especialidade: d.especialidade ?? '',
        telefone: d.telefone ?? '',
        celular: d.celular ?? '',
        clinica_ids: [...(d.clinica_ids || [])].slice().sort((a, b) => a - b),
        assinatura: d.assinatura ? 1 : 0,
        remover_assinatura: !!d.remover_assinatura,
        ativo: d.ativo,
        enderecos: (d.enderecos || []).map((e) => ({
            nome: e.nome ?? '',
            cep: e.cep ?? '',
            endereco: e.endereco ?? '',
            numero: e.numero ?? '',
            complemento: e.complemento ?? '',
            bairro: e.bairro ?? '',
            cidade: e.cidade ?? '',
            uf: e.uf ?? '',
        })),
    };
}

function roleFilterFromFilters(filters, tinyEnabled) {
    const r = filters?.role;
    if (!r || r === '') return 'all';
    if (tinyEnabled && r === 'callcenter') return 'all';
    return r;
}

export default function UsersIndex({ users, clinicas = [], filters = {} }) {
    const { props } = usePage();
    const auth = props.auth || {};
    const tinyEnabled = auth.tinyEnabled || false;
    const serverErrors = props.errors || {};

    const [search, setSearch] = useState(() =>
        filters.search != null && filters.search !== '' ? String(filters.search) : ''
    );
    const [roleFilter, setRoleFilter] = useState(() => roleFilterFromFilters(filters, tinyEnabled));
    const skipNextUsersFetch = useRef(true);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [toast, setToast] = useState(null);

    const isMedico = (role) => role === 'medico';

    const isEditingSelf = Boolean(
        editingUser &&
        auth.user?.id != null &&
        String(editingUser.id) === String(auth.user.id)
    );

    useEffect(() => {
        const nextSearch = filters.search != null && filters.search !== '' ? String(filters.search) : '';
        setSearch((prev) => (prev === nextSearch ? prev : nextSearch));
        const nextRole = roleFilterFromFilters(filters, tinyEnabled);
        setRoleFilter((prev) => (prev === nextRole ? prev : nextRole));
    }, [filters?.search, filters?.role, tinyEnabled]);

    const [formBaseline, setFormBaseline] = useState(null);

    const { data, setData, setError, clearErrors, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'medico',
        clinica_id: '',
        is_active: true,
        // Campos médico (quando role=medico)
        crm: '',
        uf_crm: '',
        especialidade: '',
        telefone: '',
        celular: '',
        clinica_ids: [],
        assinatura: null,
        remover_assinatura: false,
        ativo: true,
        enderecos: [],
    });

    const openCreateDrawer = () => {
        reset();
        setEditingUser(null);
        setShowDeleteConfirm(false);
        setDrawerOpen(true);
    };

    const openEditDrawer = (user) => {
        setEditingUser(user);
        setShowDeleteConfirm(false);
        const medico = user.medico;
        const rawEnderecos = medico?.enderecos || [];
        const enderecoUf = (rawEnderecos[0]?.uf || '').trim().toUpperCase();
        const ufCrmFromMedico = (medico?.uf_crm || '').trim();
        const uf_crm =
            ufCrmFromMedico || (enderecoUf.length === 2 ? enderecoUf : '');
        setData({
            name: user.name,
            email: user.email,
            password: '',
            role: user.role,
            clinica_id: user.clinica_id ? String(user.clinica_id) : '',
            is_active: user.is_active,
            crm: medico?.crm || '',
            uf_crm,
            especialidade: medico?.especialidade || '',
            telefone: medico?.telefone1 || '',
            celular: medico?.telefone2 || '',
            clinica_ids: medico?.clinicas?.map(c => c.id) || [],
            assinatura: null,
            remover_assinatura: false,
            ativo: medico?.ativo ?? true,
            enderecos: rawEnderecos.map(e => ({
                nome: e.nome || '',
                cep: e.cep || '',
                endereco: e.endereco || '',
                numero: e.numero || '',
                complemento: e.complemento || '',
                bairro: e.bairro || '',
                cidade: e.cidade || '',
                uf: e.uf || '',
            })) || [],
        });
        setDrawerOpen(true);
    };

    const forceCloseDrawer = () => {
        setDrawerOpen(false);
        setEditingUser(null);
        setShowDeleteConfirm(false);
        reset();
    };

    useEffect(() => {
        if (!drawerOpen) {
            setFormBaseline(null);
            return;
        }
        setFormBaseline(cloneDeep(userFormComparable(data)));
    }, [drawerOpen, editingUser?.id]);

    const validateBaseFields = () => {
        const errs = {};
        if (!data.name?.trim()) errs.name = 'O nome é obrigatório.';
        if (!data.email?.trim()) errs.email = 'O e-mail é obrigatório.';
        if (!editingUser && !data.password) errs.password = 'A senha é obrigatória.';
        if (data.role === 'secretaria' && !data.clinica_id) errs.clinica_id = 'A clínica é obrigatória.';
        return errs;
    };

    const validateMedicoFields = () => {
        const errs = {};
        if (!data.crm?.trim()) errs.crm = 'O CRM é obrigatório.';
        if (!data.uf_crm?.trim()) errs.uf_crm = 'A UF do CRM é obrigatória.';
        const telDigits = (data.telefone || '').replace(/\D/g, '');
        const celDigits = (data.celular || '').replace(/\D/g, '');
        if (telDigits.length < 10 && celDigits.length < 10) {
            const msg = 'Informe telefone ou celular com DDD (mínimo 10 dígitos).';
            errs.telefone = msg;
            errs.celular = msg;
        }
        return errs;
    };

    const isFormValid = useMemo(() => {
        const baseErrs = validateBaseFields();
        if (Object.keys(baseErrs).length > 0) return false;
        if (isMedico(data.role)) {
            const medicoErrs = validateMedicoFields();
            if (Object.keys(medicoErrs).length > 0) return false;
        }
        return true;
    }, [data, editingUser]);

    const trySaveUser = useCallback(
        ({ onSuccess, onError, onInvalid }) => {
            clearErrors();
            const baseErrs = validateBaseFields();
            if (Object.keys(baseErrs).length > 0) {
                setError(baseErrs);
                onInvalid?.();
                return;
            }

            let submitData = { ...data };
            if (isMedico(data.role)) {
                submitData.enderecos = (data.enderecos || []).filter((ev) => ev.nome && ev.nome.trim());
                submitData.email = data.email;

                const medicoErrs = validateMedicoFields();
                if (Object.keys(medicoErrs).length > 0) {
                    setError(medicoErrs);
                    onInvalid?.();
                    return;
                }
            }

            const options = {
                forceFormData: isMedico(data.role),
                preserveScroll: true,
                onSuccess,
                onError,
            };

            if (editingUser) {
                router.put(`/users/${editingUser.id}`, submitData, options);
            } else {
                router.post('/users', submitData, options);
            }
        },
        [data, editingUser, clearErrors, setError]
    );

    const isDirty = useMemo(() => {
        if (!drawerOpen || !formBaseline) return false;
        return !isEqual(userFormComparable(data), formBaseline);
    }, [drawerOpen, formBaseline, data]);

    const saveBeforeClose = useCallback(
        () =>
            new Promise((resolve) => {
                trySaveUser({
                    onSuccess: () => resolve(true),
                    onError: () => resolve(false),
                    onInvalid: () => resolve(false),
                });
            }),
        [trySaveUser]
    );

    const {
        requestClose,
        showUnsavedModal,
        savingBeforeLeave,
        handleUnsavedCancel,
        handleUnsavedDiscard,
        handleUnsavedSave,
    } = useDrawerUnsavedChanges({
        isOpen: drawerOpen,
        isDirty,
        onConfirmClose: forceCloseDrawer,
        saveBeforeClose,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        trySaveUser({
            onSuccess: () => {
                forceCloseDrawer();
                setToast({
                    message: editingUser ? 'Usuário atualizado com sucesso!' : 'Usuário criado com sucesso!',
                    type: 'success',
                });
            },
        });
    };

    const handleDelete = () => {
        if (editingUser) {
            router.delete(`/users/${editingUser.id}`, {
                onSuccess: () => {
                    forceCloseDrawer();
                    setToast({ message: 'Usuário excluído com sucesso!', type: 'success' });
                },
            });
        }
    };

    const buildQuery = useCallback(
        (extra = {}) => {
            const q = { ...extra };
            if (search?.trim()) q.search = search.trim();
            if (roleFilter && roleFilter !== 'all') q.role = roleFilter;
            return q;
        },
        [search, roleFilter]
    );

    const runUsersQuery = useMemo(
        () =>
            debounce((query) => {
                router.get('/users', query, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['users', 'filters'],
                });
            }, 350),
        []
    );

    useEffect(() => {
        if (skipNextUsersFetch.current) {
            skipNextUsersFetch.current = false;
            return;
        }
        runUsersQuery(buildQuery());
    }, [search, roleFilter, buildQuery, runUsersQuery]);

    useEffect(() => () => runUsersQuery.cancel(), [runUsersQuery]);

    const handleToggleStatus = (user) => {
        router.put(`/users/${user.id}`, {
            ...user,
            is_active: !user.is_active,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setToast({
                    message: user.is_active ? 'Usuário desativado!' : 'Usuário ativado!',
                    type: 'success'
                });
            },
        });
    };

    const getRoleBadge = (role) => {
        switch (role) {
            case 'admin': return 'bg-purple-100 text-purple-800';
            case 'medico': return 'bg-emerald-100 text-emerald-800';
            case 'callcenter': return 'bg-blue-100 text-blue-800';
            case 'secretaria': return 'bg-amber-100 text-amber-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getRoleLabel = (role) => {
        switch (role) {
            case 'admin': return 'Administrador';
            case 'medico': return 'Médico';
            case 'callcenter': return 'Call Center';
            case 'secretaria': return 'Secretária';
            default: return role;
        }
    };

    const drawerWidth = isMedico(data.role)
        ? 'w-full max-w-[100vw] sm:max-w-[900px] sm:w-[900px]'
        : 'w-full max-w-[100vw] sm:max-w-[700px] sm:w-[700px]';

    return (
        <DashboardLayout>
            <Head title="Usuários" />

            <div className="py-4 lg:py-6 px-0">
                <PageHeader
                    title="Gerenciamento de Usuários"
                    description="Gerencie administradores e usuários do sistema"
                    actions={
                        <button
                            type="button"
                            onClick={openCreateDrawer}
                            className="w-full sm:w-auto justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            Novo Usuário
                        </button>
                    }
                />

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                        <input
                            type="text"
                            placeholder="Buscar por nome ou e-mail..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            autoComplete="off"
                            className="w-full min-w-0 flex-1 sm:min-w-[200px] px-4 py-2.5 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        />
                        <select
                            value={roleFilter}
                            onChange={(e) => setRoleFilter(e.target.value)}
                            className="w-full sm:w-auto min-h-[44px] px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        >
                            <option value="all">Todos</option>
                            <option value="admin">Administrador</option>
                            <option value="medico">Médico</option>
                            <option value="secretaria">Secretária</option>
                            {!tinyEnabled && <option value="callcenter">Call Center</option>}
                        </select>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <ResponsiveEntityList
                        desktop={
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-mail</th>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perfil</th>
                                            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {(users.data || users).map((user) => (
                                            <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">{user.name}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-gray-900">{user.email}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getRoleBadge(user.role)}`}>
                                                        {getRoleLabel(user.role)}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                        {user.is_active ? 'Ativo' : 'Inativo'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditDrawer(user)}
                                                        className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                        title="Editar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        }
                        mobile={
                            <div className="divide-y divide-gray-200">
                                {(users.data || users).map((user) => (
                                    <div key={user.id} className="p-4 max-w-full">
                                        <div className="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                            <button
                                                type="button"
                                                className="w-full text-left p-3 min-h-[44px] hover:bg-gray-50 transition-colors"
                                                onClick={() => openEditDrawer(user)}
                                            >
                                                <div className="font-medium text-gray-900 break-words">{user.name}</div>
                                                <p className="text-sm text-gray-700 break-all mt-1">{user.email}</p>
                                                <div className="flex flex-wrap gap-2 mt-3">
                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getRoleBadge(user.role)}`}>
                                                        {getRoleLabel(user.role)}
                                                    </span>
                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                        {user.is_active ? 'Ativo' : 'Inativo'}
                                                    </span>
                                                </div>
                                            </button>
                                            <div className="flex flex-wrap items-center justify-end gap-1 px-2 py-2 border-t border-gray-100 bg-gray-50/60">
                                                <button
                                                    type="button"
                                                    onClick={() => openEditDrawer(user)}
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
                                ))}
                            </div>
                        }
                    />
                    {users?.links && (
                        <Pagination links={users.links} preserveScroll />
                    )}
                </div>
            </div>

            <Drawer
                isOpen={drawerOpen}
                onClose={requestClose}
                title={editingUser ? 'Editar Usuário' : 'Novo Usuário'}
                width={drawerWidth}
            >
                <form onSubmit={handleSubmit} className="flex flex-col h-full">
                    <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                        <div className="space-y-6">
                            <h3 className="text-sm font-semibold text-gray-900 border-b pb-2">Dados de acesso</h3>
                                <Select
                                    label="Perfil"
                                    value={data.role}
                                    onChange={(e) => {
                                        const newRole = e.target.value;
                                        setData((prev) => ({
                                            ...prev,
                                            role: newRole,
                                            clinica_id: newRole === 'secretaria' ? prev.clinica_id : '',
                                        }));
                                    }}
                                    error={errors.role}
                                    required
                                    options={[
                                        { value: 'medico', label: 'Médico' },
                                        { value: 'callcenter', label: 'Call Center' },
                                        { value: 'secretaria', label: 'Secretária' },
                                        { value: 'admin', label: 'Administrador' },
                                    ].filter((o) => (tinyEnabled ? o.value !== 'callcenter' : true))}
                                />

                                <Input
                                    label="Nome"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    error={errors.name || serverErrors.name}
                                    required
                                />

                                <Input
                                    label="E-mail"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    error={errors.email || serverErrors.email}
                                    required
                                />

                                <Input
                                    label={editingUser ? 'Nova Senha (deixe em branco para manter)' : 'Senha'}
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    error={errors.password || serverErrors.password}
                                    required={!editingUser}
                                />

                                {data.role === 'secretaria' && (
                                    <Select
                                        label="Clínica"
                                        value={data.clinica_id}
                                        onChange={(e) => setData('clinica_id', e.target.value)}
                                        error={errors.clinica_id}
                                        required
                                        options={[
                                            { value: '', label: 'Selecione a clínica' },
                                            ...clinicas.map((c) => ({ value: String(c.id), label: c.nome })),
                                        ]}
                                    />
                                )}

                                {editingUser && (
                                    <Select
                                        label="Status"
                                        value={data.is_active ? '1' : '0'}
                                        onChange={(e) => setData('is_active', e.target.value === '1')}
                                        error={errors.is_active}
                                        required
                                        options={[
                                            { value: '1', label: 'Ativo' },
                                            { value: '0', label: 'Inativo' },
                                        ]}
                                    />
                                )}
                        </div>

                        {isMedico(data.role) && (
                            <div className="space-y-6 pt-6 border-t border-gray-200">
                                <h3 className="text-sm font-semibold text-gray-900 border-b pb-2">Dados profissionais</h3>
                                <MedicoFormFields
                                data={data}
                                setData={setData}
                                errors={{ ...serverErrors, ...errors }}
                                clinicas={clinicas}
                                existingMedico={editingUser?.medico}
                                showAtivo={!!editingUser}
                            />
                            </div>
                        )}
                    </div>

                    <div className="border-t border-gray-200 p-6 bg-gray-50">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                {editingUser && !isEditingSelf && !showDeleteConfirm && (
                                    <button
                                        type="button"
                                        onClick={() => setShowDeleteConfirm(true)}
                                        className="px-4 py-2.5 text-red-600 hover:text-red-700 hover:bg-red-50 font-semibold rounded-lg transition-all duration-200 flex items-center gap-2"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Excluir
                                    </button>
                                )}

                                {editingUser && !isEditingSelf && showDeleteConfirm && (
                                    <div className="flex items-center gap-3 animate-fade-in">
                                        <span className="text-sm text-gray-700 font-medium">Deseja realmente excluir?</span>
                                        <button type="button" onClick={handleDelete} className="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg">Sim</button>
                                        <button type="button" onClick={() => setShowDeleteConfirm(false)} className="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-lg">Não</button>
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center gap-3">
                                <button type="button" onClick={requestClose} className="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" disabled={!isFormValid || processing} className="px-8 py-2.5 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    {processing ? 'Salvando...' : 'Salvar'}
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

            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
