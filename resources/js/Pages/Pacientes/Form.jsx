import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';
import MaskedInput from '@/Components/Form/MaskedInput';
import Toast from '@/Components/Toast';

export default function PacienteForm({ paciente, medicos, sexoOptions, fototipoOptions }) {
    const isEditing = !!paciente;
    const [toast, setToast] = useState(null);
    const [loadingCep, setLoadingCep] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        nome: paciente?.nome || '',
        data_nascimento: paciente?.data_nascimento || '',
        sexo: paciente?.sexo || '',
        fototipo: paciente?.fototipo || '',
        cpf: paciente?.cpf || '',
        rg: paciente?.rg || '',
        telefone1: paciente?.telefone1 || '',
        telefone2: paciente?.telefone2 || '',
        telefone3: paciente?.telefone3 || '',
        email1: paciente?.email1 || '',
        email2: paciente?.email2 || '',
        tipo_endereco: paciente?.tipo_endereco || '',
        endereco: paciente?.endereco || '',
        numero: paciente?.numero || '',
        complemento: paciente?.complemento || '',
        bairro: paciente?.bairro || '',
        cidade: paciente?.cidade || '',
        uf: paciente?.uf || '',
        cep: paciente?.cep || '',
        indicado_por: paciente?.indicado_por || '',
        anotacoes: paciente?.anotacoes || '',
        medico_id: paciente?.medico_id || '',
        ativo: paciente?.ativo ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        if (isEditing) {
            put(`/pacientes/${paciente.id}`, {
                onSuccess: () => {
                    setToast({ message: 'Paciente atualizado com sucesso!', type: 'success' });
                },
            });
        } else {
            post('/pacientes', {
                onSuccess: () => {
                    setToast({ message: 'Paciente cadastrado com sucesso!', type: 'success' });
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

    const ufOptions = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
        'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
        'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
    ];

    return (
        <DashboardLayout>
            <Head title={isEditing ? 'Editar Paciente' : 'Novo Paciente'} />

            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <Link
                        href="/pacientes"
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4"
                    >
                        <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para lista
                    </Link>
                    <h1 className="text-3xl font-bold text-gray-900">
                        {isEditing ? 'Editar Paciente' : 'Novo Paciente'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit}>
                    {/* Dados Pessoais */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados Pessoais</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <Input
                                    label="Nome Completo"
                                    value={data.nome}
                                    onChange={(e) => setData('nome', e.target.value)}
                                    error={errors.nome}
                                    required
                                />
                            </div>
                            <Input
                                label="Data de Nascimento"
                                type="date"
                                value={data.data_nascimento}
                                onChange={(e) => setData('data_nascimento', e.target.value)}
                                error={errors.data_nascimento}
                            />
                            <Select
                                label="Sexo"
                                value={data.sexo}
                                onChange={(e) => setData('sexo', e.target.value)}
                                error={errors.sexo}
                                options={[
                                    { value: '', label: 'Selecione...' },
                                    ...sexoOptions.map(s => ({ value: s, label: s }))
                                ]}
                            />
                            <Select
                                label="Fototipo"
                                value={data.fototipo}
                                onChange={(e) => setData('fototipo', e.target.value)}
                                error={errors.fototipo}
                                options={[
                                    { value: '', label: 'Selecione...' },
                                    ...fototipoOptions.map(f => ({ value: f, label: `Tipo ${f}` }))
                                ]}
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
                                label="Médico Responsável"
                                value={data.medico_id}
                                onChange={(e) => setData('medico_id', e.target.value)}
                                error={errors.medico_id}
                                options={[
                                    { value: '', label: 'Selecione...' },
                                    ...medicos.map(m => ({ value: m.id, label: m.nome }))
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
                            <Input
                                label="Indicado por"
                                value={data.indicado_por}
                                onChange={(e) => setData('indicado_por', e.target.value)}
                                error={errors.indicado_por}
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
                                        <svg className="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
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

                    {/* Anotações */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Anotações</h2>
                        <textarea
                            value={data.anotacoes}
                            onChange={(e) => setData('anotacoes', e.target.value)}
                            rows={4}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Observações sobre o paciente..."
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
                            href="/pacientes"
                            className="px-6 py-3 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-8 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-lg shadow-blue-600/30"
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

