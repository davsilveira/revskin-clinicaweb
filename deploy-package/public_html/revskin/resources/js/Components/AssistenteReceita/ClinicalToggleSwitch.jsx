/**
 * Interruptor estilo pill (sem texto Sim/Não). Cinza claro desligado, verde ligado.
 */
export default function ClinicalToggleSwitch({
    checked,
    onChange,
    disabled = false,
    'aria-labelledby': ariaLabelledby,
    'aria-label': ariaLabel,
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-labelledby={ariaLabelledby}
            aria-label={ariaLabel}
            disabled={disabled}
            onClick={() => !disabled && onChange(!checked)}
            className={`relative inline-flex h-[26px] w-[44px] flex-shrink-0 cursor-pointer rounded-full border-0 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 ${
                checked ? 'bg-emerald-500' : 'bg-gray-200'
            } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
        >
            <span
                aria-hidden
                className={`pointer-events-none absolute left-[2px] top-1/2 h-[22px] w-[22px] -translate-y-1/2 rounded-full bg-white shadow transition-transform duration-200 ease-in-out ${
                    checked ? 'translate-x-[18px]' : 'translate-x-0'
                }`}
            />
        </button>
    );
}
