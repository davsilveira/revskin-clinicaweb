import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import ProfileDrawer from '@/Components/ProfileDrawer';

const LG_MEDIA = '(min-width: 1024px)';

export default function DashboardLayout({ children }) {
    const page = usePage();
    const { auth } = page.props;
    const { url } = page;
    const [showUserMenu, setShowUserMenu] = useState(false);
    const [showProfileDrawer, setShowProfileDrawer] = useState(false);

    const [isLg, setIsLg] = useState(() =>
        typeof window !== 'undefined' ? window.matchMedia(LG_MEDIA).matches : true
    );
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    // Sidebar collapsed state with localStorage persistence (default: expanded) — desktop (lg+) only
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem('sidebarCollapsed');
            return stored === null ? false : stored === 'true';
        }
        return false;
    });

    useEffect(() => {
        const mq = window.matchMedia(LG_MEDIA);
        const onChange = () => {
            const next = mq.matches;
            setIsLg(next);
            if (next) setMobileNavOpen(false);
        };
        onChange();
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, []);

    useEffect(() => {
        setMobileNavOpen(false);
    }, [url]);

    useEffect(() => {
        if (!isLg && mobileNavOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
        };
    }, [isLg, mobileNavOpen]);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape' && mobileNavOpen && !isLg) {
                setMobileNavOpen(false);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [mobileNavOpen, isLg]);

    useEffect(() => {
        localStorage.setItem('sidebarCollapsed', sidebarCollapsed);
    }, [sidebarCollapsed]);

    const toggleSidebar = () => {
        setSidebarCollapsed((prev) => !prev);
    };

    const closeMobileNav = useCallback(() => {
        setMobileNavOpen(false);
    }, []);

    const handleLogout = () => {
        router.post('/logout');
    };

    const isActive = (path) => {
        return window.location.pathname === path || window.location.pathname.startsWith(path + '/');
    };

    const isAdmin = auth.user.role === 'admin';
    const isMedico = auth.user.role === 'medico';
    const isCallcenter = auth.user.role === 'callcenter';
    const pendingCallCenterCount = auth.pendingCallCenterCount || 0;
    const tinyEnabled = auth.tinyEnabled || false;

    const getRoleLabel = (role) => {
        const labels = {
            admin: 'Administrador',
            medico: 'Médico',
            callcenter: 'Call Center',
            secretaria: 'Secretária',
        };
        return labels[role] || role;
    };

    const showLabels = !isLg || !sidebarCollapsed;
    const offCanvasClosed = !isLg && !mobileNavOpen;

    const sidebarNavClick = (e) => {
        if (isLg) return;
        const a = e.target.closest('a[href]');
        if (a) setMobileNavOpen(false);
    };

    return (
        <div className="min-h-screen flex bg-gray-50">
            {/* Mobile drawer backdrop */}
            {!isLg && mobileNavOpen && (
                <button
                    type="button"
                    aria-label="Fechar menu"
                    className="fixed inset-0 z-30 bg-black/50 lg:hidden"
                    onClick={closeMobileNav}
                />
            )}

            {/* Sidebar */}
            <aside
                id="dashboard-sidebar"
                className={`
                    bg-white border-r border-gray-200 fixed h-screen flex flex-col transition-all duration-300
                    w-64 max-w-[85vw]
                    z-40 lg:z-20
                    ${sidebarCollapsed ? 'lg:w-16' : 'lg:w-64'}
                    ${offCanvasClosed ? '-translate-x-full' : 'translate-x-0'}
                    lg:translate-x-0
                `}
            >
                {/* Logo */}
                <div
                    className={`h-16 flex items-center border-b border-gray-200 ${
                        showLabels ? 'px-6' : 'px-3 justify-center'
                    }`}
                >
                    <Link href="/dashboard" className="flex items-center gap-3 min-w-0">
                        <div className="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        {showLabels && <span className="text-xl font-bold text-gray-900 truncate">RevSkin</span>}
                    </Link>
                </div>

                {/* Navigation */}
                <nav
                    onClick={sidebarNavClick}
                    className={`flex-1 ${showLabels ? 'px-4' : 'px-2'} py-6 overflow-y-auto`}
                >
                    <div className="space-y-1">
                        {/* Tela Inicial */}
                        <Link
                            href="/dashboard"
                            className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                isActive('/dashboard') && !isActive('/dashboard/')
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                            title={!showLabels ? 'Tela Inicial' : undefined}
                        >
                            <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            {showLabels && <span className="font-medium">Tela Inicial</span>}
                        </Link>

                        {/* Pacientes */}
                        <Link
                            href="/pacientes"
                            className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                isActive('/pacientes')
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                            title={!showLabels ? 'Pacientes' : undefined}
                        >
                            <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            {showLabels && <span className="font-medium">Pacientes</span>}
                        </Link>

                        {/* Receitas (medico and admin) */}
                        {(isAdmin || isMedico) && (
                            <>
                                <Link
                                    href="/receitas"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/receitas')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Receitas' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Receitas</span>}
                                </Link>

                                <Link
                                    href="/assistente-receita"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/assistente-receita')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Assistente Receita' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Assistente Receita</span>}
                                </Link>

                                <Link
                                    href="/relatorios"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/relatorios')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Relatórios' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Relatórios</span>}
                                </Link>

                                {isMedico && (
                                    <Link
                                        href="/catalogo-produtos"
                                        className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                            isActive('/catalogo-produtos')
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'text-gray-700 hover:bg-gray-100'
                                        }`}
                                        title={!showLabels ? 'Produtos' : undefined}
                                    >
                                        <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        {showLabels && <span className="font-medium">Produtos</span>}
                                    </Link>
                                )}
                            </>
                        )}

                        {!tinyEnabled && (isAdmin || isCallcenter) && (
                            <Link
                                href="/callcenter"
                                className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2 relative'} py-3 rounded-lg transition-colors ${
                                    isActive('/callcenter')
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                                title={!showLabels ? 'Call Center' : undefined}
                            >
                                <div className="relative">
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    {!showLabels && isCallcenter && pendingCallCenterCount > 0 && (
                                        <span className="absolute -top-1 -right-1 bg-red-500 text-white rounded-full min-w-[18px] h-4 px-1 flex items-center justify-center text-[10px] font-semibold">
                                            {pendingCallCenterCount > 99 ? '99+' : pendingCallCenterCount}
                                        </span>
                                    )}
                                </div>
                                {showLabels && (
                                    <>
                                        <span className="font-medium">Call Center</span>
                                        {isCallcenter && pendingCallCenterCount > 0 && (
                                            <span className="bg-red-500 text-white rounded-full min-w-[20px] h-5 px-1.5 flex items-center justify-center text-xs font-semibold">
                                                {pendingCallCenterCount > 99 ? '99+' : pendingCallCenterCount}
                                            </span>
                                        )}
                                    </>
                                )}
                            </Link>
                        )}

                        {isAdmin && (
                            <Link
                                href="/produtos"
                                className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                    isActive('/produtos')
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                                title={!showLabels ? 'Produtos' : undefined}
                            >
                                <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                {showLabels && <span className="font-medium">Produtos</span>}
                            </Link>
                        )}

                        {isAdmin && (
                            <>
                                {showLabels && (
                                    <div className="pt-4 pb-2">
                                        <div className="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Administração
                                        </div>
                                    </div>
                                )}
                                {!showLabels && <div className="pt-4 border-t border-gray-200 mt-2" />}

                                <Link
                                    href="/clinicas"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/clinicas')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Clínicas' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Clínicas</span>}
                                </Link>

                                <Link
                                    href="/users"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/users')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Usuários' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Usuários</span>}
                                </Link>

                                <Link
                                    href="/assistente/tabelas-karnaugh"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/assistente/tabelas-karnaugh')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Tabelas Karnaugh' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Tabelas Karnaugh</span>}
                                </Link>

                                <Link
                                    href="/assistente/regras"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/assistente/regras')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Regras Condicionais' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Regras Condicionais</span>}
                                </Link>

                                <Link
                                    href="/exports"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/exports')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Exportar' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Exportar</span>}
                                </Link>

                                {showLabels && (
                                    <div className="pt-4 pb-2">
                                        <div className="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Configurações
                                        </div>
                                    </div>
                                )}
                                {!showLabels && <div className="pt-4 border-t border-gray-200 mt-2" />}

                                <Link
                                    href="/settings"
                                    className={`flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 rounded-lg transition-colors ${
                                        isActive('/settings')
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                    title={!showLabels ? 'Configurações' : undefined}
                                >
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <circle cx="12" cy="12" r="3" strokeWidth={2} />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4V2m0 20v-2m8-8h2M2 12h2m13.657-6.343l1.414-1.414M4.929 19.071l1.414-1.414m0-11.314L4.93 4.93m14.142 14.142l-1.414-1.414" />
                                    </svg>
                                    {showLabels && <span className="font-medium">Configurações</span>}
                                </Link>
                            </>
                        )}
                    </div>
                </nav>

                <div className={`${showLabels ? 'p-4' : 'p-2'} border-t border-gray-200 space-y-2`}>
                    {isLg ? (
                        <button
                            type="button"
                            onClick={toggleSidebar}
                            className={`w-full flex items-center ${showLabels ? 'gap-3 px-4' : 'justify-center px-2'} py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors`}
                            title={sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'}
                        >
                            <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                {sidebarCollapsed ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                                )}
                            </svg>
                            {showLabels && <span className="font-medium">Recolher menu</span>}
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={closeMobileNav}
                            className="w-full flex items-center gap-3 px-4 py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span className="font-medium">Fechar menu</span>
                        </button>
                    )}
                </div>
            </aside>

            {/* Main Content Area */}
            <div
                className={`flex-1 min-w-0 overflow-x-hidden transition-all duration-300 ml-0 ${
                    sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'
                }`}
            >
                <header className="sticky top-0 z-10 bg-white border-b border-gray-200 h-16">
                    <div className="h-full px-5 lg:px-6 flex items-center justify-between gap-2 min-w-0">
                        <div className="flex items-center gap-2 lg:gap-4 min-w-0 flex-1">
                            <button
                                type="button"
                                className="lg:hidden flex-shrink-0 p-2 rounded-lg text-gray-600 hover:bg-gray-100 -ml-1"
                                onClick={() => setMobileNavOpen((o) => !o)}
                                aria-expanded={mobileNavOpen}
                                aria-controls="dashboard-sidebar"
                                aria-label={mobileNavOpen ? 'Fechar menu de navegação' : 'Abrir menu de navegação'}
                            >
                                {mobileNavOpen ? (
                                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                ) : (
                                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                )}
                            </button>
                            <h2 className="text-base lg:text-lg font-semibold text-gray-900 truncate min-w-0">
                                <span className="lg:hidden">Olá, {auth.user.name}</span>
                                <span className="hidden lg:inline">Bem-vindo, {auth.user.name}!</span>
                            </h2>
                        </div>

                        <div className="relative flex-shrink-0">
                            <button
                                type="button"
                                onClick={() => setShowUserMenu(!showUserMenu)}
                                className="flex items-center gap-2 lg:gap-3 px-2 lg:px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors"
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
                                <svg className="w-4 h-4 text-gray-500 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            {showUserMenu && (
                                <>
                                    <div
                                        className="fixed inset-0 z-[60] bg-transparent"
                                        onClick={() => setShowUserMenu(false)}
                                    />

                                    <div className="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-[70]">
                                        <button
                                            type="button"
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
                                            type="button"
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

                <main className="py-4 lg:py-8 px-5 lg:px-8">{children}</main>
            </div>

            <ProfileDrawer isOpen={showProfileDrawer} onClose={() => setShowProfileDrawer(false)} />

            <ToastContainer
                className="max-lg:!top-2 max-lg:!right-auto max-lg:!left-1/2 max-lg:!-translate-x-1/2 max-lg:!w-[calc(100vw-1rem)] max-lg:!max-w-sm"
                toastClassName="max-lg:!mx-auto"
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
