import { useForm, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function ClinicaForm({ clinica }) {
    const isEditing = !!clinica;
    const [loadingCep, setLoadingCep] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        nome: clinica?.nome || '',
        razao_social: clinica?.razao_social || '',
        cnpj: clinica?.cnpj || '',
        inscricao_estadual: clinica?.inscricao_estadual || '',
        telefone: clinica?.telefone || '',
        email: clinica?.email || '',
        cep: clinica?.cep || '',
        endereco: clinica?.endereco || '',
        numero: clinica?.numero || '',
        complemento: clinica?.complemento || '',
        bairro: clinica?.bairro || '',
        cidade: clinica?.cidade || '',
        uf: clinica?.uf || '',
        ativa: clinica?.ativa ?? true,
    });

    const buscarCep = async () => {
        if (data.cep.length < 8) return;
        
        setLoadingCep(true);
        try {
            const cepLimpo = data.cep.replace(/\D/g, '');
            const response = await fetch(`/api/cep/${cepLimpo}`);
            const result = await response.json();
            
            if (result.success) {
                setData({
                    ...data,
                    endereco: result.data.logradouro || '',
                    bairro: result.data.bairro || '',
                    cidade: result.data.localidade || '',
                    uf: result.data.uf || '',
                });
            }
        } catch (error) {
            console.error('Erro ao buscar CEP:', error);
        } finally {
            setLoadingCep(false);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/clinicas/${clinica.id}`);
        } else {
            post('/clinicas');
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-4xl mx-auto">
                <div className="mb-6">
                    <Link
                        href="/clinicas"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Clínicas
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">
                        {isEditing ? 'Editar Clínica' : 'Nova Clínica'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Dados Básicos */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Dados da Clínica</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Nome Fantasia *
                                </label>
                                <input
                                    type="text"
                                    value={data.nome}
                                    onChange={(e) => setData('nome', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {errors.nome && <p className="mt-1 text-sm text-red-600">{errors.nome}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Razão Social
                                </label>
                                <input
                                    type="text"
                                    value={data.razao_social}
                                    onChange={(e) => setData('razao_social', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    CNPJ
                                </label>
                                <input
                                    type="text"
                                    value={data.cnpj}
                                    onChange={(e) => setData('cnpj', e.target.value)}
                                    placeholder="00.000.000/0000-00"
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {errors.cnpj && <p className="mt-1 text-sm text-red-600">{errors.cnpj}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Inscrição Estadual
                                </label>
                                <input
                                    type="text"
                                    value={data.inscricao_estadual}
                                    onChange={(e) => setData('inscricao_estadual', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Telefone
                                </label>
                                <input
                                    type="text"
                                    value={data.telefone}
                                    onChange={(e) => setData('telefone', e.target.value)}
                                    placeholder="(00) 0000-0000"
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    E-mail
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.ativa}
                                        onChange={(e) => setData('ativa', e.target.checked)}
                                        className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                    />
                                    <span className="text-sm font-medium text-gray-700">Clínica ativa</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Endereço */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Endereço</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-6 gap-6">
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    CEP
                                </label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={data.cep}
                                        onChange={(e) => setData('cep', e.target.value)}
                                        onBlur={buscarCep}
                                        placeholder="00000-000"
                                        className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={buscarCep}
                                        disabled={loadingCep}
                                        className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-50"
                                    >
                                        {loadingCep ? '...' : 'Buscar'}
                                    </button>
                                </div>
                            </div>

                            <div className="md:col-span-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Endereço
                                </label>
                                <input
                                    type="text"
                                    value={data.endereco}
                                    onChange={(e) => setData('endereco', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div className="md:col-span-1">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Número
                                </label>
                                <input
                                    type="text"
                                    value={data.numero}
                                    onChange={(e) => setData('numero', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Complemento
                                </label>
                                <input
                                    type="text"
                                    value={data.complemento}
                                    onChange={(e) => setData('complemento', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div className="md:col-span-3">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Bairro
                                </label>
                                <input
                                    type="text"
                                    value={data.bairro}
                                    onChange={(e) => setData('bairro', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div className="md:col-span-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Cidade
                                </label>
                                <input
                                    type="text"
                                    value={data.cidade}
                                    onChange={(e) => setData('cidade', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    UF
                                </label>
                                <select
                                    value={data.uf}
                                    onChange={(e) => setData('uf', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="">Selecione</option>
                                    {['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].map(uf => (
                                        <option key={uf} value={uf}>{uf}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-4">
                        <Link
                            href="/clinicas"
                            className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                        >
                            {processing ? 'Salvando...' : (isEditing ? 'Atualizar' : 'Cadastrar')}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}

