import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/Components/Form/Input';
import Checkbox from '@/Components/Form/Checkbox';
import Toast from '@/Components/Toast';

export default function TinySettings({ settings, onToast }) {
    const [toast, setToast] = useState(null);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [removeToken, setRemoveToken] = useState(false);

    const { data, setData, put, processing, errors, transform } = useForm({
        enabled: settings.enabled || false,
        token: '',
        remove_token: false,
        url_base: settings.url_base || 'https://api.tiny.com.br/api2',
    });

    transform((data) => {
        const transformed = {
            enabled: data.enabled,
            url_base: data.url_base,
        };

        if (data.remove_token) {
            transformed.remove_token = true;
        } else if (data.token && data.token.trim() !== '') {
            transformed.token = data.token;
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
                setData('token', '');
                setData('remove_token', false);
                setRemoveToken(false);
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
            });
            setToast(payload);
            if (onToast) onToast(payload);
        } finally {
            setTesting(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-xl font-bold text-gray-900">Integracao Tiny ERP</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Configure o acesso a API do Tiny ERP para sincronizacao de produtos, clientes e propostas.
                    </p>
                    {settings.updated_at && (
                        <p className="mt-2 text-xs text-gray-500">
                            Ultima atualizacao: {settings.updated_at}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={handleTestConnection}
                        disabled={testing || !settings.has_token}
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
                        <div>
                            <p className={`font-medium ${testResult.success ? 'text-green-800' : 'text-red-800'}`}>
                                {testResult.success ? 'Conexao estabelecida com sucesso!' : 'Falha na conexao'}
                            </p>
                            <p className={`text-sm mt-1 ${testResult.success ? 'text-green-700' : 'text-red-700'}`}>
                                {testResult.message}
                            </p>
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
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Token de acesso (API Key)
                            </label>
                            {settings.has_token && !data.token && !removeToken ? (
                                <div className="space-y-2">
                                    <div className="relative">
                                        <Input
                                            value="********************************"
                                            disabled
                                            className="bg-gray-50 text-gray-500 cursor-not-allowed"
                                        />
                                        <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                            <span className="text-xs text-green-600 bg-green-50 px-2 py-1 rounded border border-green-200">
                                                Configurado
                                            </span>
                                        </div>
                                    </div>
                                    <label className="inline-flex items-center gap-2 cursor-pointer">
                                        <Checkbox
                                            checked={removeToken}
                                            onChange={(event) => {
                                                setRemoveToken(event.target.checked);
                                                setData('remove_token', event.target.checked);
                                            }}
                                        />
                                        <span className="text-xs text-gray-600">
                                            Remover token atual e inserir um novo.
                                        </span>
                                    </label>
                                </div>
                            ) : (
                                <Input
                                    type="password"
                                    value={data.token}
                                    onChange={(event) => {
                                        setData('token', event.target.value);
                                        if (event.target.value) {
                                            setRemoveToken(false);
                                            setData('remove_token', false);
                                        }
                                    }}
                                    placeholder={settings.has_token ? "Digite um novo token para substituir" : "Informe o token fornecido pelo Tiny"}
                                    error={errors.token}
                                />
                            )}
                            <p className="mt-1 text-xs text-gray-500">
                                Obtenha o token em: Tiny ERP {'->'} Configuracoes {'->'} Integradores {'->'} Tokens de API
                            </p>
                        </div>

                        <div>
                            <Input
                                label="URL Base da API"
                                value={data.url_base}
                                onChange={(event) => setData('url_base', event.target.value)}
                                placeholder="https://api.tiny.com.br/api2"
                                error={errors.url_base}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Normalmente nao precisa alterar. Use o padrao: https://api.tiny.com.br/api2
                            </p>
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
                                Sincronizar produtos do Tiny
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Sincronizar clientes/pacientes
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Criar propostas/orcamentos
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Consultar estoque
                            </li>
                        </ul>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3">Como obter o Token</h2>
                        <ol className="space-y-2 text-sm text-gray-600 list-decimal list-inside">
                            <li>Acesse o painel do Tiny ERP</li>
                            <li>Va em Configuracoes {'>'} Integradores</li>
                            <li>Clique em "Tokens de API"</li>
                            <li>Gere um novo token ou copie existente</li>
                            <li>Cole o token no campo acima</li>
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
                                    Mantenha o token seguro. Nao compartilhe com terceiros.
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
