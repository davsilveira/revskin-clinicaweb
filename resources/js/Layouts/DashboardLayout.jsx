import { Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import ProfileDrawer from '@/Components/ProfileDrawer';

export default function DashboardLayout({ children }) {
    const { auth } = usePage().props;
    const [showUserMenu, setShowUserMenu] = useState(false);
    const [showProfileDrawer, setShowProfileDrawer] = useState(false);

    const handleLogout = () => {
        router.post('/logout');
    };

    const isActive = (path) => {
        return window.location.pathname === path;
    };

    const isAdmin = auth.user.role === 'admin';

    return (
        <div className="min-h-screen flex bg-gray-50">
            {/* Sidebar */}
            <aside className="w-64 bg-white border-r border-gray-200 fixed h-screen flex flex-col">
                {/* Logo */}
                <div className="h-16 flex items-center px-6 border-b border-gray-200">
                    <Link href="/dashboard" className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <span className="text-xl font-bold text-gray-900">Revskin</span>
                    </Link>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-4 py-6 overflow-y-auto">
                    <div className="space-y-2">
                        {/* Dashboard */}
                        <Link
                            href="/dashboard"
                            className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                                isActive('/dashboard')
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span className="font-medium">Dashboard</span>
                        </Link>

                        {/* Admin only sections */}
                        {isAdmin && (
                            <>
                                {/* Users */}
                                <Link
                                    href="/users"
                                    className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                                        isActive('/users')
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <span className="font-medium">Usuários</span>
                                </Link>

                                {/* Exports */}
                                <Link
                                    href="/exports"
                                    className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                                        isActive('/exports')
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                                    </svg>
                                    <span className="font-medium">Exportar</span>
                                </Link>

                                {/* Tools Section */}
                                <div className="pt-4 pb-2">
                                    <div className="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Ferramentas
                                    </div>
                                </div>

                                <Link
                                    href="/tools/infosimples"
                                    className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                                        isActive('/tools/infosimples')
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <span className="font-medium">Infosimples</span>
                                </Link>

                                {/* Settings Section */}
                                <div className="pt-4 pb-2">
                                    <div className="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Configurações
                                    </div>
                                </div>

                                <Link
                                    href="/settings"
                                    className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                                        isActive('/settings')
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <circle cx="12" cy="12" r="3" strokeWidth={2} />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4V2m0 20v-2m8-8h2M2 12h2m13.657-6.343l1.414-1.414M4.929 19.071l1.414-1.414m0-11.314L4.93 4.93m14.142 14.142l-1.414-1.414" />
                                    </svg>
                                    <span className="font-medium">Configurações</span>
                                </Link>
                            </>
                        )}
                    </div>
                </nav>

                {/* Footer do Sidebar */}
                <div className="p-4 border-t border-gray-200">
                    <a
                        href="/"
                        className="flex items-center gap-3 px-4 py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span className="font-medium">Voltar ao site</span>
                    </a>
                </div>
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 ml-64">
                {/* Topbar */}
                <header className="sticky top-0 z-10 bg-white border-b border-gray-200 h-16">
                    <div className="h-full px-6 flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <h2 className="text-lg font-semibold text-gray-900">
                                Bem-vindo, {auth.user.name}!
                            </h2>
                        </div>

                        {/* User Menu */}
                        <div className="relative">
                            <button
                                onClick={() => setShowUserMenu(!showUserMenu)}
                                className="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors"
                            >
                                <div className="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                    <span className="text-sm font-medium text-white">
                                        {auth.user.name.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div className="text-left hidden md:block">
                                    <div className="text-sm font-medium text-gray-900">{auth.user.name}</div>
                                    <div className="text-xs text-gray-500 capitalize">{auth.user.role}</div>
                                </div>
                                <svg className="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            {showUserMenu && (
                                <>
                                    <div
                                        className="fixed inset-0 z-10"
                                        onClick={() => setShowUserMenu(false)}
                                    />

                                    <div className="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-20">
                                        <button
                                            onClick={() => {
                                                setShowUserMenu(false);
                                                setShowProfileDrawer(true);
                                            }}
                                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Meu Perfil
                                        </button>

                                        <div className="border-t border-gray-200 my-2" />

                                        <button
                                            onClick={handleLogout}
                                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                        >
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                            Sair
                                        </button>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                {/* Content */}
                <main className="p-8">
                    {children}
                </main>
            </div>

            {/* Profile Drawer */}
            <ProfileDrawer 
                isOpen={showProfileDrawer} 
                onClose={() => setShowProfileDrawer(false)} 
            />

            {/* Toast Container */}
            <ToastContainer
                position="top-right"
                autoClose={3000}
                hideProgressBar={false}
                newestOnTop={false}
                closeOnClick
                rtl={false}
                pauseOnFocusLoss
                draggable
                pauseOnHover
                theme="light"
            />
        </div>
    );
}

