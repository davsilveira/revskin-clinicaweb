import { Link, useForm, router } from '@inertiajs/react';
import { useState, useCallback, useEffect, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import ProductItemsEditor from '@/Components/Receita/ProductItemsEditor';
import AcompanhamentoSection from '@/Components/CallCenter/AcompanhamentoSection';
import PatientDrawer from '@/Components/PatientDrawer';
import useAutoSave from '@/hooks/useAutoSave';

export default function CallCenterShow({ atendimento, statusOptions, produtos }) {
    const isFirstRender = useRef(true);
    const [showStatusModal, setShowStatusModal] = useState(false);
    const [selectedStatus, setSelectedStatus] = useState(atendimento.status);
    const [statusAcompanhamento, setStatusAcompanhamento] = useState('');
    const [patientDrawerOpen, setPatientDrawerOpen] = useState(false);

    // State for acompanhamento
    const [acompProcessing, setAcompProcessing] = useState(false);

    // Form for receita items
    const [itens, setItens] = useState(
        atendimento.receita?.itens?.map(item => ({
            produto_id: item.produto_id,
            local_uso: item.local_uso || '',
            anotacoes: item.anotacoes || '',
            quantidade: item.quantidade,
            valor_unitario: parseFloat(item.valor_unitario) || 0,
            imprimir: item.imprimir ?? true,
            grupo: item.grupo || 'recomendado',
        })) || []
    );
    const [descontoPercentual, setDescontoPercentual] = useState(atendimento.receita?.desconto_percentual || 0);
    const [descontoMotivo, setDescontoMotivo] = useState(atendimento.receita?.desconto_motivo || '');
    const [valorFrete, setValorFrete] = useState(atendimento.receita?.valor_frete || 0);
    const [valorCaixa, setValorCaixa] = useState(atendimento.receita?.valor_caixa || 0);

    const statusConfig = {
        entrar_em_contato: { label: 'Entrar em Contato', color: 'bg-yellow-100 text-yellow-800', borderColor: 'border-yellow-300' },
        aguardando_retorno: { label: 'Aguardando Retorno', color: 'bg-purple-100 text-purple-800', borderColor: 'border-purple-300' },
        em_producao: { label: 'Em Produção', color: 'bg-blue-100 text-blue-800', borderColor: 'border-blue-300' },
        finalizado: { label: 'Finalizado', color: 'bg-green-100 text-green-800', borderColor: 'border-green-300' },
        cancelado: { label: 'Cancelado', color: 'bg-red-100 text-red-800', borderColor: 'border-red-300' },
    };

    // Autosave function
    const performAutoSave = useCallback(async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch(`/api/callcenter/${atendimento.id}/autosave`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                itens,
                desconto_percentual: descontoPercentual,
                desconto_motivo: descontoMotivo,
                valor_frete: valorFrete,
                valor_caixa: valorCaixa,
            }),
        });
        
        if (!response.ok) throw new Error('Autosave failed');
        return await response.json();
    }, [itens, descontoPercentual, descontoMotivo, valorFrete, valorCaixa, atendimento.id]);

    const { 
        lastSavedText, 
        isSaving: isAutoSaving, 
        triggerAutoSave,
    } = useAutoSave(performAutoSave, 2000, true);

    // Trigger autosave when data changes
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        triggerAutoSave();
    }, [itens, descontoPercentual, descontoMotivo, valorFrete, valorCaixa]);

    const handleAddAcompanhamento = (tipo, descricao) => {
        setAcompProcessing(true);
        router.post(`/callcenter/${atendimento.id}/acompanhamento`, {
            tipo,
            descricao,
        }, {
            preserveScroll: true,
            onFinish: () => setAcompProcessing(false),
        });
    };

    const handleStatusChange = () => {
        router.put(`/callcenter/${atendimento.id}/status`, {
            status: selectedStatus,
            acompanhamento: statusAcompanhamento,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowStatusModal(false);
                setStatusAcompanhamento('');
            },
        });
    };

    const handleAlterarParaProducao = () => {
        setSelectedStatus('em_producao');
        setShowStatusModal(true);
    };

    const formatPhone = (phone) => {
        if (!phone) return null;
        return phone.replace(/\D/g, '');
    };

    return (
        <DashboardLayout>
            <div className="p-6">
                {/* Header */}
                <div className="mb-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Link
                                href="/callcenter"
                                className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                </svg>
                            </Link>
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900">
                                    Atendimento #{atendimento.id}
                                </h1>
                                <p className="text-sm text-gray-500">
                                    Receita #{atendimento.receita?.id?.toString().padStart(5, '0')}
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-center gap-3">
                            {/* Autosave indicator */}
                            {(isAutoSaving || lastSavedText) && (
                                <div className="text-xs text-gray-500 flex items-center gap-1">
                                    {isAutoSaving ? (
                                        <>
                                            <svg className="animate-spin h-3 w-3" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            <span>Salvando...</span>
                                        </>
                                    ) : (
                                        <>
                                            <svg className="h-3 w-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Salvo às {lastSavedText}</span>
                                        </>
                                    )}
                                </div>
                            )}
                            
                            {/* Status Badge */}
                            <span className={`px-3 py-1 text-sm font-medium rounded-full ${statusConfig[atendimento.status]?.color}`}>
                                {statusConfig[atendimento.status]?.label}
                            </span>
                            
                            {/* Alterar Status para Produzir - Main CTA */}
                            {atendimento.status !== 'em_producao' && atendimento.status !== 'finalizado' && atendimento.status !== 'cancelado' && (
                                <button
                                    onClick={handleAlterarParaProducao}
                                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 font-medium"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Alterar Status para Produzir
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Upper Section - 3 Columns */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-6">
                    {/* Acompanhamento + Histórico */}
                    <div className="lg:col-span-9">
                        <AcompanhamentoSection
                            acompanhamentos={atendimento.acompanhamentos || []}
                            onAdd={handleAddAcompanhamento}
                            processing={acompProcessing}
                        />
                    </div>

                    {/* Contato + Status */}
                    <div className="lg:col-span-3 space-y-4">
                        {/* Contato Card */}
                        <div className="bg-white rounded-lg border border-gray-200 p-4">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Contato</h3>
                            <div className="space-y-2">
                                {atendimento.paciente?.telefone1 && (
                                    <a
                                        href={`tel:${atendimento.paciente.telefone1}`}
                                        className="flex items-center gap-2 text-sm text-emerald-600 hover:text-emerald-700"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        {atendimento.paciente.telefone1}
                                    </a>
                                )}
                                {atendimento.paciente?.telefone2 && (
                                    <a
                                        href={`https://wa.me/55${formatPhone(atendimento.paciente.telefone2)}`}
                                        target="_blank"
                                        className="flex items-center gap-2 text-sm text-green-600 hover:text-green-700"
                                    >
                                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        {atendimento.paciente.telefone2}
                                    </a>
                                )}
                            </div>
                        </div>

                        {/* Status Card */}
                        <div className={`bg-white rounded-lg border-2 p-4 ${statusConfig[atendimento.status]?.borderColor || 'border-gray-200'}`}>
                            <h3 className="text-sm font-semibold text-gray-900 mb-2">Status do Atendimento</h3>
                            <div className={`px-3 py-2 rounded-lg text-sm font-medium text-center ${statusConfig[atendimento.status]?.color}`}>
                                {statusConfig[atendimento.status]?.label}
                            </div>
                            <button
                                onClick={() => setShowStatusModal(true)}
                                className="w-full mt-3 px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Alterar Status
                            </button>
                        </div>
                    </div>
                </div>

                {/* Info Section - 2 Columns Compact */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                    {/* Info Paciente */}
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-semibold text-gray-900">Informações do Paciente</h3>
                            <button
                                onClick={() => setPatientDrawerOpen(true)}
                                className="text-xs text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                            >
                                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                Editar
                            </button>
                        </div>
                        <div className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <div>
                                <span className="text-gray-500">Nome:</span>
                                <p className="font-medium text-gray-900">{atendimento.paciente?.nome}</p>
                            </div>
                            <div>
                                <span className="text-gray-500">Médico:</span>
                                <p className="font-medium text-gray-900">{atendimento.medico?.nome || '-'}</p>
                            </div>
                            {atendimento.paciente?.indicado_por && (
                                <div className="col-span-2">
                                    <span className="text-gray-500">Indicado Por:</span>
                                    <p className="font-medium text-gray-900">{atendimento.paciente.indicado_por}</p>
                                </div>
                            )}
                            {atendimento.paciente?.anotacoes && (
                                <div className="col-span-2">
                                    <span className="text-gray-500">Anotações:</span>
                                    <p className="text-gray-700">{atendimento.paciente.anotacoes}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Info Receita */}
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-semibold text-gray-900">Dados da Receita</h3>
                            <Link
                                href={`/receitas/${atendimento.receita?.id}`}
                                className="text-xs text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                            >
                                Ver completa
                                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </Link>
                        </div>
                        <div className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <div>
                                <span className="text-gray-500">Nº Receita:</span>
                                <p className="font-medium text-gray-900">#{atendimento.receita?.id?.toString().padStart(5, '0')}</p>
                            </div>
                            <div>
                                <span className="text-gray-500">Data:</span>
                                <p className="font-medium text-gray-900">
                                    {atendimento.receita?.data_receita 
                                        ? new Date(atendimento.receita.data_receita).toLocaleDateString('pt-BR')
                                        : '-'}
                                </p>
                            </div>
                            {atendimento.receita?.anotacoes && (
                                <div className="col-span-2">
                                    <span className="text-gray-500">Anotações:</span>
                                    <p className="text-gray-700">{atendimento.receita.anotacoes}</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Products Section - Full Width */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <ProductItemsEditor
                        itens={itens}
                        onItensChange={setItens}
                        produtos={produtos}
                        descontoPercentual={descontoPercentual}
                        onDescontoPercentualChange={setDescontoPercentual}
                        descontoMotivo={descontoMotivo}
                        onDescontoMotivoChange={setDescontoMotivo}
                        valorFrete={valorFrete}
                        onValorFreteChange={setValorFrete}
                        valorCaixa={valorCaixa}
                        onValorCaixaChange={setValorCaixa}
                        showPrices={true}
                        showGroups={false}
                        readOnly={atendimento.status === 'finalizado' || atendimento.status === 'cancelado'}
                    />
                </div>
            </div>

            {/* Patient Drawer */}
            <PatientDrawer
                isOpen={patientDrawerOpen}
                onClose={() => setPatientDrawerOpen(false)}
                paciente={atendimento.paciente}
                onSave={() => router.reload({ only: ['atendimento'] })}
                isAdmin={true}
                enableAutoSave={true}
            />

            {/* Status Change Modal */}
            {showStatusModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        <div 
                            className="fixed inset-0 bg-black/50 transition-opacity"
                            onClick={() => setShowStatusModal(false)}
                        />
                        
                        <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 transform transition-all">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Alterar Status</h3>
                            
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Novo Status</label>
                                    <select
                                        value={selectedStatus}
                                        onChange={(e) => setSelectedStatus(e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        {Object.entries(statusOptions || statusConfig).map(([key, config]) => (
                                            <option key={key} value={key}>
                                                {config.label || config}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Observação (opcional)
                                    </label>
                                    <textarea
                                        value={statusAcompanhamento}
                                        onChange={(e) => setStatusAcompanhamento(e.target.value)}
                                        rows={3}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        placeholder="Adicione uma observação sobre a mudança de status..."
                                    />
                                </div>
                            </div>
                            
                            <div className="flex justify-end gap-3 mt-6">
                                <button
                                    type="button"
                                    onClick={() => setShowStatusModal(false)}
                                    className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    onClick={handleStatusChange}
                                    className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                                >
                                    Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
