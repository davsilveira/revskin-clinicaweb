import { useId, useLayoutEffect, useRef, useState } from 'react';

function EyeIcon({ crossed = false }) {
    if (crossed) {
        return (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
        );
    }

    return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
    );
}

export default function Input({
    type = 'text',
    value,
    onChange,
    label,
    error,
    required = false,
    placeholder = '',
    disabled = false,
    maxLength,
    className = '',
    multiline = false,
    rows = 3,
    autoGrow = false,
    /** Mostra um spinner dentro do campo (ex.: busca em andamento enquanto se digita). */
    loading = false,
    id,
    ...props
}) {
    const textareaRef = useRef(null);
    const [revealed, setRevealed] = useState(false);
    const autoId = useId();
    const inputId = id || autoId;

    const isPassword = type === 'password';
    const showRevealToggle = isPassword && !disabled && !multiline;
    const inputType = showRevealToggle && revealed ? 'text' : type;

    useLayoutEffect(() => {
        if (!multiline || !autoGrow) {
            return;
        }
        const el = textareaRef.current;
        if (!el) {
            return;
        }
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    }, [value, multiline, autoGrow, rows]);

    const multilineClass = multiline
        ? autoGrow
            ? 'min-h-[100px] resize-none overflow-hidden'
            : 'min-h-[100px] resize-y'
        : 'h-[44px]';

    const hasRightAddon = !multiline && (loading || showRevealToggle);

    const inputProps = {
        value: value || '',
        onChange,
        placeholder,
        disabled,
        maxLength,
        className: `w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 ${
            error ? 'border-red-400 bg-red-50' : ''
        } ${disabled ? 'bg-gray-100 cursor-not-allowed opacity-60' : ''} ${hasRightAddon ? 'pr-12' : ''} ${multilineClass}`,
        ...props,
        id: inputId,
    };

    return (
        <div className={className}>
            {label && (
                <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-2">
                    {label}
                    {required && <span className="text-red-500 ml-1">*</span>}
                </label>
            )}
            {multiline ? (
                <textarea ref={textareaRef} {...inputProps} rows={rows} />
            ) : (
                <div className="relative">
                    <input type={inputType} {...inputProps} />
                    {loading && (
                        <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                        </span>
                    )}
                    {showRevealToggle && !loading && (
                        <button
                            type="button"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => setRevealed((v) => !v)}
                            className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors"
                            aria-label={revealed ? 'Ocultar senha' : 'Mostrar senha'}
                            aria-pressed={revealed}
                        >
                            <EyeIcon crossed={revealed} />
                        </button>
                    )}
                </div>
            )}
            {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
        </div>
    );
}
