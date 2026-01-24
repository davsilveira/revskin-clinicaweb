import { Link, router } from '@inertiajs/react';
import { useState, useCallback, useEffect, useRef } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import ProductItemsEditor from '@/Components/Receita/ProductItemsEditor';
import AcompanhamentoSection from '@/Components/CallCenter/AcompanhamentoSection';
import PatientDrawer from '@/Components/PatientDrawer';
import useAutoSave from '@/hooks/useAutoSave';

export default function CallCenterShow({ atendimento, statusOptions, produtos }) {
    const isFirstRender = useRef(true);
    const [showProducaoModal, setShowProducaoModal] = useState(false);
    const [producaoAcompanhamento, setProducaoAcompanhamento] = useState('');
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

    const handleEnviarParaProducao = () => {
        router.put(`/callcenter/${atendimento.id}/status`, {
            status: 'em_producao',
            acompanhamento: producaoAcompanhamento,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowProducaoModal(false);
                setProducaoAcompanhamento('');
            },
        });
    };

    const formatPhone = (phone) => {
        if (!phone) return null;
        return phone.replace(/\D/g, '');
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('pt-BR');
    };

    const canSendToProduction = atendimento.status !== 'em_producao' && 
                                 atendimento.status !== 'finalizado' && 
                                 atendimento.status !== 'cancelado';

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
                                <div className="flex items-center gap-2 text-sm text-gray-500">
                                    <Link 
                                        href={`/receitas/${atendimento.receita?.id}`}
                                        className="hover:text-emerald-600 flex items-center gap-1"
                                    >
                                        Receita #{atendimento.receita?.id?.toString().padStart(5, '0')}
                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </Link>
                                    <span>|</span>
                                    <span>{formatDate(atendimento.receita?.data_receita)}</span>
                                </div>
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
                            
                            {/* Alterar Status para Produzir - Main CTA */}
                            {canSendToProduction && (
                                <button
                                    onClick={() => setShowProducaoModal(true)}
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

                {/* Upper Section - 2 Columns */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-6">
                    {/* Acompanhamento + Histórico */}
                    <div className="lg:col-span-9">
                        <AcompanhamentoSection
                            acompanhamentos={atendimento.acompanhamentos || []}
                            onAdd={handleAddAcompanhamento}
                            processing={acompProcessing}
                        />
                    </div>

                    {/* Info Paciente + Contato */}
                    <div className="lg:col-span-3">
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
                            
                            <div className="space-y-3 text-sm">
                                <div>
                                    <span className="text-gray-500">Nome:</span>
                                    <p className="font-medium text-gray-900">{atendimento.paciente?.nome}</p>
                                </div>
                                <div>
                                    <span className="text-gray-500">Médico:</span>
                                    <p className="font-medium text-gray-900">{atendimento.medico?.nome || '-'}</p>
                                </div>
                                
                                {/* Telefones */}
                                <div className="pt-2 border-t border-gray-100">
                                    <span className="text-gray-500">Contato:</span>
                                    <div className="mt-1 space-y-1">
                                        {atendimento.paciente?.telefone1 && (
                                            <a
                                                href={`tel:${atendimento.paciente.telefone1}`}
                                                className="flex items-center gap-2 text-emerald-600 hover:text-emerald-700"
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
                                                className="flex items-center gap-2 text-green-600 hover:text-green-700"
                                            >
                                                <svg className="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                </svg>
                                                {atendimento.paciente.telefone2}
                                            </a>
                                        )}
                                        {atendimento.paciente?.email1 && (
                                            <a
                                                href={`mailto:${atendimento.paciente.email1}`}
                                                className="flex items-center gap-2 text-gray-600 hover:text-gray-700"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                                <span className="truncate">{atendimento.paciente.email1}</span>
                                            </a>
                                        )}
                                    </div>
                                </div>

                                {atendimento.paciente?.anotacoes && (
                                    <div className="pt-2 border-t border-gray-100">
                                        <span className="text-gray-500">Anotações:</span>
                                        <p className="text-gray-700 mt-1">{atendimento.paciente.anotacoes}</p>
                                    </div>
                                )}
                            </div>
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
                        showGroups={true}
                        readOnly={['em_producao', 'finalizado', 'cancelado'].includes(atendimento.status)}
                    />
                </div>
            </div>

            {/* Patient Drawer */}
            <PatientDrawer
                isOpen={patientDrawerOpen}
                onClose={() => setPatientDrawerOpen(false)}
                paciente={atendimento.paciente}
                onSave={() => {
                    setPatientDrawerOpen(false);
                    router.reload({ only: ['atendimento'] });
                }}
                isAdmin={true}
                enableAutoSave={false}
            />

            {/* Modal de Confirmação - Enviar para Produção */}
            {showProducaoModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        {/* Backdrop */}
                        <div 
                            className="fixed inset-0 bg-black/50 transition-opacity"
                            onClick={() => setShowProducaoModal(false)}
                        />
                        
                        {/* Modal */}
                        <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900">Enviar para Produção</h3>
                            </div>
                            
                            <p className="text-gray-600 mb-4">
                                Deseja enviar este pedido para produção? Após confirmado, o status será alterado para "Em Produção".
                            </p>

                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Observação (opcional)
                                </label>
                                <textarea
                                    value={producaoAcompanhamento}
                                    onChange={(e) => setProducaoAcompanhamento(e.target.value)}
                                    rows={3}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Adicione uma observação sobre o envio para produção..."
                                />
                            </div>
                            
                            <div className="flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setShowProducaoModal(false)}
                                    className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    onClick={handleEnviarParaProducao}
                                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
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
