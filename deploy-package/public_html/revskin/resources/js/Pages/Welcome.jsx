import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Welcome() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Login" />

            <div className="min-h-screen flex">
                {/* Lado Esquerdo - Imagem */}
                <div className="hidden lg:flex lg:w-1/2 relative">
                    <img
                        src="/images/login-bg.png"
                        alt="Background"
                        className="absolute inset-0 w-full h-full object-cover"
                    />
                    {/* Overlay opcional para melhorar legibilidade se adicionar texto */}
                    <div className="absolute inset-0 bg-gradient-to-br from-emerald-900/20 to-transparent" />
                </div>

                {/* Lado Direito - Login */}
                <div className="w-full lg:w-1/2 flex items-center justify-center bg-white px-6 py-12">
                    <div className="max-w-md w-full">
                        {/* Logo e Título */}
                        <div className="text-center mb-8">
                            <div className="inline-flex items-center justify-center w-16 h-16 bg-emerald-600 rounded-2xl mb-4 shadow-lg shadow-emerald-600/30">
                                <svg className="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </div>
                            <h1 className="text-3xl font-bold text-gray-900 mb-2">
                                Bem-vindo de volta
                            </h1>
                            <p className="text-gray-600">
                                Faça login para acessar o sistema
                            </p>
                        </div>

                        {/* Card de Login */}
                        <div className="bg-white rounded-xl border border-gray-200 p-8">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* E-mail */}
                                <div>
                                    <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                                        E-mail
                                    </label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                            </svg>
                                        </div>
                                        <input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={e => setData('email', e.target.value)}
                                            className={`w-full pl-10 pr-4 py-3 h-[44px] bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 ${
                                                errors.email ? 'border-red-400 bg-red-50' : 'hover:border-gray-400'
                                            }`}
                                            placeholder="seu@email.com"
                                            autoComplete="email"
                                            autoFocus
                                        />
                                    </div>
                                    {errors.email && <p className="mt-1.5 text-sm text-red-600">{errors.email}</p>}
                                </div>

                                {/* Senha */}
                                <div>
                                    <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                                        Senha
                                    </label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={e => setData('password', e.target.value)}
                                            className={`w-full pl-10 pr-12 py-3 h-[44px] bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 ${
                                                errors.password ? 'border-red-400 bg-red-50' : 'hover:border-gray-400'
                                            }`}
                                            placeholder="••••••••"
                                            autoComplete="current-password"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors"
                                        >
                                            {showPassword ? (
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            ) : (
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            )}
                                        </button>
                                    </div>
                                    {errors.password && <p className="mt-1.5 text-sm text-red-600">{errors.password}</p>}
                                </div>

                                {/* Remember + Forgot */}
                                <div className="flex items-center justify-between">
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <input
                                            id="remember"
                                            type="checkbox"
                                            checked={data.remember}
                                            onChange={e => setData('remember', e.target.checked)}
                                            className="w-5 h-5 text-emerald-600 bg-white border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 transition-all duration-200"
                                        />
                                        <span className="text-sm font-medium text-gray-700">Lembrar-me</span>
                                    </label>
                                    <Link
                                        href="/forgot-password"
                                        className="text-sm font-medium text-emerald-600 hover:text-emerald-700"
                                    >
                                        Esqueceu a senha?
                                    </Link>
                                </div>

                                {/* Botão de Login */}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full px-8 py-3.5 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 flex items-center justify-center shadow-lg shadow-emerald-600/30"
                                >
                                    {processing ? (
                                        <>
                                            <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Entrando...
                                        </>
                                    ) : (
                                        'Entrar'
                                    )}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
