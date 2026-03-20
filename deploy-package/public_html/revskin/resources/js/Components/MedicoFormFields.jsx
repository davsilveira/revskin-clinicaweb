import { useCallback } from 'react';
import Input from '@/Components/Form/Input';
import Select from '@/Components/Form/Select';
import MaskedInput from '@/Components/Form/MaskedInput';

const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
const UF_OPTIONS = UFS.map(uf => ({ value: uf, label: uf }));
const UF_OPTIONS_WITH_EMPTY = [{ value: '', label: '-' }, ...UF_OPTIONS]; // Para endereços (opcional)

export default function MedicoFormFields({
    data,
    setData,
    errors = {},
    clinicas = [],
    existingMedico = null,
    showAtivo = false,
}) {
    const addEndereco = () => {
        setData('enderecos', [
            ...(data.enderecos || []),
            { nome: '', cep: '', endereco: '', numero: '', complemento: '', bairro: '', cidade: '', uf: '' },
        ]);
    };

    const removeEndereco = (index) => {
        const newEnderecos = [...(data.enderecos || [])];
        newEnderecos.splice(index, 1);
        setData('enderecos', newEnderecos);
    };

    const updateEndereco = (index, field, value) => {
        const newEnderecos = [...(data.enderecos || [])];
        newEnderecos[index] = { ...newEnderecos[index], [field]: value };
        setData('enderecos', newEnderecos);
    };

    const buscarCepEndereco = useCallback(async (index) => {
        const enderecos = data.enderecos || [];
        const cepLimpo = enderecos[index]?.cep?.replace(/\D/g, '');
        if (!cepLimpo || cepLimpo.length < 8) return;
        try {
            const response = await fetch(`/api/cep/${cepLimpo}`);
            const result = await response.json();
            if (result.success) {
                const newEnderecos = [...enderecos];
                newEnderecos[index] = {
                    ...newEnderecos[index],
                    endereco: result.data.logradouro || '',
                    bairro: result.data.bairro || '',
                    cidade: result.data.localidade || '',
                    uf: result.data.uf || '',
                };
                setData('enderecos', newEnderecos);
            }
        } catch (e) {
            console.error(e);
        }
    }, [data.enderecos, setData]);

    const addClinica = (clinicaId) => {
        const id = parseInt(clinicaId);
        if (!id) return;
        const current = data.clinica_ids || [];
        if (current.includes(id)) return;
        setData('clinica_ids', [...current, id]);
    };

    const removeClinica = (clinicaId) => {
        setData('clinica_ids', (data.clinica_ids || []).filter(id => id !== clinicaId));
    };

    const selectedClinicas = clinicas.filter(c => (data.clinica_ids || []).includes(c.id));
    const enderecos = data.enderecos || [];

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-3 gap-4">
                <div className="col-span-2">
                    <Input
                        label="CRM"
                        value={data.crm || ''}
                        onChange={(e) => setData('crm', e.target.value)}
                        error={errors.crm}
                        required
                    />
                </div>
                <Select
                    label="UF CRM"
                    value={data.uf_crm || ''}
                    onChange={(e) => setData('uf_crm', e.target.value)}
                    error={errors.uf_crm}
                    required
                    options={[
                        { value: '', label: 'Selecione a UF' },
                        ...UF_OPTIONS,
                    ]}
                />
            </div>

            <Input
                label="Especialidade"
                value={data.especialidade || ''}
                onChange={(e) => setData('especialidade', e.target.value)}
                placeholder="Ex: Dermatologia"
            />

            <div className="grid grid-cols-2 gap-4">
                <MaskedInput
                    label="Telefone"
                    value={data.telefone || ''}
                    onAccept={(value) => setData('telefone', value)}
                    mask="(00) 0000-0000"
                    placeholder="(00) 0000-0000"
                    error={errors.telefone}
                />
                <MaskedInput
                    label="Celular"
                    value={data.celular || ''}
                    onAccept={(value) => setData('celular', value)}
                    mask="(00) 00000-0000"
                    placeholder="(00) 00000-0000"
                    required
                    error={errors.celular}
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Assinatura (imagem)</label>
                <input
                    type="file"
                    accept="image/*"
                    onChange={(e) => setData('assinatura', e.target.files?.[0])}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg"
                />
                {existingMedico?.assinatura_url && !data.remover_assinatura && (
                    <div className="mt-2 flex items-center gap-2">
                        <img src={existingMedico.assinatura_url} alt="Assinatura" className="h-16 border border-gray-200 rounded-lg" />
                        <button
                            type="button"
                            onClick={() => setData('remover_assinatura', true)}
                            className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                            title="Remover assinatura"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                )}
                {data.remover_assinatura && (
                    <div className="mt-2 flex items-center gap-3 text-sm text-gray-500">
                        <span>Assinatura será removida ao salvar</span>
                        <button
                            type="button"
                            onClick={() => setData('remover_assinatura', false)}
                            className="text-emerald-600 hover:underline"
                        >
                            Cancelar
                        </button>
                    </div>
                )}
            </div>

            {/* Endereços */}
            <div className="border-t pt-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-sm font-medium text-gray-900">Endereços</h3>
                    <button
                        type="button"
                        onClick={addEndereco}
                        className="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Adicionar Endereço
                    </button>
                </div>
                {enderecos.length > 0 ? (
                    <div className="space-y-3">
                        {enderecos.map((endereco, index) => (
                            <div key={index} className="border border-gray-200 rounded-lg p-3 relative bg-gray-50/50">
                                <button
                                    type="button"
                                    onClick={() => removeEndereco(index)}
                                    className="absolute top-2 right-2 p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <div className="space-y-2">
                                    <div className="pr-6">
                                        <Input
                                            label="Nome do Endereço"
                                            placeholder="Ex: Consultório, Residência..."
                                            value={endereco.nome || ''}
                                            onChange={(e) => updateEndereco(index, 'nome', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid grid-cols-12 gap-2">
                                        <div className="col-span-3">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                            <input
                                                type="text"
                                                value={endereco.cep || ''}
                                                onChange={(e) => updateEndereco(index, 'cep', e.target.value)}
                                                onBlur={() => buscarCepEndereco(index)}
                                                placeholder="00000-000"
                                                className="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm"
                                            />
                                        </div>
                                        <div className="col-span-9">
                                            <Input
                                                label="Endereço"
                                                value={endereco.endereco || ''}
                                                onChange={(e) => updateEndereco(index, 'endereco', e.target.value)}
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <Input
                                                label="Nº"
                                                value={endereco.numero || ''}
                                                onChange={(e) => updateEndereco(index, 'numero', e.target.value)}
                                            />
                                        </div>
                                        <div className="col-span-4">
                                            <Input
                                                label="Complemento"
                                                value={endereco.complemento || ''}
                                                onChange={(e) => updateEndereco(index, 'complemento', e.target.value)}
                                            />
                                        </div>
                                        <div className="col-span-6">
                                            <Input
                                                label="Bairro"
                                                value={endereco.bairro || ''}
                                                onChange={(e) => updateEndereco(index, 'bairro', e.target.value)}
                                            />
                                        </div>
                                        <div className="col-span-9">
                                            <Input
                                                label="Cidade"
                                                value={endereco.cidade || ''}
                                                onChange={(e) => updateEndereco(index, 'cidade', e.target.value)}
                                            />
                                        </div>
                                        <div className="col-span-3">
                                            <Select
                                                label="UF"
                                                value={endereco.uf || ''}
                                                onChange={(e) => updateEndereco(index, 'uf', e.target.value)}
                                                options={UF_OPTIONS_WITH_EMPTY}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-gray-500">Clique em "Adicionar Endereço" para incluir endereços</p>
                )}
            </div>

            {showAtivo && (
                <Select
                    label="Status"
                    value={data.ativo ? '1' : '0'}
                    onChange={(e) => setData('ativo', e.target.value === '1')}
                    options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]}
                />
            )}

            {/* Clínicas */}
            <div className="border-t pt-6">
                <h3 className="text-sm font-medium text-gray-900 mb-4">Clínicas Vinculadas</h3>
                {selectedClinicas.length > 0 && (
                    <div className="flex flex-wrap gap-2 mb-4">
                        {selectedClinicas.map((clinica) => (
                            <div
                                key={clinica.id}
                                className="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-100 text-purple-800 rounded-full text-sm"
                            >
                                <span>{clinica.nome}</span>
                                <button
                                    type="button"
                                    onClick={() => removeClinica(clinica.id)}
                                    className="text-purple-600 hover:text-purple-800"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        ))}
                    </div>
                )}
                <Select
                    label=""
                    value=""
                    onChange={(e) => addClinica(e.target.value)}
                    options={[
                        { value: '', label: clinicas.length > 0 ? 'Adicionar clínica...' : 'Nenhuma clínica cadastrada' },
                        ...clinicas.filter(c => !selectedClinicas.find(s => s.id === c.id)).map(c => ({ value: c.id, label: c.nome })),
                    ]}
                />
            </div>
        </div>
    );
}
