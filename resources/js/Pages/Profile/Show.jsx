import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function ProfileShow() {
    const { auth } = usePage().props;

    return (
        <DashboardLayout>
            <Head title="Meu Perfil" />

            <div className="max-w-3xl mx-auto">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">
                        Meu Perfil
                    </h1>
                    <p className="text-gray-600">
                        Gerencie suas informações pessoais
                    </p>
                </div>

                {/* Profile Card */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    {/* Header */}
                    <div className="bg-gradient-to-br from-blue-500 to-blue-600 px-8 py-12">
                        <div className="flex items-center gap-6">
                            <div className="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg">
                                <span className="text-3xl font-bold text-blue-600">
                                    {auth.user.name.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div className="text-white">
                                <h2 className="text-2xl font-bold">{auth.user.name}</h2>
                                <p className="text-blue-100">{auth.user.email}</p>
                            </div>
                        </div>
                    </div>

                    {/* Info */}
                    <div className="p-8">
                        <div className="space-y-6">
                            <div className="flex items-center justify-between py-4 border-b border-gray-100">
                                <div>
                                    <p className="text-sm text-gray-500">Nome</p>
                                    <p className="font-medium text-gray-900">{auth.user.name}</p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between py-4 border-b border-gray-100">
                                <div>
                                    <p className="text-sm text-gray-500">E-mail</p>
                                    <p className="font-medium text-gray-900">{auth.user.email}</p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between py-4 border-b border-gray-100">
                                <div>
                                    <p className="text-sm text-gray-500">Perfil</p>
                                    <p className="font-medium text-gray-900 capitalize">{auth.user.role}</p>
                                </div>
                                <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                                    auth.user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'
                                }`}>
                                    {auth.user.role === 'admin' ? 'Administrador' : 'Usuário'}
                                </span>
                            </div>

                            <div className="flex items-center justify-between py-4">
                                <div>
                                    <p className="text-sm text-gray-500">Status</p>
                                    <p className="font-medium text-gray-900">Ativo</p>
                                </div>
                                <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <span className="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    Ativo
                                </span>
                            </div>
                        </div>

                        {/* Info Box */}
                        <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
                            <svg className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div className="text-sm text-blue-800">
                                <p className="font-medium mb-1">Editar perfil</p>
                                <p>
                                    Para editar suas informações ou alterar sua senha, clique no seu nome
                                    no menu superior e selecione "Meu Perfil".
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

