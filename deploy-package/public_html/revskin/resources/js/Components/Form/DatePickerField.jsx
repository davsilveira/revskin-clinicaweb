import { useEffect, useRef, useState, useCallback } from 'react';
import { DayPicker } from 'react-day-picker';
import { IMaskInput } from 'react-imask';
import { format, isValid, parse } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import 'react-day-picker/style.css';

function parseYmd(value) {
    if (!value || typeof value !== 'string') return undefined;
    const d = parse(value, 'yyyy-MM-dd', new Date());
    return isValid(d) ? d : undefined;
}

function isReasonableDate(d) {
    const y = d.getFullYear();
    return y >= 1900 && y <= 2100;
}

/**
 * Data em yyyy-MM-dd (API) com calendário pt-BR, independente do browser.
 * `allowType`: permite escrever dd/mm/aaaa (além de abrir o calendário).
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
    allowType = false,
}) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const [textValue, setTextValue] = useState('');
    const [inputError, setInputError] = useState('');

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

    useEffect(() => {
        if (!allowType) return;
        if (!value) {
            setTextValue('');
            return;
        }
        const d = parseYmd(value);
        if (d) {
            setTextValue(format(d, 'dd/MM/yyyy'));
        }
    }, [value, allowType]);

    const commitDmyString = useCallback(
        (raw) => {
            const t = (raw || '').trim();
            if (!t) {
                setInputError('');
                onChange('');
                return true;
            }
            if (t.length < 10) {
                setInputError('Data incompleta.');
                return false;
            }
            const d = parse(t, 'dd/MM/yyyy', new Date());
            if (!isValid(d) || !isReasonableDate(d)) {
                setInputError('Data inválida.');
                return false;
            }
            if (format(d, 'dd/MM/yyyy') !== t) {
                setInputError('Data inválida.');
                return false;
            }
            setInputError('');
            onChange(format(d, 'yyyy-MM-dd'));
            return true;
        },
        [onChange]
    );

    const tryCommitDmy = useCallback((s) => commitDmyString(s), [commitDmyString]);

    const onAcceptType = (val) => {
        setTextValue(val);
        setInputError('');
        if (val && val.length === 10) {
            commitDmyString(val);
        } else if (!val) {
            onChange('');
        }
    };

    const display = selected ? format(selected, 'dd/MM/yyyy', { locale: ptBR }) : '';

    const triggerClass = compact
        ? `min-h-[32px] px-2 py-1 text-sm border rounded text-left w-full max-w-full min-w-0 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 disabled:cursor-not-allowed ${
              error || inputError ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'
          }`
        : `w-full min-h-[44px] px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-left focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 disabled:bg-gray-100 disabled:cursor-not-allowed ${
              error || inputError ? 'border-red-400 bg-red-50' : ''
          }`;

    const inputTypeClass = compact
        ? `min-h-[32px] px-2 py-1 text-sm border rounded w-full min-w-0 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 ${
              error || inputError ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'
          }`
        : `w-full min-h-[44px] px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white disabled:bg-gray-100 ${
              error || inputError ? 'border-red-400 bg-red-50' : ''
          }`;

    const calBtnClass = compact
        ? `shrink-0 min-h-[32px] min-w-[32px] px-1 border border-gray-300 rounded flex items-center justify-center bg-white hover:bg-gray-50 disabled:opacity-50 ${
              error || inputError ? 'border-red-400' : ''
          }`
        : `shrink-0 min-h-[44px] min-w-[44px] border border-gray-200 rounded-lg flex items-center justify-center bg-gray-50 hover:bg-white disabled:opacity-50 ${
              error || inputError ? 'border-red-400' : ''
          }`;

    const showError = error || inputError;

    return (
        <div className={`relative ${className}`} ref={rootRef}>
            {label ? (
                <label
                    className={`block text-sm font-medium text-gray-700 ${allowType ? 'mb-1' : 'mb-2'}`}
                >
                    {label}
                    {required && <span className="text-red-500 ml-1">*</span>}
                </label>
            ) : null}
            {allowType ? (
                <div className="flex gap-1 items-stretch w-full min-w-0">
                    <IMaskInput
                        mask="00/00/0000"
                        value={textValue}
                        onAccept={onAcceptType}
                        onBlur={() => tryCommitDmy(textValue)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.target.blur();
                            }
                        }}
                        disabled={disabled}
                        placeholder="dd/mm/aaaa"
                        inputMode="numeric"
                        autoComplete="off"
                        className={inputTypeClass}
                    />
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => !disabled && setOpen((o) => !o)}
                        className={calBtnClass}
                        title="Abrir calendário"
                        aria-label="Abrir calendário"
                    >
                        <svg className="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => !disabled && setOpen((o) => !o)}
                    className={triggerClass}
                >
                    {display || <span className="text-gray-400">Selecione a data…</span>}
                </button>
            )}
            {open && !disabled && (
                <div className="absolute z-[100] mt-1 right-0 rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
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
            {showError ? <p className="mt-1.5 text-sm text-red-600">{inputError || error}</p> : null}
        </div>
    );
}
