import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/Components/Form/Input';
import Checkbox from '@/Components/Form/Checkbox';
import Toast from '@/Components/Toast';

export default function TinySettings({ settings, onToast, isAuthenticated }) {
    const [toast, setToast] = useState(null);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [showClientSecret, setShowClientSecret] = useState(false);
    const [removeClientSecret, setRemoveClientSecret] = useState(false);
    const [authorizing, setAuthorizing] = useState(false);

    const { data, setData, put, processing, errors, transform } = useForm({
        enabled: settings.enabled || false,
        client_id: '',
        client_secret: '',
        remove_client_secret: false,
        url_base: settings.url_base || 'https://api.tiny.com.br/public-api/v3',
    });

    transform((data) => {
        const transformed = {
            enabled: data.enabled,
            url_base: data.url_base,
        };

        if (data.client_id && data.client_id.trim() !== '') {
            transformed.client_id = data.client_id;
        }

        if (data.remove_client_secret) {
            transformed.remove_client_secret = true;
        } else if (data.client_secret && data.client_secret.trim() !== '') {
            transformed.client_secret = data.client_secret;
        }

        return transformed;
    });

    const handleSubmit = (event) => {
        event.preventDefault();

        put('/settings/integrations/tiny', {
            preserveScroll: true,
            onSuccess: () => {
                const payload = { message: 'Configuracoes salvas com sucesso!', type: 'success' };
                setToast(payload);
                if (onToast) onToast(payload);
                setData('client_id', '');
                setData('client_secret', '');
                setData('remove_client_secret', false);
                setRemoveClientSecret(false);
            },
            onError: () => {
                const payload = { message: 'Erro ao salvar configuracoes.', type: 'error' };
                setToast(payload);
                if (onToast) onToast(payload);
            },
        });
    };

    const handleTestConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const response = await window.axios.post('/settings/integrations/tiny/test');
            const isSuccess = response.data?.success;
            const payload = {
                message: response.data?.message ?? 'Teste de conexao concluido.',
                type: isSuccess ? 'success' : 'warning',
            };
            setTestResult({
                success: isSuccess,
                message: response.data?.message,
                data: response.data?.data,
                requiresAuth: response.data?.requires_auth,
            });
            setToast(payload);
            if (onToast) onToast(payload);
        } catch (error) {
            const payload = {
                message: error.response?.data?.message ?? 'Falha ao testar conexao com Tiny ERP.',
                type: 'error',
            };
            setTestResult({
                success: false,
                message: payload.message,
                requiresAuth: error.response?.data?.requires_auth,
            });
            setToast(payload);
            if (onToast) onToast(payload);
        } finally {
            setTesting(false);
        }
    };

    const handleAuthorize = async () => {
        setAuthorizing(true);
        try {
            const response = await window.axios.get('/integracoes/tiny/auth-url');
            if (response.data?.success && response.data?.auth_url) {
                // Redirecionar para página de autorização do Tiny
                window.location.href = response.data.auth_url;
            } else {
                const payload = {
                    message: 'Erro ao gerar URL de autorização.',
                    type: 'error',
                };
                setToast(payload);
                if (onToast) onToast(payload);
                setAuthorizing(false);
            }
        } catch (error) {
            const payload = {
                message: error.response?.data?.message ?? 'Erro ao iniciar autorização.',
                type: 'error',
            };
            setToast(payload);
            if (onToast) onToast(payload);
            setAuthorizing(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-xl font-bold text-gray-900">Integracao Tiny ERP</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Configure o acesso a API v3 do Tiny ERP para sincronizacao de produtos, clientes e vendas.
                    </p>
                    {settings.last_sync && (
                        <p className="mt-2 text-xs text-gray-500">
                            Ultima sincronizacao de produtos: {new Date(settings.last_sync).toLocaleString('pt-BR')}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    {!isAuthenticated && settings.has_client_id && settings.has_client_secret && (
                        <button
                            type="button"
                            onClick={handleAuthorize}
                            disabled={authorizing}
                            className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {authorizing ? (
                                <>
                                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    Redirecionando...
                                </>
                            ) : (
                                <>
                                    <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Autorizar Aplicativo
                                </>
                            )}
                        </button>
                    )}
                    {isAuthenticated && (
                        <span className="inline-flex items-center px-3 py-1 text-sm font-medium rounded-lg bg-green-100 text-green-800">
                            <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Autenticado
                        </span>
                    )}
                    <button
                        type="button"
                        onClick={handleTestConnection}
                        disabled={testing || !settings.has_client_id || !settings.has_client_secret}
                        className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-emerald-600 text-emerald-600 hover:bg-emerald-50 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {testing ? (
                            <>
                                <svg className="animate-spin -ml-1 mr-2 h-4 w-4" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Testando...
                            </>
                        ) : (
                            'Testar conexao'
                        )}
                    </button>
                </div>
            </div>

            {testResult && (
                <div className={`p-4 rounded-lg ${testResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
                    <div className="flex items-start gap-3">
                        {testResult.success ? (
                            <svg className="w-5 h-5 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        ) : (
                            <svg className="w-5 h-5 text-red-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        )}
                        <div className="flex-1">
                            <p className={`font-medium ${testResult.success ? 'text-green-800' : 'text-red-800'}`}>
                                {testResult.success ? 'Conexao estabelecida com sucesso!' : 'Falha na conexao'}
                            </p>
                            <p className={`text-sm mt-1 ${testResult.success ? 'text-green-700' : 'text-red-700'}`}>
                                {testResult.message}
                            </p>
                            {testResult.requiresAuth && !isAuthenticated && (
                                <div className="mt-3">
                                    <button
                                        type="button"
                                        onClick={handleAuthorize}
                                        className="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                                    >
                                        <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        Autorizar Aplicativo Agora
                                    </button>
                                </div>
                            )}
                            {testResult.data && (
                                <pre className="mt-2 text-xs bg-white/50 p-2 rounded overflow-auto max-h-32">
                                    {JSON.stringify(testResult.data, null, 2)}
                                </pre>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2">
                    <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                        <div>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <Checkbox
                                    checked={data.enabled}
                                    onChange={(event) => setData('enabled', event.target.checked)}
                                />
                                <span className="text-sm font-medium text-gray-700">Ativar integracao</span>
                            </label>
                            <p className="mt-1 text-xs text-gray-500">
                                Quando desativada, nenhuma sincronizacao sera feita com o Tiny ERP.
                            </p>
                            {errors.enabled && <p className="mt-1 text-sm text-red-600">{errors.enabled}</p>}
                        </div>

                        <div>
                            <Input
                                label="Client ID"
                                value={data.client_id}
                                onChange={(event) => setData('client_id', event.target.value)}
                                placeholder={settings.has_client_id ? "Digite um novo Client ID para substituir" : "Informe o Client ID do app criado no Tiny"}
                                error={errors.client_id}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Client ID do aplicativo criado no painel do Tiny ERP.
                            </p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Client Secret
                            </label>
                            {settings.has_client_secret && !data.client_secret && !removeClientSecret ? (
                                <div className="space-y-2">
                                    <div className="relative">
                                        <Input
                                            type={showClientSecret ? 'text' : 'password'}
                                            value="********************************"
                                            disabled
                                            className="bg-gray-50 text-gray-500 cursor-not-allowed"
                                        />
                                        <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => setShowClientSecret(!showClientSecret)}
                                                className="text-xs text-gray-600 hover:text-gray-800"
                                            >
                                                {showClientSecret ? 'Ocultar' : 'Exibir'}
                                            </button>
                                            <span className="text-xs text-green-600 bg-green-50 px-2 py-1 rounded border border-green-200">
                                                Configurado
                                            </span>
                                        </div>
                                    </div>
                                    <label className="inline-flex items-center gap-2 cursor-pointer">
                                        <Checkbox
                                            checked={removeClientSecret}
                                            onChange={(event) => {
                                                setRemoveClientSecret(event.target.checked);
                                                setData('remove_client_secret', event.target.checked);
                                            }}
                                        />
                                        <span className="text-xs text-gray-600">
                                            Remover Client Secret atual e inserir um novo.
                                        </span>
                                    </label>
                                </div>
                            ) : (
                                <Input
                                    type="password"
                                    value={data.client_secret}
                                    onChange={(event) => {
                                        setData('client_secret', event.target.value);
                                        if (event.target.value) {
                                            setRemoveClientSecret(false);
                                            setData('remove_client_secret', false);
                                        }
                                    }}
                                    placeholder={settings.has_client_secret ? "Digite um novo Client Secret para substituir" : "Informe o Client Secret do app criado no Tiny"}
                                    error={errors.client_secret}
                                />
                            )}
                            <p className="mt-1 text-xs text-gray-500">
                                Client Secret do aplicativo criado no painel do Tiny ERP. Mantenha seguro e nao compartilhe.
                            </p>
                        </div>

                        <div>
                            <Input
                                label="URL Base da API"
                                value={data.url_base}
                                onChange={(event) => setData('url_base', event.target.value)}
                                placeholder="https://api.tiny.com.br/public-api/v3"
                                error={errors.url_base}
                                disabled
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                URL base da API v3 do Tiny ERP. Normalmente nao precisa alterar.
                            </p>
                        </div>

                        <div className="bg-blue-50 rounded-lg border border-blue-200 p-4">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 text-blue-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p className="font-medium text-blue-800">URL de Redirecionamento</p>
                                    <p className="text-sm text-blue-700 mt-1">
                                        Certifique-se de que esta URL esta configurada no app "ClinicaWeb" no painel do Tiny ERP:
                                    </p>
                                    <code className="mt-2 block text-xs bg-white p-2 rounded border border-blue-300 break-all">
                                        {window.location.origin}/integracoes/tiny/callback
                                    </code>
                                    <p className="text-xs text-blue-600 mt-2">
                                        A URL deve corresponder exatamente ao configurado no Tiny ERP.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-blue-50 rounded-lg border border-blue-200 p-4">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 text-blue-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p className="font-medium text-blue-800">URL do Webhook</p>
                                    <p className="text-sm text-blue-700 mt-1">
                                        Configure esta URL no app "ClinicaWeb" no painel do Tiny ERP:
                                    </p>
                                    <code className="mt-2 block text-xs bg-white p-2 rounded border border-blue-300 break-all">
                                        {window.location.origin}/api/webhooks/tiny/pedido-finalizado
                                    </code>
                                    <p className="text-xs text-blue-600 mt-2">
                                        Nota: Webhooks so funcionam em producao, nao em localhost.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Salvando...' : 'Salvar configuracoes'}
                            </button>
                        </div>
                    </form>
                </div>

                <div className="space-y-6">
                    <div className="bg-white rounded-xl shadow-sm border border-emerald-200 p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 bg-emerald-600 text-white rounded-full flex items-center justify-center">
                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">Funcionalidades</h2>
                                <p className="text-sm text-gray-500">O que a integracao permite</p>
                            </div>
                        </div>
                        <ul className="space-y-3 text-sm text-gray-600">
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Sincronizar produtos do Tiny (2x por dia)
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Sincronizar clientes/pacientes automaticamente
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Criar pedidos quando atendimento vai para producao
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Receber notificacoes quando pedido e finalizado no Tiny
                            </li>
                        </ul>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3">Como obter as credenciais</h2>
                        <ol className="space-y-2 text-sm text-gray-600 list-decimal list-inside">
                            <li>Acesse o painel do Tiny ERP</li>
                            <li>Va em Configuracoes {'>'} E-commerce {'>'} Integracoes</li>
                            <li>Procure por "API do ERP" ou crie um novo app</li>
                            <li>Copie o Client ID e Client Secret gerados</li>
                            <li>Cole nas configuracoes acima</li>
                        </ol>
                        <a
                            href="https://tiny.com.br/login"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-4 inline-flex items-center text-sm text-emerald-600 hover:text-emerald-700"
                        >
                            Acessar Tiny ERP
                            <svg className="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>

                    <div className="bg-amber-50 rounded-xl border border-amber-200 p-4">
                        <div className="flex items-start gap-3">
                            <svg className="w-5 h-5 text-amber-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p className="font-medium text-amber-800">Importante</p>
                                <p className="text-sm text-amber-700 mt-1">
                                    Mantenha o Client Secret seguro. Nao compartilhe com terceiros. O Client Secret e criptografado no banco de dados.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
        </div>
    );
}
