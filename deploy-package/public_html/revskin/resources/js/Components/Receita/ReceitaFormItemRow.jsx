import Tippy from '@tippyjs/react';
import ProductCombobox from '@/Components/Receita/ProductCombobox';

const tippyAquisicaoProps = {
    appendTo: () => document.body,
    popperOptions: { strategy: 'fixed' },
    zIndex: 9999,
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('pt-BR');
};

/**
 * Linha de item da receita: layout empilhado abaixo de lg, linha horizontal em lg+.
 */
export default function ReceitaFormItemRow({
    item,
    index,
    variant,
    produtos,
    isReadOnly,
    isMedico,
    ultimaAquisicao,
    datasAquisicao,
    temHistorico,
    onUpdateItem,
    onRemoveItem,
    isLastItem,
    lastItemRef,
    formatItemTotal,
}) {
    const rowTone =
        item.vendido
            ? 'bg-green-50 border border-green-200'
            : item.imprimir
              ? variant === 'recomendado'
                  ? 'hover:bg-emerald-50/50'
                  : 'hover:bg-gray-50'
              : 'bg-gray-50';

    return (
        <div
            ref={isLastItem ? lastItemRef : null}
            className={`flex flex-col gap-1 py-2 px-2 rounded transition-colors lg:flex-row lg:items-center lg:gap-2 lg:py-1.5 ${rowTone}`}
        >
            {/* Mobile: checkbox + remover */}
            <div className="flex items-center justify-between gap-2 lg:hidden">
                <div className="flex items-center gap-2 min-w-0">
                    {item.vendido && (
                        <svg className="w-4 h-4 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                    <input
                        type="checkbox"
                        checked={item.imprimir}
                        onChange={(e) => onUpdateItem(index, 'imprimir', e.target.checked)}
                        disabled={isReadOnly}
                        className="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 flex-shrink-0"
                    />
                </div>
                {!isReadOnly && (
                    <button
                        type="button"
                        onClick={() => onRemoveItem(index)}
                        className="flex-shrink-0 p-1 text-red-500 hover:bg-red-50 rounded"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                )}
            </div>

            {/* Desktop: checkbox + vendido */}
            <div className="hidden lg:flex items-center gap-2 flex-shrink-0">
                {item.vendido && (
                    <svg className="w-4 h-4 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                )}
                <input
                    type="checkbox"
                    checked={item.imprimir}
                    onChange={(e) => onUpdateItem(index, 'imprimir', e.target.checked)}
                    disabled={isReadOnly}
                    className="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 flex-shrink-0"
                />
            </div>

            <div className="w-full min-w-0 lg:flex-[3]">
                <span className="text-xs font-medium text-gray-500 lg:hidden block mb-0.5">Produto</span>
                <ProductCombobox
                    value={item.produto_id}
                    onChange={(val) => onUpdateItem(index, 'produto_id', val)}
                    produtos={produtos}
                    disabled={isReadOnly}
                    minChars={3}
                    className="w-full min-w-0"
                />
            </div>

            <div className="w-full min-w-0 lg:flex-[2]">
                <span className="text-xs font-medium text-gray-500 lg:hidden block mb-1">Anotações</span>
                <input
                    type="text"
                    placeholder="Anotações..."
                    value={item.anotacoes || ''}
                    onChange={(e) => onUpdateItem(index, 'anotacoes', e.target.value)}
                    disabled={isReadOnly}
                    className="w-full min-w-0 px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-emerald-500 bg-gray-50"
                />
            </div>

            <div className="w-full grid grid-cols-2 gap-x-3 gap-y-1 sm:flex sm:flex-row sm:flex-wrap sm:items-end sm:gap-2 lg:contents">
                <div className="col-span-2 w-full sm:flex-1 sm:min-w-[8rem] lg:w-36 lg:flex-shrink-0 lg:flex lg:items-center lg:justify-center">
                    <span className="text-xs font-medium text-gray-500 lg:hidden block mb-0.5">Data aquisição</span>
                    <div className="flex items-center justify-start sm:justify-center gap-1.5 lg:justify-center w-full">
                        {ultimaAquisicao && ultimaAquisicao !== '-' ? (
                            <div className="flex items-center gap-1.5">
                                {temHistorico && datasAquisicao.length > 1 ? (
                                    <Tippy
                                        content={
                                            <div className="text-xs py-1">
                                                <div className="font-medium mb-2 text-gray-900">Últimas aquisições</div>
                                                <div className="space-y-1">
                                                    {datasAquisicao.map((data, idx) => (
                                                        <div key={idx} className="text-gray-700">
                                                            {formatDate(data)}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        }
                                        placement="top"
                                        interactive={true}
                                        theme="light-border"
                                        {...tippyAquisicaoProps}
                                    >
                                        <span className="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-md inline-flex items-center gap-1.5 cursor-help hover:bg-gray-200 transition-colors">
                                            <span>{formatDate(ultimaAquisicao)}</span>
                                            <span className="px-1 py-0 bg-gray-200 text-gray-600 text-[10px] font-medium rounded leading-none">
                                                +{datasAquisicao.length - 1}
                                            </span>
                                        </span>
                                    </Tippy>
                                ) : (
                                    <Tippy
                                        content={
                                            <div className="text-xs text-gray-700">{formatDate(ultimaAquisicao)}</div>
                                        }
                                        placement="top"
                                        theme="light-border"
                                        {...tippyAquisicaoProps}
                                    >
                                        <span className="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-md inline-flex items-center gap-1 cursor-help hover:bg-gray-200 transition-colors">
                                            <span>{formatDate(ultimaAquisicao)}</span>
                                        </span>
                                    </Tippy>
                                )}
                            </div>
                        ) : (
                            <span className="text-xs text-gray-400">—</span>
                        )}
                    </div>
                </div>

                <div className="w-full sm:w-auto lg:w-14 lg:flex-shrink-0">
                    <span className="text-xs font-medium text-gray-500 lg:hidden block mb-0.5">Qtd</span>
                    <input
                        type="number"
                        min="1"
                        value={item.quantidade}
                        onChange={(e) => onUpdateItem(index, 'quantidade', parseInt(e.target.value, 10) || 1)}
                        disabled={isReadOnly || !item.imprimir}
                        className={`w-full sm:w-14 px-1 py-1 border border-gray-300 rounded text-sm text-center focus:ring-1 focus:ring-emerald-500 lg:w-14 flex-shrink-0 ${!item.imprimir ? 'bg-gray-100 text-gray-400' : ''}`}
                    />
                </div>

                {!isMedico && (
                    <div className="w-full sm:flex-1 sm:text-right lg:w-20 lg:flex-shrink-0 lg:text-right">
                        <span className="text-xs font-medium text-gray-500 lg:hidden block mb-0.5">Total</span>
                        <span
                            className={`block text-sm font-medium lg:text-right ${item.imprimir ? 'text-gray-900' : 'text-gray-400'}`}
                        >
                            {formatItemTotal(item)}
                        </span>
                    </div>
                )}
            </div>

            {!isReadOnly && (
                <button
                    type="button"
                    onClick={() => onRemoveItem(index)}
                    className="hidden lg:inline-flex flex-shrink-0 p-1 text-red-500 hover:bg-red-50 rounded self-center"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            )}
        </div>
    );
}
