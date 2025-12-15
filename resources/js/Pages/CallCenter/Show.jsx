import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function CallCenterShow({ atendimento }) {
    const { data, setData, post, processing } = useForm({
        tipo: 'ligacao',
        descricao: '',
    });

    const statusConfig = {
        entrar_em_contato: { label: 'Entrar em Contato', color: 'bg-yellow-100 text-yellow-800' },
        aguardando_retorno: { label: 'Aguardando Retorno', color: 'bg-purple-100 text-purple-800' },
        em_producao: { label: 'Em Produção', color: 'bg-blue-100 text-blue-800' },
        finalizado: { label: 'Finalizado', color: 'bg-green-100 text-green-800' },
        cancelado: { label: 'Cancelado', color: 'bg-red-100 text-red-800' },
    };

    const tipoConfig = {
        ligacao: { label: 'Ligação', icon: '📞' },
        whatsapp: { label: 'WhatsApp', icon: '💬' },
        email: { label: 'E-mail', icon: '📧' },
        observacao: { label: 'Observação', icon: '📝' },
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/callcenter/${atendimento.id}/acompanhamento`, {
            onSuccess: () => {
                setData('descricao', '');
            },
        });
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-6xl mx-auto">
                <div className="mb-6">
                    <Link
                        href="/callcenter"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Call Center
                    </Link>
                    
                    <div className="flex justify-between items-start mt-2">
                        <h1 className="text-2xl font-bold text-gray-900">
                            Atendimento #{atendimento.id}
                        </h1>
                        <span className={`px-3 py-1 text-sm font-medium rounded-full ${
                            statusConfig[atendimento.status]?.color
                        }`}>
                            {statusConfig[atendimento.status]?.label}
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Receita Info */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Receita</h2>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm text-gray-500">Número</label>
                                    <p className="font-medium text-gray-900">
                                        #{atendimento.receita?.id?.toString().padStart(5, '0')}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm text-gray-500">Data</label>
                                    <p className="font-medium text-gray-900">
                                        {atendimento.receita?.data_receita 
                                            ? new Date(atendimento.receita.data_receita).toLocaleDateString('pt-BR')
                                            : '-'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm text-gray-500">Médico</label>
                                    <p className="font-medium text-gray-900">
                                        {atendimento.receita?.medico?.nome || '-'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm text-gray-500">Valor Total</label>
                                    <p className="font-medium text-emerald-600">
                                        {new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                        }).format(atendimento.receita?.valor_total || 0)}
                                    </p>
                                </div>
                            </div>

                            <Link
                                href={`/receitas/${atendimento.receita?.id}`}
                                className="mt-4 inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 text-sm font-medium"
                            >
                                Ver receita completa →
                            </Link>
                        </div>

                        {/* Histórico de Acompanhamento */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Histórico de Acompanhamento</h2>
                            
                            {atendimento.acompanhamentos?.length > 0 ? (
                                <div className="space-y-4">
                                    {atendimento.acompanhamentos.map((acomp, index) => (
                                        <div key={index} className="flex gap-4 pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                                            <div className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-lg">
                                                {tipoConfig[acomp.tipo]?.icon || '📝'}
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex justify-between items-start">
                                                    <span className="font-medium text-gray-900">
                                                        {tipoConfig[acomp.tipo]?.label || acomp.tipo}
                                                    </span>
                                                    <span className="text-sm text-gray-500">
                                                        {new Date(acomp.created_at).toLocaleString('pt-BR')}
                                                    </span>
                                                </div>
                                                <p className="text-gray-600 mt-1">{acomp.descricao}</p>
                                                {acomp.usuario && (
                                                    <p className="text-xs text-gray-400 mt-1">
                                                        Por: {acomp.usuario.name}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-gray-500">
                                    <p>Nenhum acompanhamento registrado</p>
                                </div>
                            )}

                            {/* Novo Acompanhamento */}
                            <form onSubmit={handleSubmit} className="mt-6 pt-6 border-t border-gray-200">
                                <h3 className="text-sm font-medium text-gray-900 mb-3">Novo Acompanhamento</h3>
                                
                                <div className="flex gap-2 mb-3">
                                    {Object.entries(tipoConfig).map(([key, config]) => (
                                        <button
                                            key={key}
                                            type="button"
                                            onClick={() => setData('tipo', key)}
                                            className={`px-3 py-2 rounded-lg text-sm flex items-center gap-1 transition-colors ${
                                                data.tipo === key
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                            }`}
                                        >
                                            <span>{config.icon}</span>
                                            {config.label}
                                        </button>
                                    ))}
                                </div>

                                <textarea
                                    value={data.descricao}
                                    onChange={(e) => setData('descricao', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Descreva o acompanhamento..."
                                />

                                <div className="flex justify-end mt-3">
                                    <button
                                        type="submit"
                                        disabled={processing || !data.descricao.trim()}
                                        className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                                    >
                                        {processing ? 'Salvando...' : 'Adicionar'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Sidebar - Paciente */}
                    <div className="space-y-6">
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Paciente</h2>
                            
                            <div className="space-y-3">
                                <div>
                                    <label className="text-sm text-gray-500">Nome</label>
                                    <p className="font-medium text-gray-900">{atendimento.paciente?.nome}</p>
                                </div>
                                {atendimento.paciente?.telefone1 && (
                                    <div>
                                        <label className="text-sm text-gray-500">Telefone</label>
                                        <a
                                            href={`tel:${atendimento.paciente.telefone1}`}
                                            className="flex items-center gap-2 font-medium text-emerald-600 hover:text-emerald-700"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            {atendimento.paciente.telefone1}
                                        </a>
                                    </div>
                                )}
                                {atendimento.paciente?.telefone2 && (
                                    <div>
                                        <label className="text-sm text-gray-500">Celular/WhatsApp</label>
                                        <a
                                            href={`https://wa.me/55${atendimento.paciente.telefone2.replace(/\D/g, '')}`}
                                            target="_blank"
                                            className="flex items-center gap-2 font-medium text-green-600 hover:text-green-700"
                                        >
                                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                            </svg>
                                            {atendimento.paciente.telefone2}
                                        </a>
                                    </div>
                                )}
                                {atendimento.paciente?.email1 && (
                                    <div>
                                        <label className="text-sm text-gray-500">E-mail</label>
                                        <a
                                            href={`mailto:${atendimento.paciente.email1}`}
                                            className="flex items-center gap-2 font-medium text-gray-700 hover:text-gray-900 break-all"
                                        >
                                            <svg className="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            {atendimento.paciente.email1}
                                        </a>
                                    </div>
                                )}
                            </div>

                            <Link
                                href={`/pacientes/${atendimento.paciente?.id}`}
                                className="mt-4 block text-center text-emerald-600 hover:text-emerald-700 text-sm font-medium"
                            >
                                Ver perfil completo →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}




