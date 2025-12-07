import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';
import MaskedInput from '@/Components/Form/MaskedInput';
import Toast from '@/Components/Toast';

export default function MedicoForm({ medico, clinicas, tabelasPreco }) {
    const isEditing = !!medico;
    const [toast, setToast] = useState(null);
    const [loadingCep, setLoadingCep] = useState(false);
    const [uploadingAssinatura, setUploadingAssinatura] = useState(false);
    const assinaturaInput = useRef(null);

    const { data, setData, post, put, processing, errors } = useForm({
        nome: medico?.nome || '',
        apelido: medico?.apelido || '',
        crm: medico?.crm || '',
        cpf: medico?.cpf || '',
        rg: medico?.rg || '',
        especialidade: medico?.especialidade || '',
        clinica_id: medico?.clinica_id || '',
        tabela_preco_id: medico?.tabela_preco_id || '',
        telefone1: medico?.telefone1 || '',
        telefone2: medico?.telefone2 || '',
        telefone3: medico?.telefone3 || '',
        email1: medico?.email1 || '',
        email2: medico?.email2 || '',
        tipo_endereco: medico?.tipo_endereco || '',
        endereco: medico?.endereco || '',
        numero: medico?.numero || '',
        complemento: medico?.complemento || '',
        bairro: medico?.bairro || '',
        cidade: medico?.cidade || '',
        uf: medico?.uf || '',
        cep: medico?.cep || '',
        rodape_receita: medico?.rodape_receita || '',
        anotacoes: medico?.anotacoes || '',
        ativo: medico?.ativo ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        if (isEditing) {
            put(`/medicos/${medico.id}`, {
                onSuccess: () => {
                    setToast({ message: 'Médico atualizado com sucesso!', type: 'success' });
                },
            });
        } else {
            post('/medicos', {
                onSuccess: () => {
                    setToast({ message: 'Médico cadastrado com sucesso!', type: 'success' });
                },
            });
        }
    };

    const handleCepBlur = async () => {
        const cep = data.cep.replace(/\D/g, '');
        if (cep.length !== 8) return;

        setLoadingCep(true);
        try {
            const response = await fetch(`/api/cep/${cep}`);
            if (response.ok) {
                const endereco = await response.json();
                setData(prev => ({
                    ...prev,
                    endereco: endereco.endereco || prev.endereco,
                    bairro: endereco.bairro || prev.bairro,
                    cidade: endereco.cidade || prev.cidade,
                    uf: endereco.uf || prev.uf,
                    complemento: endereco.complemento || prev.complemento,
                }));
                setToast({ message: 'Endereço encontrado!', type: 'success' });
            } else {
                setToast({ message: 'CEP não encontrado', type: 'error' });
            }
        } catch (error) {
            setToast({ message: 'Erro ao buscar CEP', type: 'error' });
        } finally {
            setLoadingCep(false);
        }
    };

    const handleAssinaturaUpload = (e) => {
        const file = e.target.files[0];
        if (!file) return;

        setUploadingAssinatura(true);
        const formData = new FormData();
        formData.append('assinatura', file);

        router.post(`/medicos/${medico.id}/assinatura`, formData, {
            forceFormData: true,
            onSuccess: () => {
                setToast({ message: 'Assinatura atualizada com sucesso!', type: 'success' });
                setUploadingAssinatura(false);
            },
            onError: () => {
                setToast({ message: 'Erro ao enviar assinatura', type: 'error' });
                setUploadingAssinatura(false);
            },
        });
    };

    const ufOptions = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
        'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
        'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
    ];

    return (
        <DashboardLayout>
            <Head title={isEditing ? 'Editar Médico' : 'Novo Médico'} />

            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <Link
                        href="/medicos"
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4"
                    >
                        <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para lista
                    </Link>
                    <h1 className="text-3xl font-bold text-gray-900">
                        {isEditing ? 'Editar Médico' : 'Novo Médico'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit}>
                    {/* Dados Pessoais */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados Pessoais</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Input
                                label="Nome Completo"
                                value={data.nome}
                                onChange={(e) => setData('nome', e.target.value)}
                                error={errors.nome}
                                required
                            />
                            <Input
                                label="Apelido"
                                value={data.apelido}
                                onChange={(e) => setData('apelido', e.target.value)}
                                error={errors.apelido}
                            />
                            <Input
                                label="CRM"
                                value={data.crm}
                                onChange={(e) => setData('crm', e.target.value)}
                                error={errors.crm}
                            />
                            <Input
                                label="Especialidade"
                                value={data.especialidade}
                                onChange={(e) => setData('especialidade', e.target.value)}
                                error={errors.especialidade}
                            />
                            <MaskedInput
                                label="CPF"
                                mask="999.999.999-99"
                                value={data.cpf}
                                onChange={(e) => setData('cpf', e.target.value)}
                                error={errors.cpf}
                            />
                            <Input
                                label="RG"
                                value={data.rg}
                                onChange={(e) => setData('rg', e.target.value)}
                                error={errors.rg}
                            />
                            <Select
                                label="Clínica"
                                value={data.clinica_id}
                                onChange={(e) => setData('clinica_id', e.target.value)}
                                error={errors.clinica_id}
                                options={[
                                    { value: '', label: 'Selecione...' },
                                    ...clinicas.map(c => ({ value: c.id, label: c.nome }))
                                ]}
                            />
                            <Select
                                label="Tabela de Preço"
                                value={data.tabela_preco_id}
                                onChange={(e) => setData('tabela_preco_id', e.target.value)}
                                error={errors.tabela_preco_id}
                                options={[
                                    { value: '', label: 'Selecione...' },
                                    ...tabelasPreco.map(t => ({ value: t.id, label: t.nome }))
                                ]}
                            />
                        </div>
                    </div>

                    {/* Contato */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Contato</h2>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <MaskedInput
                                label="Telefone 1"
                                mask="(99) 99999-9999"
                                value={data.telefone1}
                                onChange={(e) => setData('telefone1', e.target.value)}
                                error={errors.telefone1}
                            />
                            <MaskedInput
                                label="Telefone 2"
                                mask="(99) 99999-9999"
                                value={data.telefone2}
                                onChange={(e) => setData('telefone2', e.target.value)}
                                error={errors.telefone2}
                            />
                            <MaskedInput
                                label="Telefone 3"
                                mask="(99) 99999-9999"
                                value={data.telefone3}
                                onChange={(e) => setData('telefone3', e.target.value)}
                                error={errors.telefone3}
                            />
                            <Input
                                label="E-mail 1"
                                type="email"
                                value={data.email1}
                                onChange={(e) => setData('email1', e.target.value)}
                                error={errors.email1}
                            />
                            <Input
                                label="E-mail 2"
                                type="email"
                                value={data.email2}
                                onChange={(e) => setData('email2', e.target.value)}
                                error={errors.email2}
                            />
                        </div>
                    </div>

                    {/* Endereço */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Endereço</h2>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div className="relative">
                                <MaskedInput
                                    label="CEP"
                                    mask="99999-999"
                                    value={data.cep}
                                    onChange={(e) => setData('cep', e.target.value)}
                                    onBlur={handleCepBlur}
                                    error={errors.cep}
                                />
                                {loadingCep && (
                                    <div className="absolute right-3 top-9">
                                        <svg className="animate-spin h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                    </div>
                                )}
                            </div>
                            <Select
                                label="Tipo"
                                value={data.tipo_endereco}
                                onChange={(e) => setData('tipo_endereco', e.target.value)}
                                error={errors.tipo_endereco}
                                options={[
                                    { value: '', label: 'Selecione...' },
                                    { value: 'Residencial', label: 'Residencial' },
                                    { value: 'Comercial', label: 'Comercial' },
                                ]}
                            />
                            <div className="md:col-span-2">
                                <Input
                                    label="Endereço"
                                    value={data.endereco}
                                    onChange={(e) => setData('endereco', e.target.value)}
                                    error={errors.endereco}
                                />
                            </div>
                            <Input
                                label="Número"
                                value={data.numero}
                                onChange={(e) => setData('numero', e.target.value)}
                                error={errors.numero}
                            />
                            <Input
                                label="Complemento"
                                value={data.complemento}
                                onChange={(e) => setData('complemento', e.target.value)}
                                error={errors.complemento}
                            />
                            <Input
                                label="Bairro"
                                value={data.bairro}
                                onChange={(e) => setData('bairro', e.target.value)}
                                error={errors.bairro}
                            />
                            <Input
                                label="Cidade"
                                value={data.cidade}
                                onChange={(e) => setData('cidade', e.target.value)}
                                error={errors.cidade}
                            />
                            <Select
                                label="UF"
                                value={data.uf}
                                onChange={(e) => setData('uf', e.target.value)}
                                error={errors.uf}
                                options={[
                                    { value: '', label: 'UF' },
                                    ...ufOptions.map(uf => ({ value: uf, label: uf }))
                                ]}
                            />
                        </div>
                    </div>

                    {/* Receita */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Configuração de Receita</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Rodapé da Receita
                                </label>
                                <textarea
                                    value={data.rodape_receita}
                                    onChange={(e) => setData('rodape_receita', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Texto que aparecerá no rodapé das receitas..."
                                />
                                {errors.rodape_receita && (
                                    <p className="mt-1 text-sm text-red-600">{errors.rodape_receita}</p>
                                )}
                            </div>

                            {/* Assinatura (only when editing) */}
                            {isEditing && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Assinatura
                                    </label>
                                    <div className="flex items-start gap-4">
                                        {medico.assinatura_path && (
                                            <div className="border border-gray-200 rounded-lg p-2">
                                                <img
                                                    src={`/storage/${medico.assinatura_path}`}
                                                    alt="Assinatura"
                                                    className="max-h-24"
                                                />
                                            </div>
                                        )}
                                        <div>
                                            <input
                                                ref={assinaturaInput}
                                                type="file"
                                                accept="image/*"
                                                onChange={handleAssinaturaUpload}
                                                className="hidden"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => assinaturaInput.current.click()}
                                                disabled={uploadingAssinatura}
                                                className="px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50"
                                            >
                                                {uploadingAssinatura ? 'Enviando...' : medico.assinatura_path ? 'Alterar Assinatura' : 'Enviar Assinatura'}
                                            </button>
                                            <p className="mt-1 text-xs text-gray-500">
                                                Formatos aceitos: JPG, PNG, GIF. Máximo 2MB.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Anotações */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Anotações</h2>
                        <textarea
                            value={data.anotacoes}
                            onChange={(e) => setData('anotacoes', e.target.value)}
                            rows={4}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            placeholder="Observações sobre o médico..."
                        />
                        {errors.anotacoes && (
                            <p className="mt-1 text-sm text-red-600">{errors.anotacoes}</p>
                        )}
                    </div>

                    {/* Status (only when editing) */}
                    {isEditing && (
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Status</h2>
                            <Select
                                label="Situação"
                                value={data.ativo ? '1' : '0'}
                                onChange={(e) => setData('ativo', e.target.value === '1')}
                                error={errors.ativo}
                                options={[
                                    { value: '1', label: 'Ativo' },
                                    { value: '0', label: 'Inativo' },
                                ]}
                            />
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-4">
                        <Link
                            href="/medicos"
                            className="px-6 py-3 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-8 py-3 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors shadow-lg shadow-emerald-600/30"
                        >
                            {processing ? 'Salvando...' : 'Salvar'}
                        </button>
                    </div>
                </form>
            </div>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
        </DashboardLayout>
    );
}

