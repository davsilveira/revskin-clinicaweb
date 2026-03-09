import { useState } from 'react';

const tipoConfig = {
    ligacao: { label: 'Ligação', icon: '📞', color: 'bg-blue-100 text-blue-700' },
    whatsapp: { label: 'WhatsApp', icon: '💬', color: 'bg-green-100 text-green-700' },
    email: { label: 'E-mail', icon: '📧', color: 'bg-purple-100 text-purple-700' },
    observacao: { label: 'Observação', icon: '📝', color: 'bg-gray-100 text-gray-700' },
};

/**
 * AcompanhamentoSection - Seção de acompanhamento para Call Center
 * 
 * Layout com 2 colunas:
 * - Esquerda: Form para adicionar novo acompanhamento
 * - Direita: Tabela de histórico
 * 
 * Props:
 * - acompanhamentos: array de histórico [{id, tipo, descricao, created_at, usuario}]
 * - onAdd: callback(tipo, descricao) quando adicionar novo
 * - processing: estado de loading
 */
export default function AcompanhamentoSection({
    acompanhamentos = [],
    onAdd,
    processing = false,
}) {
    const [tipo, setTipo] = useState('ligacao');
    const [descricao, setDescricao] = useState('');

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!descricao.trim()) return;
        onAdd?.(tipo, descricao);
        setDescricao('');
    };

    const formatDateTime = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {/* Coluna Esquerda - Form */}
            <div className="bg-white rounded-lg border border-gray-200 p-4">
                <h3 className="text-sm font-semibold text-gray-900 mb-3">Novo Acompanhamento</h3>
                
                <form onSubmit={handleSubmit} className="space-y-3">
                    {/* Textarea */}
                    <textarea
                        value={descricao}
                        onChange={(e) => setDescricao(e.target.value)}
                        rows={4}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm resize-none"
                        placeholder="Descreva o acompanhamento..."
                    />

                    {/* Tipo Buttons */}
                    <div className="flex flex-wrap gap-2">
                        {Object.entries(tipoConfig).map(([key, config]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setTipo(key)}
                                className={`px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition-colors ${
                                    tipo === key
                                        ? 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-500'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }`}
                            >
                                <span>{config.icon}</span>
                                {config.label}
                            </button>
                        ))}
                    </div>

                    {/* Submit Button */}
                    <button
                        type="submit"
                        disabled={processing || !descricao.trim()}
                        className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 text-sm font-medium"
                    >
                        {processing ? 'Salvando...' : 'Salvar Acompanhamento'}
                    </button>
                </form>
            </div>

            {/* Coluna Direita - Histórico */}
            <div className="bg-white rounded-lg border border-gray-200 p-4">
                <h3 className="text-sm font-semibold text-gray-900 mb-3">Histórico</h3>
                
                {acompanhamentos.length > 0 ? (
                    <div className="overflow-auto max-h-48">
                        <table className="w-full text-xs">
                            <thead className="bg-gray-50 sticky top-0">
                                <tr>
                                    <th className="text-left px-2 py-1.5 font-medium text-gray-600">Hora</th>
                                    <th className="text-left px-2 py-1.5 font-medium text-gray-600">Usuário</th>
                                    <th className="text-left px-2 py-1.5 font-medium text-gray-600">Atendimento</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {acompanhamentos.map((acomp, index) => (
                                    <tr key={acomp.id || index} className="hover:bg-gray-50">
                                        <td className="px-2 py-1.5 text-gray-600 whitespace-nowrap">
                                            {formatDateTime(acomp.created_at || acomp.data_registro)}
                                        </td>
                                        <td className="px-2 py-1.5 text-gray-600 whitespace-nowrap">
                                            {acomp.usuario?.name || '-'}
                                        </td>
                                        <td className="px-2 py-1.5 text-gray-900">
                                            <div className="flex items-start gap-1">
                                                {acomp.tipo && (
                                                    <span className={`px-1.5 py-0.5 rounded text-xs ${tipoConfig[acomp.tipo]?.color || 'bg-gray-100 text-gray-600'}`}>
                                                        {tipoConfig[acomp.tipo]?.icon}
                                                    </span>
                                                )}
                                                <span className="line-clamp-2">{acomp.descricao}</span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="text-center py-6 text-gray-500 text-sm">
                        Nenhum acompanhamento registrado
                    </div>
                )}
            </div>
        </div>
    );
}
