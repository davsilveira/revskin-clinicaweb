import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Bem-vindo" />

            <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
                {/* Header */}
                <header className="fixed top-0 w-full bg-white/80 backdrop-blur-md border-b border-gray-200/50 z-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center h-16">
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                                    <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <span className="text-xl font-bold text-gray-900">Boilerplate</span>
                            </div>
                            <Link
                                href="/login"
                                className="px-6 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-lg shadow-blue-600/30"
                            >
                                Entrar
                            </Link>
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="pt-32 pb-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h1 className="text-5xl md:text-6xl font-bold text-gray-900 mb-6">
                                Laravel + React
                                <span className="text-blue-600"> Boilerplate</span>
                            </h1>
                            <p className="text-xl text-gray-600 max-w-2xl mx-auto mb-10">
                                Um ponto de partida completo para seus projetos. 
                                Autenticação, painel admin, gestão de usuários e muito mais.
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Link
                                    href="/login"
                                    className="px-8 py-4 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-all shadow-xl shadow-blue-600/30 flex items-center gap-2"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                    </svg>
                                    Acessar Sistema
                                </Link>
                            </div>
                        </div>

                        {/* Features */}
                        <div className="mt-24 grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div className="bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                                <div className="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                                    <svg className="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-3">Autenticação Completa</h3>
                                <p className="text-gray-600">
                                    Login, logout, recuperação de senha e gestão de perfil prontos para uso.
                                </p>
                            </div>

                            <div className="bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                                <div className="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-6">
                                    <svg className="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-3">Gestão de Usuários</h3>
                                <p className="text-gray-600">
                                    CRUD completo de usuários com níveis de acesso e controle de permissões.
                                </p>
                            </div>

                            <div className="bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                                <div className="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-6">
                                    <svg className="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-3">Painel Administrativo</h3>
                                <p className="text-gray-600">
                                    Interface moderna com sidebar, componentes reutilizáveis e design responsivo.
                                </p>
                            </div>
                        </div>

                        {/* Tech Stack */}
                        <div className="mt-24 text-center">
                            <h2 className="text-2xl font-bold text-gray-900 mb-8">Stack Tecnológica</h2>
                            <div className="flex flex-wrap justify-center gap-4">
                                {['Laravel 12', 'React 19', 'Inertia.js', 'Tailwind CSS 4', 'Vite'].map((tech) => (
                                    <span
                                        key={tech}
                                        className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium"
                                    >
                                        {tech}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t border-gray-200 py-8">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <p className="text-center text-gray-500 text-sm">
                            © {new Date().getFullYear()} Boilerplate. Todos os direitos reservados.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}

