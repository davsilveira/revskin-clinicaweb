import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import ProfileDrawer from '@/Components/ProfileDrawer';

export default function DashboardLayout({ children }) {
    const { auth } = usePage().props;
    const [showUserMenu, setShowUserMenu] = useState(false);
    const [showProfileDrawer, setShowProfileDrawer] = useState(false);
    const [expandedMenus, setExpandedMenus] = useState({});
    
    // Sidebar collapsed state with localStorage persistence
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('sidebarCollapsed') === 'true';
        }
        return false;
    });

    useEffect(() => {
        localStorage.setItem('sidebarCollapsed', sidebarCollapsed);
    }, [sidebarCollapsed]);

    const toggleSidebar = () => {
        setSidebarCollapsed(prev => !prev);
    };

    const handleLogout = () => {
        router.post('/logout');
    };

    const isActive = (path) => {
        return window.location.pathname === path || window.location.pathname.startsWith(path + '/');
    };

    const toggleMenu = (menu) => {
        setExpandedMenus(prev => ({ ...prev, [menu]: !prev[menu] }));
    };

    const isAdmin = auth.user.role === 'admin';
    const isMedico = auth.user.role === 'medico';
    const isCallcenter = auth.user.role === 'callcenter';

    const getRoleLabel = (role) => {
        const labels = {
            admin: 'Administrador',
            medico: 'Médico',
            callcenter: 'Call Center',
        };
        return labels[role] || role;
    };

    return (
        <div className="min-h-screen flex bg-gray-50">
            {/* Sidebar */}
            <aside className={`${sidebarCollapsed ? 'w-16' : 'w-64'} bg-white border-r border-gray-200 fixed h-screen flex flex-col z-20 transition-all duration-300`}>
                {/* Logo */}
                <div className={`h-16 flex items-center ${sidebarCollapsed ? 'px-3 justify-center' : 'px-6'} border-b border-gray-200`}>
                    <Link href="/dashboard" className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        {!sidebarCollapsed && <span className="text-xl font-bold text-gray-900">RevSkin</span>}
                    </Link>
                </div>

                {/* Navigation */}
                <nav className={`flex-1 ${sidebarCollapsed ? 'px-2' : 'px-4'} py-6 overflow-y-auto`}>
                    <div className="space-y-1">
                        {/* Dashboard */}
                        <Link
                            href="/dashboard"
                            className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                isActive('/dashboard') && !isActive('/dashboard/')
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                            title={sidebarCollapsed ? 'Dashboard' : undefined}
                        >
                            <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            {!sidebarCollapsed && <span className="font-medium">Dashboard</span>}
                        </Link>

                        {/* Pacientes */}
                        <Link
                            href="/pacientes"
                            className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                isActive('/pacientes')
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                            title={sidebarCollapsed ? 'Pacientes' : undefined}
                        >
                            <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            {!sidebarCollapsed && <span className="font-medium">Pacientes</span>}
                        </Link>

                        {/* Receitas (medico and admin) */}
                        {(isAdmin || isMedico) && (
                            <>
                                <Link
                                    href="/receitas"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/receitas')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Receitas' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Receitas</span>}
                                </Link>

                                <Link
                                    href="/assistente-receita"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/assistente-receita')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Assistente Receita' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Assistente Receita</span>}
                                </Link>
                            </>
                        )}

                        {/* Call Center (callcenter and admin) */}
                        {(isAdmin || isCallcenter) && (
                            <Link
                                href="/callcenter"
                                className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                    isActive('/callcenter')
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                                title={sidebarCollapsed ? 'Call Center' : undefined}
                            >
                                <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                {!sidebarCollapsed && <span className="font-medium">Call Center</span>}
                            </Link>
                        )}

                        {/* Cadastros Section - Admin and Call Center */}
                        {(isAdmin || isCallcenter) && (
                            <>
                                {!sidebarCollapsed && (
                                    <div className="pt-4 pb-2">
                                        <div className="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Cadastros
                                        </div>
                                    </div>
                                )}
                                {sidebarCollapsed && <div className="pt-4 border-t border-gray-200 mt-2" />}

                                <Link
                                    href="/medicos"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/medicos')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Médicos' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Médicos</span>}
                                </Link>

                                <Link
                                    href="/clinicas"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/clinicas')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Clínicas' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Clínicas</span>}
                                </Link>

                                <Link
                                    href="/produtos"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/produtos')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Produtos' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Produtos</span>}
                                </Link>
                            </>
                        )}

                        {/* Administração Section - Admin only */}
                        {isAdmin && (
                            <>
                                {!sidebarCollapsed && (
                                    <div className="pt-4 pb-2">
                                        <div className="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Administração
                                        </div>
                                    </div>
                                )}
                                {sidebarCollapsed && <div className="pt-4 border-t border-gray-200 mt-2" />}

                                <Link
                                    href="/users"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/users')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Usuários' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Usuários</span>}
                                </Link>

                                <Link
                                    href="/assistente/tabelas-karnaugh"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/assistente/tabelas-karnaugh')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Tabelas Karnaugh' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Tabelas Karnaugh</span>}
                                </Link>

                                <Link
                                    href="/assistente/regras"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/assistente/regras')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Regras Condicionais' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Regras Condicionais</span>}
                                </Link>

                                <Link
                                    href="/relatorios"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/relatorios')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Relatórios' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Relatórios</span>}
                                </Link>

                                <Link
                                    href="/exports"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/exports')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Exportar' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Exportar</span>}
                                </Link>

                                {/* Configurações Section - Admin only */}
                                {!sidebarCollapsed && (
                                    <div className="pt-4 pb-2">
                                        <div className="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Configurações
                                        </div>
                                    </div>
                                )}
                                {sidebarCollapsed && <div className="pt-4 border-t border-gray-200 mt-2" />}

                                <Link
                                    href="/settings"
                                    className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 rounded-lg transition-colors ${
                                        isActive('/settings')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={sidebarCollapsed ? 'Configurações' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <circle cx="12" cy="12" r="3" strokeWidth={2} />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4V2m0 20v-2m8-8h2M2 12h2m13.657-6.343l1.414-1.414M4.929 19.071l1.414-1.414m0-11.314L4.93 4.93m14.142 14.142l-1.414-1.414" />
                                    </svg>
                                    {!sidebarCollapsed && <span className="font-medium">Configurações</span>}
                                </Link>
                            </>
                        )}
                    </div>
                </nav>

                {/* Footer do Sidebar */}
                <div className={`${sidebarCollapsed ? 'p-2' : 'p-4'} border-t border-gray-200 space-y-2`}>
                    <a
                        href="/"
                        className={`flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors`}
                        title={sidebarCollapsed ? 'Voltar ao site' : undefined}
                    >
                        <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        {!sidebarCollapsed && <span className="font-medium">Voltar ao site</span>}
                    </a>
                    
                    {/* Toggle Sidebar Button */}
                    <button
                        onClick={toggleSidebar}
                        className={`w-full flex items-center ${sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-4'} py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors`}
                        title={sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'}
                    >
                        <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            {sidebarCollapsed ? (
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            ) : (
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                            )}
                        </svg>
                        {!sidebarCollapsed && <span className="font-medium">Recolher menu</span>}
                    </button>
                </div>
            </aside>

            {/* Main Content Area */}
            <div className={`flex-1 ${sidebarCollapsed ? 'ml-16' : 'ml-64'} overflow-x-hidden transition-all duration-300`}>
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
                                <div className="w-8 h-8 bg-emerald-600 rounded-full flex items-center justify-center">
                                    <span className="text-sm font-medium text-white">
                                        {auth.user.name.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div className="text-left hidden md:block">
                                    <div className="text-sm font-medium text-gray-900">{auth.user.name}</div>
                                    <div className="text-xs text-gray-500">{getRoleLabel(auth.user.role)}</div>
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
