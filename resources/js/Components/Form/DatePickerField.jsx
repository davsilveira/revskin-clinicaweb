import { useEffect, useRef, useState } from 'react';
import { DayPicker } from 'react-day-picker';
import { format, isValid, parse } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import 'react-day-picker/style.css';

function parseYmd(value) {
    if (!value || typeof value !== 'string') return undefined;
    const d = parse(value, 'yyyy-MM-dd', new Date());
    return isValid(d) ? d : undefined;
}

/**
 * Data em yyyy-MM-dd (API) com calendário pt-BR e controlo independente do browser.
 */
export default function DatePickerField({
    label,
    value,
    onChange,
    error,
    required = false,
    disabled = false,
    compact = false,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const selected = parseYmd(value);

    useEffect(() => {
        if (!open) return;
        const onDoc = (e) => {
            if (rootRef.current && !rootRef.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [open]);

    const display = selected ? format(selected, 'dd/MM/yyyy', { locale: ptBR }) : '';

    const triggerClass = compact
        ? `min-h-[32px] px-2 py-1 text-sm border rounded text-left w-full max-w-full min-w-0 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed ${
              error ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'
          }`
        : `w-full min-h-[44px] px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-left focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 disabled:bg-gray-100 disabled:cursor-not-allowed ${
              error ? 'border-red-400 bg-red-50' : ''
          }`;

    return (
        <div className={`relative ${className}`} ref={rootRef}>
            {label ? (
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    {label}
                    {required && <span className="text-red-500 ml-1">*</span>}
                </label>
            ) : null}
            <button
                type="button"
                disabled={disabled}
                onClick={() => !disabled && setOpen((o) => !o)}
                className={triggerClass}
            >
                {display || <span className="text-gray-400">Selecione a data…</span>}
            </button>
            {open && !disabled && (
                <div className="absolute z-[100] mt-1 rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
                    <DayPicker
                        mode="single"
                        selected={selected}
                        onSelect={(d) => {
                            if (d) {
                                onChange(format(d, 'yyyy-MM-dd'));
                            }
                            setOpen(false);
                        }}
                        locale={ptBR}
                        defaultMonth={selected}
                    />
                </div>
            )}
            {error ? <p className="mt-1.5 text-sm text-red-600">{error}</p> : null}
        </div>
    );
}
