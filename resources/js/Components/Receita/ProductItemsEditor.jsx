import { useRef } from 'react';

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
        newItens[index] = { ...newItens[index], [field]: value };

        // Se mudou o produto, atualiza o preco e local_uso padrao
        if (field === 'produto_id') {
            const produto = produtos.find(p => p.id === parseInt(value));
            if (produto) {
                newItens[index].valor_unitario = parseFloat(produto.preco_venda) || parseFloat(produto.preco) || 0;
                newItens[index].local_uso = produto.local_uso || '';
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
        return subtotal - desconto + frete;
    };

    // Renderizar linha de item
    const renderItemRow = (item, index, isLastItem) => (
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
            
            {/* Local de Uso */}
            <div className="w-36 flex-shrink-0" title={item.local_uso || '-'}>
                <span className="text-xs text-gray-600 bg-gray-100 px-2 py-0.5 rounded block truncate">
                    {formatLocalUso(item.local_uso)}
                </span>
            </div>
            
            {/* Produto Select */}
            <select
                value={item.produto_id}
                onChange={(e) => updateItem(index, 'produto_id', e.target.value)}
                disabled={readOnly}
                className="flex-[2] min-w-0 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
            >
                <option value="">Produto...</option>
                {produtos?.map((p) => (
                    <option key={p.id} value={p.id}>{p.codigo} - {p.nome}</option>
                ))}
            </select>
            
            {/* Anotações */}
            <input
                type="text"
                placeholder="Anotações..."
                value={item.anotacoes || ''}
                onChange={(e) => updateItem(index, 'anotacoes', e.target.value)}
                disabled={readOnly}
                className="flex-1 min-w-0 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-emerald-500 bg-gray-50"
            />
            
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

                        {/* Produtos Opcionais */}
                        <div>
                            <div className="flex items-center gap-2 mb-2 pb-1 border-b border-gray-300">
                                <div className="w-2.5 h-2.5 bg-gray-400 rounded-full"></div>
                                <span className="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    Opcionais
                                </span>
                                <span className="text-xs text-gray-500">
                                    ({itens.filter(i => i.grupo === 'opcional' && i.imprimir).length})
                                </span>
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
                                <label className="text-sm font-medium text-gray-700">Caixa:</label>
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
