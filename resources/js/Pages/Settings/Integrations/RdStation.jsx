import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/Components/Form/Input';
import Checkbox from '@/Components/Form/Checkbox';
import Toast from '@/Components/Toast';

export default function RdStationSettings({
    settings,
    onToast,
    isAuthenticated,
    webhookReceipts = [],
    webhookLastReceivedAt = null,
}) {
    const [toast, setToast] = useState(null);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [showClientSecret, setShowClientSecret] = useState(false);
    const [removeClientSecret, setRemoveClientSecret] = useState(false);
    const [authorizing, setAuthorizing] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);
    const [receipts, setReceipts] = useState(webhookReceipts);
    const [lastReceivedAt, setLastReceivedAt] = useState(webhookLastReceivedAt);
    const [refreshingWebhookLog, setRefreshingWebhookLog] = useState(false);

    const { data, setData, put, processing, errors, transform } = useForm({
        enabled: settings.enabled || false,
        client_id: '',
        client_secret: '',
        remove_client_secret: false,
        stage_id: settings.stage_id || '6929f60257dcba001d9b375b',
        produto_padrao_id: settings.produto_padrao_id || '69a956705a1a6a00133167dc',
        medico_field_id: settings.medico_field_id || '69a955ea78fde3001f6f61dc',
        receita_field_id: settings.receita_field_id || '699efc3a13a467001cb81ea1',
        cortesia_field_id: settings.cortesia_field_id || '6a721f71257c0d0020d8178e',
        owner_id: settings.owner_id || '',
        cancelamento_field_id: settings.cancelamento_field_id || '',
        cancelamento_field_value: settings.cancelamento_field_value || '',
        webhook_secret: settings.webhook_secret || '',
    });

    transform((data) => {
        const transformed = {
            enabled: data.enabled,
            stage_id: data.stage_id,
            produto_padrao_id: data.produto_padrao_id,
            medico_field_id: data.medico_field_id,
            receita_field_id: data.receita_field_id,
            cortesia_field_id: data.cortesia_field_id,
            owner_id: data.owner_id,
            cancelamento_field_id: data.cancelamento_field_id,
            cancelamento_field_value: data.cancelamento_field_value,
            webhook_secret: data.webhook_secret,
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

        put('/settings/integrations/rd-station', {
            preserveScroll: true,
            onSuccess: () => {
                const payload = { message: 'Configuracoes do RD Station salvas com sucesso!', type: 'success' };
                setToast(payload);
                if (onToast) onToast(payload);
                setData('client_id', '');
                setData('client_secret', '');
                setData('remove_client_secret', false);
                setRemoveClientSecret(false);
            },
            onError: () => {
                const payload = { message: 'Erro ao salvar configuracoes do RD Station.', type: 'error' };
                setToast(payload);
                if (onToast) onToast(payload);
            },
        });
    };

    const handleTestConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const response = await window.axios.post('/settings/integrations/rd-station/test');
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
                message: error.response?.data?.message ?? 'Falha ao testar conexao com RD Station CRM.',
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
            const response = await window.axios.get('/integracoes/rd-station/auth-url');
            if (response.data?.success && response.data?.auth_url) {
                window.location.href = response.data.auth_url;
            } else {
                const payload = { message: 'Erro ao gerar URL de autorizacao.', type: 'error' };
                setToast(payload);
                if (onToast) onToast(payload);
                setAuthorizing(false);
            }
        } catch (error) {
            const payload = {
                message: error.response?.data?.message ?? 'Erro ao iniciar autorizacao.',
                type: 'error',
            };
            setToast(payload);
            if (onToast) onToast(payload);
            setAuthorizing(false);
        }
    };

    const handleDisconnect = async () => {
        if (!confirm('Tem certeza que deseja desconectar? Voce precisara autorizar novamente para usar a integracao.')) {
            return;
        }
        setDisconnecting(true);
        try {
            await window.axios.post('/integracoes/rd-station/disconnect');
            const payload = { message: 'Desconectado do RD Station com sucesso.', type: 'success' };
            setToast(payload);
            if (onToast) onToast(payload);
            window.location.reload();
        } catch (error) {
            const payload = { message: 'Erro ao desconectar.', type: 'error' };
            setToast(payload);
            if (onToast) onToast(payload);
        } finally {
            setDisconnecting(false);
        }
    };

    const btnBase = 'inline-flex items-center justify-center h-9 px-4 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed';
    const spinner = (
        <svg className="animate-spin -ml-0.5 mr-1.5 h-4 w-4" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
    );

    const handleGenerateWebhookSecret = () => {
        const bytes = new Uint8Array(24);
        window.crypto.getRandomValues(bytes);
        const secret = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        setData('webhook_secret', secret);
    };

    const webhookUrl = `${window.location.origin}/api/webhooks/rd/crm-deal-updated`;

    const outcomeLabels = {
        dispatched: 'Job enfileirado',
        rejected_auth: 'Rejeitado (segredo)',
        ignored_event: 'Evento ignorado',
        missing_deal_id: 'Sem ID da negociacao',
    };

    const formatReceivedAt = (value) => {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleString('pt-BR');
        } catch {
            return value;
        }
    };

    const handleRefreshWebhookLog = async () => {
        setRefreshingWebhookLog(true);
        try {
            const response = await fetch('/settings/integrations/rd-station/webhook-log', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (response.ok) {
                setReceipts(data.receipts || []);
                setLastReceivedAt(data.last_received_at || null);
            }
        } finally {
            setRefreshingWebhookLog(false);
        }
    };

    const canTest = settings.has_client_id && settings.has_client_secret;

    return (
        <div className="space-y-4 md:space-y-6">
            <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0">
                    <h2 className="text-xl font-bold text-gray-900">Integracao RD Station CRM</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Configure o acesso a API do RD Station CRM para envio automatico de negociacoes ao finalizar receitas.
                    </p>
                </div>
                <div className="flex flex-col gap-2 w-full md:w-auto items-stretch md:items-end">
                    {isAuthenticated && (
                        <span className="inline-flex self-start md:self-end items-center gap-1.5 px-3 h-7 text-xs font-medium rounded-full bg-green-100 text-green-700">
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                            Autenticado
                        </span>
                    )}
                    <div className="flex flex-wrap items-center gap-2 w-full md:w-auto justify-start md:justify-end">
                        {settings.has_client_id && settings.has_client_secret && (
                            <button type="button" onClick={handleAuthorize} disabled={authorizing} className={`${btnBase} bg-blue-600 text-white hover:bg-blue-700`}>
                                {authorizing ? spinner : <svg className="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>}
                                {authorizing ? 'Redirecionando...' : isAuthenticated ? 'Re-autorizar' : 'Autorizar Aplicativo'}
                            </button>
                        )}
                        <button type="button" onClick={handleTestConnection} disabled={testing || !canTest} className={`${btnBase} border border-gray-300 text-gray-700 hover:bg-gray-50`}>
                            {testing ? spinner : null}
                            {testing ? 'Testando...' : 'Testar conexao'}
                        </button>
                        {isAuthenticated && (
                            <button type="button" onClick={handleDisconnect} disabled={disconnecting} className={`${btnBase} border border-gray-300 text-red-600 hover:bg-red-50`}>
                                {disconnecting ? 'Saindo...' : 'Desconectar'}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {testResult && (
                <div className={`p-3 sm:p-4 rounded-lg ${testResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
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
                                <p className="mt-2 text-sm text-red-700">
                                    Clique em "Autorizar Aplicativo" acima para conectar.
                                </p>
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

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                <div className="lg:col-span-2">
                    <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5 space-y-4 sm:space-y-5">
                        <div>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <Checkbox
                                    checked={data.enabled}
                                    onChange={(event) => setData('enabled', event.target.checked)}
                                />
                                <span className="text-sm font-medium text-gray-700">Ativar integracao</span>
                            </label>
                            <p className="mt-1 text-xs text-gray-500">
                                Quando desativada, nenhuma negociacao sera enviada ao RD Station CRM.
                            </p>
                            {errors.enabled && <p className="mt-1 text-sm text-red-600">{errors.enabled}</p>}
                        </div>

                        <div className="border-t border-gray-200 pt-4 sm:pt-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Credenciais OAuth 2.0</h3>

                            <div className="space-y-4">
                                <div>
                                    <Input
                                        label="Client ID"
                                        value={data.client_id}
                                        onChange={(event) => setData('client_id', event.target.value)}
                                        placeholder={settings.has_client_id ? 'Digite um novo Client ID para substituir' : 'Informe o Client ID do app criado na RD Station'}
                                        error={errors.client_id}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Client ID do aplicativo criado na RD Station App Store.
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
                                            placeholder={settings.has_client_secret ? 'Digite um novo Client Secret para substituir' : 'Informe o Client Secret do app criado na RD Station'}
                                            error={errors.client_secret}
                                        />
                                    )}
                                    <p className="mt-1 text-xs text-gray-500">
                                        Client Secret do aplicativo. Mantenha seguro e nao compartilhe. O valor e criptografado no banco de dados.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-blue-50 rounded-lg border border-blue-200 p-3 sm:p-4">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div className="min-w-0">
                                    <p className="font-medium text-blue-800">URL de Redirecionamento</p>
                                    <p className="text-sm text-blue-700 mt-1">
                                        Configure esta URL no aplicativo criado na RD Station App Store:
                                    </p>
                                    <code className="mt-2 block text-xs bg-white p-2 rounded border border-blue-300 break-all">
                                        {window.location.origin}/integracoes/rd-station/callback
                                    </code>
                                    <p className="text-xs text-blue-600 mt-2">
                                        A URL deve corresponder exatamente ao configurado na RD Station.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-gray-200 pt-4 sm:pt-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Configuracoes do CRM</h3>

                            <div className="space-y-4">
                                <div>
                                    <Input
                                        label="ID da Etapa (Stage ID)"
                                        value={data.stage_id}
                                        onChange={(event) => setData('stage_id', event.target.value)}
                                        placeholder="ID da etapa do funil de vendas"
                                        error={errors.stage_id}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        ID da etapa "Novo Lead" no funil de vendas do RD Station CRM.
                                    </p>
                                </div>

                                <div>
                                    <Input
                                        label="ID do Produto Padrao"
                                        value={data.produto_padrao_id}
                                        onChange={(event) => setData('produto_padrao_id', event.target.value)}
                                        placeholder="ID do produto no RD Station"
                                        error={errors.produto_padrao_id}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        ID do produto no RD Station CRM usado para registrar o valor total da negociacao.
                                    </p>
                                </div>

                                <div>
                                    <Input
                                        label="ID do Campo - Nome do Medico"
                                        value={data.medico_field_id}
                                        onChange={(event) => setData('medico_field_id', event.target.value)}
                                        placeholder="ID do campo personalizado"
                                        error={errors.medico_field_id}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        ID do campo personalizado "Nome do Medico" na negociacao do RD Station.
                                    </p>
                                </div>

                                <div>
                                    <Input
                                        label="ID do Campo - Receita"
                                        value={data.receita_field_id}
                                        onChange={(event) => setData('receita_field_id', event.target.value)}
                                        placeholder="ID do campo personalizado"
                                        error={errors.receita_field_id}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        ID do campo personalizado "Receita" na negociacao do RD Station.
                                    </p>
                                </div>

                                <div>
                                    <Input
                                        label="ID do Campo - Cortesia"
                                        value={data.cortesia_field_id}
                                        onChange={(event) => setData('cortesia_field_id', event.target.value)}
                                        placeholder="ID do campo personalizado"
                                        error={errors.cortesia_field_id}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        ID do campo personalizado "Cortesia" na negociacao. Envia "Sim" quando marcado na receita; vazio quando nao.
                                    </p>
                                </div>

                                <div>
                                    <Input
                                        label="ID do Proprietario (Owner ID)"
                                        value={data.owner_id}
                                        onChange={(event) => setData('owner_id', event.target.value)}
                                        placeholder="ID do usuario responsavel pela negociacao"
                                        error={errors.owner_id}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        ID do usuario do RD Station CRM responsavel pelas negociacoes criadas. Obrigatorio para criar negociacoes.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-gray-200 pt-4 sm:pt-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Cancelamento de receita</h3>
                            <p className="text-xs text-gray-500 mb-4">
                                Ao cancelar uma receita no ClinicaWeb, o sistema preenche um campo customizado na negociacao do RD.
                                Configure uma automacao no RD para marcar a negociacao como <strong>perdida</strong> (<code>lost</code>) quando esse campo receber o valor informado.
                                Sem essa automacao, o ClinicaWeb recebera webhooks com status <code>ongoing</code> e a receita nao sera cancelada pelo RD.
                            </p>

                            <div className="space-y-4">
                                <div>
                                    <Input
                                        label="ID do Campo - Cancelamento"
                                        value={data.cancelamento_field_id}
                                        onChange={(event) => setData('cancelamento_field_id', event.target.value)}
                                        placeholder="ID do campo personalizado na negociacao"
                                        error={errors.cancelamento_field_id}
                                    />
                                </div>

                                <div>
                                    <Input
                                        label="Valor do Campo - Cancelamento"
                                        value={data.cancelamento_field_value}
                                        onChange={(event) => setData('cancelamento_field_value', event.target.value)}
                                        placeholder="Ex.: Sim, Cancelado"
                                        error={errors.cancelamento_field_value}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Valor exato que a automacao do RD deve detectar (label ou ID da opcao, conforme o tipo do campo).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-gray-200 pt-4 sm:pt-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Webhook RD (negociacao atualizada)</h3>
                            <p className="text-xs text-gray-500 mb-4">
                                Configure no RD CRM o evento <strong>Negociacao atualizada</strong> (<code>crm_deal_updated</code>)
                                apontando para a URL abaixo. A receita sera cancelada aqui quando a negociacao ficar com status perdida (<code>lost</code>)
                                <strong> ou </strong>
                                quando o campo de cancelamento configurado acima receber o valor informado (ex.: <code>Cancelada</code>).
                            </p>

                            <div className="bg-amber-50 rounded-lg border border-amber-200 p-3 sm:p-4 mb-4">
                                <p className="font-medium text-amber-800 text-sm">Proxy / forward (autz, n8n, etc.)</p>
                                <p className="text-xs text-amber-700 mt-2">
                                    Encaminhe apenas o JSON interno do evento (campos <code>event_name</code>, <code>document</code>, <code>transaction_uuid</code>),
                                    nao o envelope com <code>headers</code> ou array wrapper. Se usar segredo abaixo, inclua no forward o header{' '}
                                    <code>X-RD-Webhook-Secret</code> — o RD nao envia esse header nativamente.
                                </p>
                            </div>

                            <div className="bg-blue-50 rounded-lg border border-blue-200 p-3 sm:p-4 mb-4">
                                <p className="font-medium text-blue-800 text-sm">URL do webhook</p>
                                <code className="mt-2 block text-xs bg-white p-2 rounded border border-blue-300 break-all">
                                    {webhookUrl}
                                </code>
                                <p className="text-xs text-blue-600 mt-2">
                                    Header recomendado no RD: <code>X-RD-Webhook-Secret: {'{segredo abaixo}'}</code>
                                </p>
                            </div>

                            <div className="space-y-3">
                                <div>
                                    <Input
                                        label="Segredo do Webhook"
                                        value={data.webhook_secret}
                                        onChange={(event) => setData('webhook_secret', event.target.value)}
                                        placeholder="Gere ou informe um segredo"
                                        error={errors.webhook_secret}
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={handleGenerateWebhookSecret}
                                    className="text-sm text-blue-600 hover:text-blue-700 font-medium"
                                >
                                    Gerar segredo aleatorio
                                </button>
                                <p className="text-xs text-gray-500">
                                    Se vazio, o endpoint aceita requisicoes sem autenticacao (nao recomendado em producao).
                                </p>
                            </div>

                            <div className="border-t border-gray-200 pt-4 mt-4">
                                <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
                                    <div>
                                        <h4 className="text-sm font-semibold text-gray-900">Log de recebimentos do webhook</h4>
                                        <p className="text-xs text-gray-500 mt-1">
                                            Ultimo hit no endpoint ClinicaWeb:{' '}
                                            <strong>{formatReceivedAt(lastReceivedAt)}</strong>
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleRefreshWebhookLog}
                                        disabled={refreshingWebhookLog}
                                        className="text-sm text-blue-600 hover:text-blue-700 font-medium disabled:opacity-50"
                                    >
                                        {refreshingWebhookLog ? 'Atualizando...' : 'Atualizar log'}
                                    </button>
                                </div>
                                <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3">
                                    Se esta lista estiver vazia apos alterar uma negociacao no RD, o POST nao esta chegando em{' '}
                                    <code className="text-xs">{webhookUrl}</code>. Confirme se o RD/autz apontam para esta URL (nao so para webhook.autz.com.br).
                                </p>
                                {receipts.length === 0 ? (
                                    <p className="text-sm text-gray-500 italic">Nenhum webhook registrado ainda.</p>
                                ) : (
                                    <div className="overflow-x-auto rounded-lg border border-gray-200">
                                        <table className="min-w-full text-xs">
                                            <thead className="bg-gray-50 text-gray-600">
                                                <tr>
                                                    <th className="px-3 py-2 text-left font-medium">Quando</th>
                                                    <th className="px-3 py-2 text-left font-medium">Resultado</th>
                                                    <th className="px-3 py-2 text-left font-medium">Evento</th>
                                                    <th className="px-3 py-2 text-left font-medium">Status</th>
                                                    <th className="px-3 py-2 text-left font-medium">Deal</th>
                                                    <th className="px-3 py-2 text-left font-medium">IP</th>
                                                    <th className="px-3 py-2 text-left font-medium">Formato</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {receipts.map((row) => (
                                                    <tr key={row.id || row.received_at}>
                                                        <td className="px-3 py-2 whitespace-nowrap text-gray-700">
                                                            {formatReceivedAt(row.received_at)}
                                                        </td>
                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                            <span className={
                                                                row.outcome === 'dispatched'
                                                                    ? 'text-green-700'
                                                                    : row.outcome === 'rejected_auth'
                                                                        ? 'text-red-700'
                                                                        : 'text-gray-600'
                                                            }>
                                                                {outcomeLabels[row.outcome] || row.outcome || '—'}
                                                                {row.http_status ? ` (${row.http_status})` : ''}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 text-gray-700">{row.event_name || '—'}</td>
                                                        <td className="px-3 py-2 text-gray-700">{row.status || '—'}</td>
                                                        <td className="px-3 py-2 text-gray-700 font-mono">{row.deal_id || '—'}</td>
                                                        <td className="px-3 py-2 text-gray-500">{row.ip || '—'}</td>
                                                        <td className="px-3 py-2 text-gray-500">{row.payload_shape || '—'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Salvando...' : 'Salvar configuracoes'}
                            </button>
                        </div>
                    </form>
                </div>

                <div className="space-y-4">
                    <div className="bg-white rounded-xl shadow-sm border border-blue-200 p-4 sm:p-5">
                        <div className="flex items-center gap-3 mb-3">
                            <div className="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center">
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
                                <svg className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Criar empresa (organizacao) no CRM ao finalizar receita
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Criar/atualizar contato vinculado a empresa
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Criar negociacao na etapa "Novo Lead" com valor total
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Preencher campos personalizados (Medico e Receita)
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Marcar negociacao como perdida ao cancelar receita (campo customizado)
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Cancelar receita via webhook quando negociacao ficar perdida no RD
                            </li>
                            <li className="flex items-start gap-2">
                                <svg className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Historico de negociacoes por empresa (paciente)
                            </li>
                        </ul>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3">
                            Como obter as credenciais
                        </h2>
                        <ol className="space-y-2 text-sm text-gray-600 list-decimal list-inside">
                            <li>Acesse a <a href="https://appstore.rdstation.com/pt-BR/publisher" target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">RD Station App Store</a></li>
                            <li>Crie um novo aplicativo para o CRM</li>
                            <li>Configure a URL de redirecionamento (acima)</li>
                            <li>Copie o Client ID e Client Secret gerados</li>
                            <li>Cole nas configuracoes ao lado e salve</li>
                            <li>Clique em "Autorizar Aplicativo"</li>
                        </ol>
                        <a
                            href="https://developers.rdstation.com/reference/crm-v2-authentication"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-4 inline-flex items-center text-sm text-blue-600 hover:text-blue-700"
                        >
                            Documentacao da API
                            <svg className="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>

                    <div className="bg-amber-50 rounded-xl border border-amber-200 p-3 sm:p-4">
                        <div className="flex items-start gap-3">
                            <svg className="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p className="font-medium text-amber-800">Importante</p>
                                <p className="text-sm text-amber-700 mt-1">
                                    O token de acesso expira a cada 2 horas e e renovado automaticamente. Se o refresh token expirar
                                    (14 dias sem uso), sera necessario autorizar novamente.
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
