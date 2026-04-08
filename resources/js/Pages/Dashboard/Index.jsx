import { Head, usePage, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function Dashboard() {
    const { auth } = usePage().props;
    const isAdmin = auth.user.role === 'admin';

    const baseActions = [
        {
            href: '/pacientes',
            label: 'Pacientes',
            iconPath: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        },
        {
            href: '/manual',
            label: 'Manual',
            iconPath:
                'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
        },
    ];

    const adminActions = [
        {
            href: '/users',
            label: 'Gerenciar Usuários',
            iconPath: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        },
        {
            href: '/exports',
            label: 'Exportar Dados',
            iconPath: 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4',
        },
        {
            href: '/settings',
            label: 'Configurações',
            iconPath: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        },
    ];

    const siteAction = {
        href: '/logout',
        label: 'Sair',
        iconPath: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
        isLogout: true,
    };

    const actions = isAdmin
        ? [...baseActions, ...adminActions, siteAction]
        : [...baseActions, siteAction];

    return (
        <DashboardLayout>
            <Head title="Tela Inicial" />

            <div className="space-y-6">
                {/* Welcome Banner */}
                <div className="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg p-8 text-white">
                    <h1 className="text-3xl font-bold mb-2">
                        Olá, {auth.user.name}!
                    </h1>
                </div>

                {/* Quick Actions */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 className="text-xl font-bold text-gray-900 mb-6">Ações Rápidas</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {actions.map((action) => 
                            action.isLogout ? (
                                <button
                                    key={action.href}
                                    type="button"
                                    onClick={() => router.post('/logout')}
                                    className="w-full flex items-center gap-4 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition-all text-left"
                                >
                                    <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-100">
                                        <svg className="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={action.iconPath} />
                                        </svg>
                                    </div>
                                    <div className="font-semibold text-gray-900">{action.label}</div>
                                </button>
                            ) : (
                                <a
                                    key={action.href}
                                    href={action.href}
                                    className="flex items-center gap-4 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition-all"
                                >
                                    <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-100">
                                        <svg className="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={action.iconPath} />
                                        </svg>
                                    </div>
                                    <div className="font-semibold text-gray-900">{action.label}</div>
                                </a>
                            )
                        )}
                    </div>
                </div>

                {/* System Info */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 className="text-xl font-bold text-gray-900 mb-4">Informações do Sistema</h2>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between py-2 border-b border-gray-100">
                            <span className="text-gray-600">Perfil</span>
                            <span className="font-medium text-gray-900 capitalize">{auth.user.role}</span>
                        </div>
                        <div className="flex items-center justify-between py-2 border-b border-gray-100">
                            <span className="text-gray-600">E-mail</span>
                            <span className="font-medium text-gray-900">{auth.user.email}</span>
                        </div>
                        <div className="flex items-center justify-between py-2">
                            <span className="text-gray-600">Status da Conta</span>
                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <span className="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                Ativo
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
