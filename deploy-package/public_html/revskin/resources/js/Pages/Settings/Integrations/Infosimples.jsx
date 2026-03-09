import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Select from '@/Components/Form/Select';
import Input from '@/Components/Form/Input';
import Checkbox from '@/Components/Form/Checkbox';
import Toast from '@/Components/Toast';

export default function InfosimplesSettings({ settings, cache_ttl_options, default_timeout_options, onToast }) {
    const [toast, setToast] = useState(null);
    const [testing, setTesting] = useState(false);
    const [removeToken, setRemoveToken] = useState(false);

    const { data, setData, put, processing, errors, transform } = useForm({
        enabled: settings.enabled,
        token: '',
        remove_token: false,
        cache_months: settings.cache_months,
        timeout: settings.timeout,
    });

    // Transform data before sending: only include token if there's a new one or if marked for removal
    transform((data) => {
        const transformed = {
            enabled: data.enabled,
            cache_months: data.cache_months,
            timeout: data.timeout,
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

        put('/settings/integrations/infosimples', {
            preserveScroll: true,
            onSuccess: () => {
                const payload = { message: 'Configurações atualizadas com sucesso!', type: 'success' };
                setToast(payload);
                if (onToast) onToast(payload);
                // Clear token field after saving
                setData('token', '');
                setData('remove_token', false);
                setRemoveToken(false);
            },
            onError: () => {
                const payload = { message: 'Erro ao salvar configurações.', type: 'error' };
                setToast(payload);
                if (onToast) onToast(payload);
            },
        });
    };

    const handleTestConnection = async () => {
        setTesting(true);
        try {
            const response = await window.axios.post('/settings/integrations/infosimples/test');
            const payload = {
                message: response.data?.message ?? 'Teste de conexão concluído.',
                type: response.data?.success ? 'success' : 'warning',
            };
            setToast(payload);
            if (onToast) onToast(payload);
        } catch (error) {
            const payload = {
                message: error.response?.data?.message ?? 'Falha ao testar conexão com Infosimples.',
                type: 'error',
            };
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
                    <h2 className="text-xl font-bold text-gray-900">Configurações Infosimples</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Configure o acesso à API da Infosimples e o comportamento do cache local.
                    </p>
                    {settings.updated_at && (
                        <p className="mt-2 text-xs text-gray-500">
                            Última atualização: {settings.updated_at}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={handleTestConnection}
                        disabled={testing}
                        className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-blue-600 text-blue-600 hover:bg-blue-50 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {testing ? 'Testando conexão...' : 'Testar conexão'}
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2">
                    <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                        <div>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <Checkbox
                                    checked={data.enabled}
                                    onChange={(event) => setData('enabled', event.target.checked)}
                                />
                                <span className="text-sm font-medium text-gray-700">Ativar integração</span>
                            </label>
                            <p className="mt-1 text-xs text-gray-500">
                                Quando desativada, nenhuma validação será enviada para a Infosimples.
                            </p>
                            {errors.enabled && <p className="mt-1 text-sm text-red-600">{errors.enabled}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Token de acesso
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
                                            <span className="text-xs text-gray-500 bg-white px-2 py-1 rounded border border-gray-200">
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
                                    placeholder={settings.has_token ? "Digite um novo token para substituir" : "Informe o token fornecido pela Infosimples"}
                                    error={errors.token}
                                />
                            )}
                            <p className="mt-1 text-xs text-gray-500">
                                O mesmo token é utilizado para todos os serviços (CPF, CNPJ e CRO).
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Select
                                label="Duração do cache"
                                value={data.cache_months}
                                onChange={(event) => setData('cache_months', Number(event.target.value))}
                                error={errors.cache_months}
                            >
                                {cache_ttl_options.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>

                            <Select
                                label="Timeout padrão"
                                value={data.timeout}
                                onChange={(event) => setData('timeout', Number(event.target.value))}
                                error={errors.timeout}
                            >
                                {default_timeout_options.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Salvando...' : 'Salvar configurações'}
                            </button>
                        </div>
                    </form>
                </div>

                <div className="space-y-6">
                    <div className="bg-white rounded-xl shadow-sm border border-blue-200 p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center">
                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m2-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">Como funciona</h2>
                                <p className="text-sm text-gray-500">Resumo do fluxo de validação</p>
                            </div>
                        </div>
                        <ul className="space-y-3 text-sm text-gray-600">
                            <li>• Consultas são enviadas para a API Infosimples.</li>
                            <li>• O retorno é armazenado em cache pelo período configurado.</li>
                            <li>• Evita consultas duplicadas e reduz custos.</li>
                            <li>• Códigos de erro ficam registrados para auditoria.</li>
                        </ul>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3">Checklist</h2>
                        <ul className="space-y-2 text-sm text-gray-600">
                            <li>• Garanta que o token está ativo na Infosimples.</li>
                            <li>• Ajuste o cache para equilibrar custo X recorrência.</li>
                            <li>• Configure o timeout conforme a estabilidade do serviço.</li>
                            <li>• Use a página de Ferramentas para testar consultas.</li>
                        </ul>
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

