import { useRef, useState, useEffect, useCallback } from 'react';
import Tippy from '@tippyjs/react';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/themes/light-border.css';
import ProductCombobox from './ProductCombobox';

// Mapeamento de local_uso para nomes mais descritivos
const localUsoLabels = {
    'face': 'Creme Facial',
    'rosto': 'Creme Facial',
    'olhos': 'Creme dos Olhos',
    'corpo': 'Creme Corpo',
    'maos': 'Creme Mãos',
    'pes': 'Creme Pés',
    'cabelo': 'Capilar',
    'solar': 'Protetor Solar',
    'limpeza': 'Limpeza',
    'serum': 'Sérum',
    'mascara': 'Máscara',
    'tonalite': 'Base Tonalité',
};

const formatLocalUso = (localUso) => {
    if (!localUso) return '-';
    // Se já tem um nome descritivo (mais de uma palavra ou começa com maiúscula), usar como está
    if (localUso.includes(' ') || /^[A-Z]/.test(localUso)) {
        return localUso;
    }
    // Caso contrário, tentar mapear
    const key = localUso.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return localUsoLabels[key] || localUso;
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('pt-BR');
};

/**
 * ProductItemsEditor - Componente reutilizável para edição de produtos em receitas
 * 
 * Props:
 * - itens: array de itens [{produto_id, local_uso, anotacoes, quantidade, valor_unitario, imprimir, grupo}]
 * - onItensChange: callback quando itens mudam
 * - produtos: lista de produtos disponíveis
 * - descontoPercentual: percentual de desconto
 * - onDescontoPercentualChange: callback para mudança de desconto
 * - descontoMotivo: motivo do desconto
 * - onDescontoMotivoChange: callback para mudança de motivo
 * - valorFrete: valor do frete
 * - onValorFreteChange: callback para mudança de frete
 * - valorCaixa: valor da caixa
 * - onValorCaixaChange: callback para mudança de caixa
 * - showPrices: mostrar colunas de preço (false para médicos)
 * - showGroups: mostrar separação por grupos (recomendado/opcional)
 * - readOnly: desabilitar edição
 * - compact: layout compacto para Call Center
 * - errors: objeto de erros de validação
 * - itensComAquisicoes: objeto mapeando item_id para dados de aquisição {ultima_aquisicao, datas_aquisicao}
 * - itensOriginais: array opcional com os itens originais carregados do backend (para buscar dados de aquisição)
 */
export default function ProductItemsEditor({
    itens = [],
    onItensChange,
    produtos = [],
    descontoPercentual = 0,
    onDescontoPercentualChange,
    descontoMotivo = '',
    onDescontoMotivoChange,
    valorFrete = 0,
    onValorFreteChange,
    valorCaixa = 0,
    onValorCaixaChange,
    showPrices = true,
    showGroups = true,
    readOnly = false,
    compact = false,
    errors = {},
    itensComAquisicoes = {},
    itensOriginais = [],
}) {
    const lastItemRef = useRef(null);

    // Funções de manipulação de itens
    const addItem = () => {
        const newItens = [
            ...itens,
            {
                produto_id: '',
                local_uso: '',
                anotacoes: '',
                quantidade: 1,
                valor_unitario: 0,
                imprimir: true,
                grupo: 'recomendado',
            },
        ];
        onItensChange(newItens);
        // Scroll para o novo item após o DOM atualizar
        setTimeout(() => {
            lastItemRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    };

    const removeItem = (index) => {
        const newItens = [...itens];
        newItens.splice(index, 1);
        onItensChange(newItens);
    };

    const updateItem = (index, field, value) => {
        const newItens = [...itens];
        const currentItem = newItens[index];
        newItens[index] = { ...currentItem, [field]: value };

        // Se mudou o produto, atualiza o preco e local_uso padrao
        if (field === 'produto_id') {
            const produto = produtos.find(p => p.id === parseInt(value));
            if (produto) {
                newItens[index].valor_unitario = parseFloat(produto.preco_venda) || parseFloat(produto.preco) || 0;
                newItens[index].local_uso = produto.local_uso || '';
                newItens[index].ultima_aquisicao = null;
                newItens[index].datas_aquisicao = [];
            }
        }

        // Se marcou o checkbox (imprimir) e tem produto_id mas não tem dados de aquisição: só mesma linha (id + produto)
        if (field === 'imprimir' && value === true && currentItem.produto_id && !currentItem.ultima_aquisicao) {
            const itemId = currentItem.id;
            if (itemId && itensComAquisicoes[itemId]) {
                const dadosAquisicao = itensComAquisicoes[itemId];
                newItens[index].ultima_aquisicao = dadosAquisicao.ultima_aquisicao;
                newItens[index].datas_aquisicao = dadosAquisicao.datas_aquisicao || [];
            } else if (itensOriginais.length > 0) {
                const itemOriginal = itensOriginais.find(
                    (i) =>
                        i.id === currentItem.id &&
                        String(i.produto_id) === String(currentItem.produto_id) &&
                        (i.ultima_aquisicao || i.datas_aquisicao?.length > 0)
                );
                if (itemOriginal) {
                    newItens[index].ultima_aquisicao = itemOriginal.ultima_aquisicao;
                    newItens[index].datas_aquisicao = itemOriginal.datas_aquisicao || [];
                }
            }
        }

        onItensChange(newItens);
    };

    // Funções de cálculo
    const calcularSubtotalItem = (item) => {
        return item.quantidade * item.valor_unitario;
    };

    const calcularSubtotal = () => {
        return itens
            .filter(item => item.imprimir)
            .reduce((total, item) => total + calcularSubtotalItem(item), 0);
    };

    const calcularDesconto = () => {
        const subtotal = calcularSubtotal();
        return subtotal * (descontoPercentual / 100);
    };

    const calcularTotal = () => {
        const subtotal = calcularSubtotal();
        const desconto = calcularDesconto();
        const frete = parseFloat(valorFrete) || 0;
        const caixa = parseFloat(valorCaixa) || 0;
        return subtotal - desconto + frete + caixa;
    };

    // Renderizar linha de item
    const renderItemRow = (item, index, isLastItem) => {
        // Buscar dados de aquisição para este item
        const aquisicaoData = item.id ? itensComAquisicoes[item.id] : null;
        const ultimaAquisicao = aquisicaoData?.ultima_aquisicao || item.ultima_aquisicao;
        const datasAquisicao = aquisicaoData?.datas_aquisicao || item.datas_aquisicao || [];
        const temHistorico = datasAquisicao.length > 1;

        return (
            <div 
                key={index} 
                ref={isLastItem ? lastItemRef : null}
                className={`flex items-center gap-2 py-1.5 px-2 rounded transition-colors ${
                    item.imprimir 
                        ? (item.grupo === 'opcional' ? 'hover:bg-gray-50' : 'hover:bg-emerald-50/50') 
                        : 'bg-gray-50 opacity-50'
                }`}
            >
                {/* Checkbox */}
                <input
                    type="checkbox"
                    checked={item.imprimir}
                    onChange={(e) => updateItem(index, 'imprimir', e.target.checked)}
                    disabled={readOnly}
                    className="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 flex-shrink-0"
                />
                
                {/* Produto Combobox */}
                <ProductCombobox
                    value={item.produto_id}
                    onChange={(val) => updateItem(index, 'produto_id', val)}
                    produtos={produtos}
                    disabled={readOnly}
                    className="flex-[3]"
                />
                
                {/* Anotações */}
                <input
                    type="text"
                    placeholder="Anotações..."
                    value={item.anotacoes || ''}
                    onChange={(e) => updateItem(index, 'anotacoes', e.target.value)}
                    disabled={readOnly}
                    className="flex-[2] min-w-0 px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-emerald-500 bg-gray-50"
                />
                
                {/* Data de Aquisição */}
                <div className="w-36 flex-shrink-0 flex items-center justify-center gap-1.5">
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
                                >
                                    <span className="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-md flex items-center gap-1.5 cursor-help hover:bg-gray-200 transition-colors">
                                        <span>{formatDate(ultimaAquisicao)}</span>
                                        <span className="px-1 py-0 bg-gray-200 text-gray-600 text-[10px] font-medium rounded leading-none">
                                            +{datasAquisicao.length - 1}
                                        </span>
                                    </span>
                                </Tippy>
                            ) : (
                                <Tippy
                                    content={
                                        <div className="text-xs text-gray-700">
                                            {formatDate(ultimaAquisicao)}
                                        </div>
                                    }
                                    placement="top"
                                    theme="light-border"
                                >
                                    <span className="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-md flex items-center gap-1 cursor-help hover:bg-gray-200 transition-colors">
                                        <span>{formatDate(ultimaAquisicao)}</span>
                                    </span>
                                </Tippy>
                            )}
                        </div>
                    ) : (
                        <span className="text-xs text-gray-400">—</span>
                    )}
                </div>
                
                {/* Quantidade */}
                <input
                    type="number"
                    min="1"
                    value={item.quantidade}
                    onChange={(e) => updateItem(index, 'quantidade', parseInt(e.target.value) || 1)}
                    disabled={readOnly || !item.imprimir}
                    className={`w-14 flex-shrink-0 px-1 py-1 border border-gray-300 rounded text-sm text-center focus:ring-1 focus:ring-emerald-500 ${!item.imprimir ? 'bg-gray-100 text-gray-400' : ''}`}
                />
                
                {/* Valor/Subtotal */}
                {showPrices && (
                    <span className={`w-20 flex-shrink-0 text-right text-sm font-medium ${item.imprimir ? 'text-gray-900' : 'text-gray-400'}`}>
                        {item.imprimir ? formatCurrency(calcularSubtotalItem(item)) : '-'}
                    </span>
                )}
                
                {/* Remover */}
                {!readOnly && (
                    <button 
                        type="button" 
                        onClick={() => removeItem(index)} 
                        className="flex-shrink-0 p-1 text-red-500 hover:bg-red-50 rounded"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                )}
            </div>
        );
    };

    const itensRecomendados = itens.filter(item => item.grupo === 'recomendado');
    const itensOpcionais = itens.filter(item => item.grupo === 'opcional');

    return (
        <div className="space-y-4">
            {/* Header com Desconto */}
            {showPrices && (
                <div className="flex justify-between items-center">
                    <h2 className="text-base font-semibold text-gray-900">Produtos</h2>
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <label className="text-sm text-gray-600">Desconto:</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                value={descontoPercentual}
                                onChange={(e) => onDescontoPercentualChange?.(parseFloat(e.target.value) || 0)}
                                disabled={readOnly}
                                className="w-16 px-2 py-1 border border-gray-300 rounded text-sm text-center focus:ring-1 focus:ring-emerald-500"
                            />
                            <span className="text-sm text-gray-600">%</span>
                        </div>
                        {descontoPercentual > 0 && (
                            <div className="flex items-center gap-2">
                                <label className="text-sm text-gray-600">Motivo:</label>
                                <input
                                    type="text"
                                    value={descontoMotivo}
                                    onChange={(e) => onDescontoMotivoChange?.(e.target.value)}
                                    disabled={readOnly}
                                    placeholder="Motivo do desconto"
                                    className="w-40 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-emerald-500"
                                />
                            </div>
                        )}
                    </div>
                </div>
            )}

            {errors.itens && (
                <div className="p-2 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                    {errors.itens}
                </div>
            )}

            {/* Lista de Produtos */}
            <div className="space-y-4">
                {showGroups ? (
                    <>
                        {/* Produtos Recomendados */}
                        <div>
                            <div className="flex items-center gap-2 mb-2 pb-1 border-b border-emerald-200">
                                <div className="w-2.5 h-2.5 bg-emerald-500 rounded-full"></div>
                                <span className="text-xs font-semibold text-emerald-700 uppercase tracking-wide">
                                    Recomendados para o Tratamento
                                </span>
                                <span className="text-xs text-gray-500">
                                    ({itens.filter(i => i.grupo === 'recomendado' && i.imprimir).length})
                                </span>
                            </div>
                            {/* Cabeçalhos da tabela */}
                            <div className="flex items-center gap-2 py-2 px-2 border-b border-gray-200 mb-1">
                                <div className="w-4 flex-shrink-0"></div>
                                <div className="flex-[3] min-w-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase">Produto</span>
                                </div>
                                <div className="flex-[2] min-w-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase">Anotações</span>
                                </div>
                                <div className="w-36 flex-shrink-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase text-center w-full block">Data Aquisição</span>
                                </div>
                                <div className="w-14 flex-shrink-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase">Qtd</span>
                                </div>
                                {showPrices && (
                                    <div className="w-20 flex-shrink-0 text-right">
                                        <span className="text-xs font-semibold text-gray-600 uppercase">Total</span>
                                    </div>
                                )}
                                {!readOnly && <div className="w-8 flex-shrink-0"></div>}
                            </div>
                            <div className="space-y-1">
                                {itens.map((item, index) => 
                                    item.grupo === 'recomendado' && renderItemRow(item, index, index === itens.length - 1)
                                )}
                            </div>
                            
                            {/* Botão Adicionar Produto - Após Recomendados */}
                            {!readOnly && (
                                <button
                                    type="button"
                                    onClick={addItem}
                                    className="w-full mt-2 px-3 py-2 border border-dashed border-emerald-300 text-emerald-600 rounded hover:bg-emerald-50 hover:border-emerald-400 transition-colors flex items-center justify-center gap-2 text-sm"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                    </svg>
                                    Adicionar Produto
                                </button>
                            )}
                        </div>

                        {/* Produtos Complementares */}
                        <div>
                            <div className="flex items-center gap-2 mb-2 pb-1 border-b border-gray-300">
                                <div className="w-2.5 h-2.5 bg-gray-400 rounded-full"></div>
                                <span className="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    Complementares
                                </span>
                                <span className="text-xs text-gray-500">
                                    ({itens.filter(i => i.grupo === 'opcional' && i.imprimir).length})
                                </span>
                            </div>
                            {/* Cabeçalhos da tabela */}
                            <div className="flex items-center gap-2 py-2 px-2 border-b border-gray-200 mb-1">
                                <div className="w-4 flex-shrink-0"></div>
                                <div className="flex-[3] min-w-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase">Produto</span>
                                </div>
                                <div className="flex-[2] min-w-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase">Anotações</span>
                                </div>
                                <div className="w-36 flex-shrink-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase text-center w-full block">Data Aquisição</span>
                                </div>
                                <div className="w-14 flex-shrink-0">
                                    <span className="text-xs font-semibold text-gray-600 uppercase">Qtd</span>
                                </div>
                                {showPrices && (
                                    <div className="w-20 flex-shrink-0 text-right">
                                        <span className="text-xs font-semibold text-gray-600 uppercase">Total</span>
                                    </div>
                                )}
                                {!readOnly && <div className="w-8 flex-shrink-0"></div>}
                            </div>
                            <div className="space-y-1">
                                {itens.map((item, index) => 
                                    item.grupo === 'opcional' && renderItemRow(item, index, index === itens.length - 1)
                                )}
                            </div>
                        </div>
                    </>
                ) : (
                    // Sem grupos - lista simples
                    <>
                        {/* Cabeçalhos da tabela */}
                        <div className="flex items-center gap-2 py-2 px-2 border-b border-gray-200 mb-1">
                            <div className="w-4 flex-shrink-0"></div>
                            <div className="flex-[3] min-w-0">
                                <span className="text-xs font-semibold text-gray-600 uppercase">Produto</span>
                            </div>
                            <div className="flex-[2] min-w-0">
                                <span className="text-xs font-semibold text-gray-600 uppercase">Anotações</span>
                            </div>
                            <div className="w-36 flex-shrink-0">
                                <span className="text-xs font-semibold text-gray-600 uppercase text-center w-full block">Data Aquisição</span>
                            </div>
                            <div className="w-14 flex-shrink-0">
                                <span className="text-xs font-semibold text-gray-600 uppercase">Qtd</span>
                            </div>
                            {showPrices && (
                                <div className="w-20 flex-shrink-0 text-right">
                                    <span className="text-xs font-semibold text-gray-600 uppercase">Total</span>
                                </div>
                            )}
                            {!readOnly && <div className="w-8 flex-shrink-0"></div>}
                        </div>
                        <div className="space-y-1">
                            {itens.map((item, index) => renderItemRow(item, index, index === itens.length - 1))}
                        </div>
                        
                        {/* Botão Adicionar Produto */}
                        {!readOnly && (
                            <button
                                type="button"
                                onClick={addItem}
                                className="w-full px-3 py-2 border border-dashed border-emerald-300 text-emerald-600 rounded hover:bg-emerald-50 hover:border-emerald-400 transition-colors flex items-center justify-center gap-2 text-sm"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Adicionar Produto
                            </button>
                        )}
                    </>
                )}
            </div>

            {/* Totais */}
            {itens.length > 0 && showPrices && (
                <div className="mt-6 pt-6 border-t border-gray-200">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Caixa e Frete */}
                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-gray-700">Embalagem:</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={valorCaixa}
                                    onChange={(e) => onValorCaixaChange?.(parseFloat(e.target.value) || 0)}
                                    disabled={readOnly}
                                    className="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-gray-700">Frete:</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={valorFrete}
                                    onChange={(e) => onValorFreteChange?.(parseFloat(e.target.value) || 0)}
                                    disabled={readOnly}
                                    className="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500"
                                />
                            </div>
                        </div>

                        {/* Resumo */}
                        <div className="bg-gray-50 rounded-lg p-4">
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">SUBTOTAL:</span>
                                    <span className="font-medium">{formatCurrency(calcularSubtotal())}</span>
                                </div>
                                {descontoPercentual > 0 && (
                                    <div className="flex justify-between text-sm text-red-600">
                                        <span>Valor Descontos ({descontoPercentual}%):</span>
                                        <span>- {formatCurrency(calcularDesconto())}</span>
                                    </div>
                                )}
                                {valorCaixa > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Embalagem:</span>
                                        <span>+ {formatCurrency(valorCaixa)}</span>
                                    </div>
                                )}
                                {valorFrete > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Total Frete:</span>
                                        <span>+ {formatCurrency(valorFrete)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between pt-2 border-t border-gray-300">
                                    <span className="font-semibold text-gray-900">VALOR TOTAL:</span>
                                    <span className="text-xl font-bold text-emerald-600">
                                        {formatCurrency(calcularTotal())}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// Export helper functions for external use
export { formatLocalUso, formatCurrency };
