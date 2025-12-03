import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Drawer from '@/Components/Drawer';
import Toast from '@/Components/Toast';
import Input from '@/Components/Form/Input';

export default function ProfileDrawer({ isOpen, onClose }) {
    const { auth } = usePage().props;
    const [activeTab, setActiveTab] = useState('profile');
    const [toast, setToast] = useState(null);

    const profileForm = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

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
                        <div className="flex gap-6">
                            <button
                                onClick={() => setActiveTab('profile')}
                                className={`pb-4 text-sm font-medium transition-colors relative ${
                                    activeTab === 'profile'
                                        ? 'text-blue-600'
                                        : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                <div className="flex items-center gap-2">
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Informações Pessoais
                                </div>
                                {activeTab === 'profile' && (
                                    <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600"></div>
                                )}
                            </button>
                            <button
                                onClick={() => setActiveTab('password')}
                                className={`pb-4 text-sm font-medium transition-colors relative ${
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

