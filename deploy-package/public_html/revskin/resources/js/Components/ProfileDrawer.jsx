import { useForm, usePage, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';

export default function ProfileDrawer({ isOpen, onClose }) {
    const { auth } = usePage().props;
    const isMedico = auth.user?.role === 'medico';
    const [activeTab, setActiveTab] = useState('profile');
    const [toast, setToast] = useState(null);
    const [savingMedico, setSavingMedico] = useState(false);

    const profileForm = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const medicoForm = useForm({
        nome: auth.medico?.nome || '',
        crm: auth.medico?.crm || '',
        especialidade: auth.medico?.especialidade || '',
        telefone1: auth.medico?.telefone1 || '',
        telefone2: auth.medico?.telefone2 || '',
        email1: auth.medico?.email1 || '',
        rodape_receita: auth.medico?.rodape_receita || '',
        enderecos: auth.medico?.enderecos?.map(e => ({
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

    const [loadingCepIndex, setLoadingCepIndex] = useState(null);

    // Endereco management functions
    const addEndereco = () => {
        medicoForm.setData('enderecos', [...medicoForm.data.enderecos, { 
            nome: '', cep: '', endereco: '', numero: '', complemento: '', bairro: '', cidade: '', uf: '' 
        }]);
    };

    const removeEndereco = (index) => {
        const newEnderecos = [...medicoForm.data.enderecos];
        newEnderecos.splice(index, 1);
        medicoForm.setData('enderecos', newEnderecos);
    };

    const updateEndereco = (index, field, value) => {
        const newEnderecos = [...medicoForm.data.enderecos];
        newEnderecos[index] = { ...newEnderecos[index], [field]: value };
        medicoForm.setData('enderecos', newEnderecos);
    };

    const buscarCepEndereco = useCallback(async (index) => {
        const cepLimpo = medicoForm.data.enderecos[index]?.cep?.replace(/\D/g, '');
        if (!cepLimpo || cepLimpo.length < 8) return;
        setLoadingCepIndex(index);
        try {
            const response = await fetch(`/api/cep/${cepLimpo}`);
            const result = await response.json();
            if (result.success) {
                const newEnderecos = [...medicoForm.data.enderecos];
                newEnderecos[index] = {
                    ...newEnderecos[index],
                    endereco: result.data.logradouro || '',
                    bairro: result.data.bairro || '',
                    cidade: result.data.localidade || '',
                    uf: result.data.uf || '',
                };
                medicoForm.setData('enderecos', newEnderecos);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoadingCepIndex(null);
        }
    }, [medicoForm.data.enderecos]);

    const handleProfileUpdate = (e) => {
        e.preventDefault();
        profileForm.put('/profile', {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                setToast({ message: 'Perfil atualizado com sucesso!', type: 'success' });
            },
        });
    };

    const handlePasswordUpdate = (e) => {
        e.preventDefault();
        passwordForm.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
                onClose();
                setToast({ message: 'Senha atualizada com sucesso!', type: 'success' });
            },
        });
    };

    const handleMedicoUpdate = (e) => {
        e.preventDefault();
        
        setSavingMedico(true);
        
        // Filter enderecos before submitting (only send those with nome filled)
        const filteredEnderecos = medicoForm.data.enderecos.filter(e => e.nome && e.nome.trim());
        
        // Prepare data for submission
        const submitData = {
            ...medicoForm.data,
            enderecos: filteredEnderecos,
        };
        
        // Use router.put directly to avoid issues with transform
        router.put('/profile/medico', submitData, {
            preserveScroll: true,
            onSuccess: () => {
                // Update form data to reflect saved state
                medicoForm.setData('enderecos', filteredEnderecos);
                setToast({ message: 'Dados profissionais atualizados com sucesso!', type: 'success' });
                setSavingMedico(false);
                onClose();
            },
            onError: (errors) => {
                // Set errors on form if any
                Object.keys(errors).forEach(key => {
                    medicoForm.setError(key, errors[key]);
                });
                setSavingMedico(false);
            },
            onFinish: () => {
                setSavingMedico(false);
            },
        });
    };

    const handleAssinaturaUpload = (e) => {
        const file = e.target.files[0];
        if (file) {
            const formData = new FormData();
            formData.append('assinatura', file);
            router.post('/profile/assinatura', formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setToast({ message: 'Assinatura atualizada com sucesso!', type: 'success' });
                },
            });
        }
    };

    return (
        <>
            <Drawer
                isOpen={isOpen}
                onClose={onClose}
                title="Meu Perfil"
            >
                <div className="flex flex-col h-full">
                    {/* Tabs */}
                    <div className="border-b border-gray-200 px-6 pt-4">
                        <div className="flex gap-4 overflow-x-auto">
                            <button
                                onClick={() => setActiveTab('profile')}
                                className={`pb-4 text-sm font-medium transition-colors relative whitespace-nowrap ${
                                    activeTab === 'profile'
                                        ? 'text-blue-600'
                                        : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                <div className="flex items-center gap-2">
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Conta
                                </div>
                                {activeTab === 'profile' && (
                                    <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600"></div>
                                )}
                            </button>
                            {isMedico && auth.medico && (
                                <button
                                    onClick={() => setActiveTab('medico')}
                                    className={`pb-4 text-sm font-medium transition-colors relative whitespace-nowrap ${
                                        activeTab === 'medico'
                                            ? 'text-blue-600'
                                            : 'text-gray-600 hover:text-gray-900'
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Dados Profissionais
                                    </div>
                                    {activeTab === 'medico' && (
                                        <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600"></div>
                                    )}
                                </button>
                            )}
                            <button
                                onClick={() => setActiveTab('password')}
                                className={`pb-4 text-sm font-medium transition-colors relative whitespace-nowrap ${
                                    activeTab === 'password'
                                        ? 'text-blue-600'
                                        : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                <div className="flex items-center gap-2">
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Segurança
                                </div>
                                {activeTab === 'password' && (
                                    <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600"></div>
                                )}
                            </button>
                        </div>
                    </div>

                    {/* Profile Tab */}
                    {activeTab === 'profile' && (
                        <form onSubmit={handleProfileUpdate} className="flex flex-col flex-1">
                            <div className="flex-1 p-6 space-y-6">
                                <Input
                                    label="Nome"
                                    value={profileForm.data.name}
                                    onChange={(e) => profileForm.setData('name', e.target.value)}
                                    error={profileForm.errors.name}
                                    required
                                />

                                <Input
                                    label="E-mail"
                                    type="email"
                                    value={profileForm.data.email}
                                    onChange={(e) => profileForm.setData('email', e.target.value)}
                                    error={profileForm.errors.email}
                                    required
                                />

                                <div className="bg-gray-50 rounded-lg p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">Perfil</p>
                                            <p className="text-sm text-gray-600 capitalize">{auth.user.role}</p>
                                        </div>
                                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                                            auth.user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'
                                        }`}>
                                            {auth.user.role === 'admin' ? 'Administrador' : 'Usuário'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t border-gray-200 p-6 bg-gray-50">
                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={profileForm.processing}
                                        className="px-8 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-lg shadow-blue-600/30"
                                    >
                                        {profileForm.processing ? 'Salvando...' : 'Salvar Alterações'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    )}

                    {/* Medico Tab */}
                    {activeTab === 'medico' && isMedico && auth.medico && (
                        <form onSubmit={handleMedicoUpdate} className="flex flex-col flex-1">
                            <div className="flex-1 p-6 space-y-6 overflow-y-auto">
                                <Input
                                    label="Nome Completo"
                                    value={medicoForm.data.nome}
                                    onChange={(e) => medicoForm.setData('nome', e.target.value)}
                                    error={medicoForm.errors.nome}
                                    required
                                />

                                <div className="grid grid-cols-2 gap-4">
                                    <Input
                                        label="CRM"
                                        value={medicoForm.data.crm}
                                        onChange={(e) => medicoForm.setData('crm', e.target.value)}
                                        error={medicoForm.errors.crm}
                                    />
                                    <Input
                                        label="Especialidade"
                                        value={medicoForm.data.especialidade}
                                        onChange={(e) => medicoForm.setData('especialidade', e.target.value)}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <Input
                                        label="Telefone"
                                        value={medicoForm.data.telefone1}
                                        onChange={(e) => medicoForm.setData('telefone1', e.target.value)}
                                        placeholder="(00) 0000-0000"
                                    />
                                    <Input
                                        label="Celular"
                                        value={medicoForm.data.telefone2}
                                        onChange={(e) => medicoForm.setData('telefone2', e.target.value)}
                                        placeholder="(00) 00000-0000"
                                    />
                                </div>

                                <Input
                                    label="E-mail Profissional"
                                    type="email"
                                    value={medicoForm.data.email1}
                                    onChange={(e) => medicoForm.setData('email1', e.target.value)}
                                />

                                {/* Multiple Addresses Section */}
                                <div className="border-t pt-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-sm font-medium text-gray-900">Endereços</h3>
                                        <button
                                            type="button"
                                            onClick={addEndereco}
                                            className="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                            </svg>
                                            Adicionar Endereço
                                        </button>
                                    </div>
                                    {medicoForm.data.enderecos.length > 0 ? (
                                        <div className="space-y-3">
                                            {medicoForm.data.enderecos.map((endereco, index) => (
                                                <div key={index} className="border border-gray-200 rounded-lg p-3 relative bg-gray-50/50">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeEndereco(index)}
                                                        className="absolute top-2 right-2 p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                    <div className="space-y-2">
                                                        <div className="pr-6">
                                                            <Input
                                                                label="Nome do Endereço"
                                                                placeholder="Ex: Consultório, Residência, Comercial..."
                                                                value={endereco.nome}
                                                                onChange={(e) => updateEndereco(index, 'nome', e.target.value)}
                                                            />
                                                        </div>
                                                        <div className="grid grid-cols-12 gap-2">
                                                            <div className="col-span-3">
                                                                <label className="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                                                <input
                                                                    type="text"
                                                                    value={endereco.cep}
                                                                    onChange={(e) => updateEndereco(index, 'cep', e.target.value)}
                                                                    onBlur={() => buscarCepEndereco(index)}
                                                                    placeholder="00000-000"
                                                                    className="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm"
                                                                />
                                                                {loadingCepIndex === index && <span className="text-xs text-gray-500">Buscando...</span>}
                                                            </div>
                                                            <div className="col-span-9">
                                                                <Input label="Endereço" value={endereco.endereco} onChange={(e) => updateEndereco(index, 'endereco', e.target.value)} />
                                                            </div>
                                                            <div className="col-span-2">
                                                                <Input label="Nº" value={endereco.numero} onChange={(e) => updateEndereco(index, 'numero', e.target.value)} />
                                                            </div>
                                                            <div className="col-span-4">
                                                                <Input label="Complemento" value={endereco.complemento} onChange={(e) => updateEndereco(index, 'complemento', e.target.value)} />
                                                            </div>
                                                            <div className="col-span-6">
                                                                <Input label="Bairro" value={endereco.bairro} onChange={(e) => updateEndereco(index, 'bairro', e.target.value)} />
                                                            </div>
                                                            <div className="col-span-9">
                                                                <Input label="Cidade" value={endereco.cidade} onChange={(e) => updateEndereco(index, 'cidade', e.target.value)} />
                                                            </div>
                                                            <div className="col-span-3">
                                                                <Select
                                                                    label="UF"
                                                                    value={endereco.uf}
                                                                    onChange={(e) => updateEndereco(index, 'uf', e.target.value)}
                                                                    options={[
                                                                        { value: '', label: 'UF' },
                                                                        ...['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => ({ value: uf, label: uf }))
                                                                    ]}
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500">Clique em "Adicionar Endereço" para incluir endereços</p>
                                    )}
                                </div>

                                <div className="border-t pt-4">
                                    <Input
                                        label="Rodapé da Receita"
                                        value={medicoForm.data.rodape_receita}
                                        onChange={(e) => medicoForm.setData('rodape_receita', e.target.value)}
                                        multiline
                                        rows={2}
                                        placeholder="Texto que aparecerá no rodapé das receitas"
                                    />
                                </div>

                                <div className="border-t pt-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Assinatura</label>
                                    {auth.medico?.assinatura_path && (
                                        <div className="mb-3">
                                            <img
                                                src={`/storage/${auth.medico.assinatura_path}`}
                                                alt="Assinatura atual"
                                                className="h-16 border rounded"
                                            />
                                        </div>
                                    )}
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={handleAssinaturaUpload}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                    />
                                    <p className="text-xs text-gray-500 mt-1">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB</p>
                                </div>
                            </div>

                            <div className="border-t border-gray-200 p-6 bg-gray-50">
                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={savingMedico}
                                        className="px-8 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-lg shadow-blue-600/30"
                                    >
                                        {savingMedico ? 'Salvando...' : 'Salvar Alterações'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    )}

                    {/* Password Tab */}
                    {activeTab === 'password' && (
                        <form onSubmit={handlePasswordUpdate} className="flex flex-col flex-1">
                            <div className="flex-1 p-6 space-y-6">
                                <Input
                                    label="Senha Atual"
                                    type="password"
                                    value={passwordForm.data.current_password}
                                    onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                    error={passwordForm.errors.current_password}
                                    required
                                />

                                <Input
                                    label="Nova Senha"
                                    type="password"
                                    value={passwordForm.data.password}
                                    onChange={(e) => passwordForm.setData('password', e.target.value)}
                                    error={passwordForm.errors.password}
                                    required
                                />

                                <Input
                                    label="Confirmar Nova Senha"
                                    type="password"
                                    value={passwordForm.data.password_confirmation}
                                    onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                    error={passwordForm.errors.password_confirmation}
                                    required
                                />

                                <div className="bg-blue-50 rounded-lg p-4 flex items-start gap-3">
                                    <svg className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div className="text-sm text-blue-800">
                                        <p className="font-medium mb-1">Dicas de segurança:</p>
                                        <ul className="list-disc list-inside space-y-1">
                                            <li>Use no mínimo 8 caracteres</li>
                                            <li>Combine letras, números e símbolos</li>
                                            <li>Não use senhas óbvias ou fáceis de adivinhar</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t border-gray-200 p-6 bg-gray-50">
                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={passwordForm.processing}
                                        className="px-8 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-lg shadow-blue-600/30"
                                    >
                                        {passwordForm.processing ? 'Atualizando...' : 'Atualizar Senha'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    )}
                </div>
            </Drawer>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
        </>
    );
}

