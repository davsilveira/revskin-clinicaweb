import { useLayoutEffect, useRef } from 'react';

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
    ...props
}) {
    const textareaRef = useRef(null);

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

    const inputProps = {
        value: value || '',
        onChange,
        placeholder,
        disabled,
        maxLength,
        className: `w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 ${
            error ? 'border-red-400 bg-red-50' : ''
        } ${disabled ? 'bg-gray-100 cursor-not-allowed opacity-60' : ''} ${multilineClass}`,
        ...props,
    };

    return (
        <div className={className}>
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    {label}
                    {required && <span className="text-red-500 ml-1">*</span>}
                </label>
            )}
            {multiline ? (
                <textarea ref={textareaRef} {...inputProps} rows={rows} />
            ) : (
                <input type={type} {...inputProps} />
            )}
            {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
        </div>
    );
}
