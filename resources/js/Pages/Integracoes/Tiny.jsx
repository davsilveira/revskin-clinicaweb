import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function IntegracoesTiny({ settings, lastSync }) {
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [syncing, setSyncing] = useState(false);

    const { data, setData, put, processing } = useForm({
        tiny_api_token: settings?.tiny_api_token || '',
        tiny_enabled: settings?.tiny_enabled ?? false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put('/integracoes/tiny');
    };

    const testConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const response = await fetch('/integracoes/tiny/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });
            const result = await response.json();
            setTestResult(result);
        } catch (error) {
            setTestResult({ success: false, message: 'Erro ao testar conexão' });
        } finally {
            setTesting(false);
        }
    };

    const syncProdutos = async () => {
        setSyncing(true);
        try {
            const response = await fetch('/integracoes/tiny/sync-produtos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });
            const result = await response.json();
            alert(result.message || 'Sincronização concluída');
        } catch (error) {
            alert('Erro ao sincronizar produtos');
        } finally {
            setSyncing(false);
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-4xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Integração Tiny ERP</h1>
                    <p className="text-gray-600 mt-1">
                        Configure a integração com o Tiny ERP para sincronizar produtos e criar propostas
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Configurações */}
                    <div className="lg:col-span-2">
                        <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Configurações da API</h2>
                            
                            <div className="space-y-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Token da API Tiny
                                    </label>
                                    <input
                                        type="password"
                                        value={data.tiny_api_token}
                                        onChange={(e) => setData('tiny_api_token', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        placeholder="Cole o token da API aqui"
                                    />
                                    <p className="text-xs text-gray-500 mt-1">
                                        Obtenha o token em: Tiny ERP → Configurações → Tokens
                                    </p>
                                </div>

                                <div>
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={data.tiny_enabled}
                                            onChange={(e) => setData('tiny_enabled', e.target.checked)}
                                            className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                        />
                                        <span className="text-sm font-medium text-gray-700">
                                            Habilitar integração
                                        </span>
                                    </label>
                                    <p className="text-xs text-gray-500 mt-1 ml-7">
                                        Quando habilitada, as receitas finalizadas poderão gerar propostas no Tiny
                                    </p>
                                </div>

                                <div className="flex gap-3 pt-4">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                                    >
                                        {processing ? 'Salvando...' : 'Salvar Configurações'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={testConnection}
                                        disabled={testing || !data.tiny_api_token}
                                        className="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                                    >
                                        {testing ? 'Testando...' : 'Testar Conexão'}
                                    </button>
                                </div>

                                {testResult && (
                                    <div className={`p-4 rounded-lg ${
                                        testResult.success 
                                            ? 'bg-green-50 border border-green-200' 
                                            : 'bg-red-50 border border-red-200'
                                    }`}>
                                        <div className="flex items-center gap-2">
                                            {testResult.success ? (
                                                <svg className="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                            ) : (
                                                <svg className="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            )}
                                            <span className={`font-medium ${
                                                testResult.success ? 'text-green-800' : 'text-red-800'
                                            }`}>
                                                {testResult.message}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </form>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Status */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 className="font-semibold text-gray-900 mb-4">Status</h3>
                            
                            <div className="space-y-3">
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-gray-500">Integração</span>
                                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                        settings?.tiny_enabled
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-800'
                                    }`}>
                                        {settings?.tiny_enabled ? 'Ativa' : 'Inativa'}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-gray-500">Token configurado</span>
                                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                        settings?.tiny_api_token
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    }`}>
                                        {settings?.tiny_api_token ? 'Sim' : 'Não'}
                                    </span>
                                </div>
                                {lastSync && (
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-500">Última sinc.</span>
                                        <span className="text-sm text-gray-900">
                                            {new Date(lastSync).toLocaleString('pt-BR')}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Ações */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 className="font-semibold text-gray-900 mb-4">Ações</h3>
                            
                            <div className="space-y-3">
                                <button
                                    onClick={syncProdutos}
                                    disabled={syncing || !settings?.tiny_enabled}
                                    className="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                                >
                                    {syncing ? (
                                        <>
                                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            Sincronizando...
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            Sincronizar Produtos
                                        </>
                                    )}
                                </button>

                                <Link
                                    href="/integracoes/tiny/pedidos"
                                    className="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Ver Pedidos
                                </Link>
                            </div>
                        </div>

                        {/* Help */}
                        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 text-blue-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p className="text-sm font-medium text-blue-900">Precisa de ajuda?</p>
                                    <p className="text-sm text-blue-700 mt-1">
                                        Consulte a documentação da API Tiny em{' '}
                                        <a 
                                            href="https://tiny.com.br/ajuda/api/inicio" 
                                            target="_blank"
                                            className="underline"
                                        >
                                            tiny.com.br/ajuda/api
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}










