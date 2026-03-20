import { useRef, useState, useEffect, useCallback } from 'react';

function previewAnotacoesEspecialistas(text) {
    if (!text || typeof text !== 'string') return '';
    return text.replace(/\s+/g, ' ').trim();
}

export default function ProductCombobox({ value, onChange, produtos = [], disabled = false, minChars = 3, className = '' }) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const wrapperRef = useRef(null);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    const selectedProduct = produtos.find(p => p.id === parseInt(value));
    const displayValue = selectedProduct ? `${selectedProduct.codigo} - ${selectedProduct.nome}` : '';

    const meetsMinChars = search.length >= minChars;

    const filtered = meetsMinChars
        ? produtos.filter(p => {
            const term = search.toLowerCase();
            return p.nome.toLowerCase().includes(term) || p.codigo?.toLowerCase().includes(term);
        })
        : [];

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target)) {
                setIsOpen(false);
                setSearch('');
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (isOpen && highlightedIndex >= 0 && listRef.current) {
            const items = listRef.current.children;
            if (items[highlightedIndex]) {
                items[highlightedIndex].scrollIntoView({ block: 'nearest' });
            }
        }
    }, [highlightedIndex, isOpen]);

    const handleKeyDown = useCallback((e) => {
        if (!isOpen) {
            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                setIsOpen(true);
                e.preventDefault();
            }
            return;
        }
        if (e.key === 'ArrowDown') {
            setHighlightedIndex(i => Math.min(i + 1, filtered.length - 1));
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            setHighlightedIndex(i => Math.max(i - 1, 0));
            e.preventDefault();
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            onChange(String(filtered[highlightedIndex].id));
            setIsOpen(false);
            setSearch('');
            e.preventDefault();
        } else if (e.key === 'Escape') {
            setIsOpen(false);
            setSearch('');
        }
    }, [isOpen, filtered, highlightedIndex, onChange]);

    const handleSelect = (productId) => {
        onChange(String(productId));
        setIsOpen(false);
        setSearch('');
    };

    return (
        <div ref={wrapperRef} className={`relative min-w-0 ${className}`}>
            <input
                ref={inputRef}
                type="text"
                value={isOpen ? search : displayValue}
                placeholder="Buscar produto..."
                disabled={disabled}
                onChange={(e) => {
                    setSearch(e.target.value);
                    setHighlightedIndex(-1);
                    if (!isOpen) setIsOpen(true);
                }}
                onFocus={() => {
                    setIsOpen(true);
                    setSearch('');
                }}
                onKeyDown={handleKeyDown}
                className="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
            />
            {value && !isOpen && (
                <button
                    type="button"
                    onClick={() => { onChange(''); setSearch(''); }}
                    disabled={disabled}
                    className="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-0.5"
                >
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
            {isOpen && (
                <div className="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto" ref={listRef}>
                    {!meetsMinChars ? (
                        <div className="px-3 py-2 text-sm text-gray-500">
                            Digite pelo menos {minChars} caracteres...
                        </div>
                    ) : filtered.length === 0 ? (
                        <div className="px-3 py-2 text-sm text-gray-500">Nenhum produto encontrado</div>
                    ) : (
                        filtered.slice(0, 50).map((p, idx) => {
                            const anotacoesPreview = previewAnotacoesEspecialistas(p.anotacoes);
                            return (
                                <div
                                    key={p.id}
                                    onMouseDown={() => handleSelect(p.id)}
                                    onMouseEnter={() => setHighlightedIndex(idx)}
                                    className={`px-3 py-1.5 text-sm cursor-pointer ${
                                        idx === highlightedIndex ? 'bg-emerald-50 text-emerald-700' :
                                        parseInt(value) === p.id ? 'bg-gray-100' : 'hover:bg-gray-50'
                                    }`}
                                >
                                    <div>
                                        <span className="font-medium text-gray-500">{p.codigo}</span> - {p.nome}
                                    </div>
                                    {anotacoesPreview ? (
                                        <div
                                            className={`mt-0.5 text-xs line-clamp-2 ${
                                                idx === highlightedIndex ? 'text-emerald-600/90' : 'text-gray-500'
                                            }`}
                                        >
                                            {anotacoesPreview}
                                        </div>
                                    ) : null}
                                </div>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
}
