/**
 * Toggle segmentado Sim / Não (acessível: grupo de botões, um estado selecionado).
 */
export default function SimNaoToggle({ value, onChange, disabled = false }) {
    return (
        <div
            className="inline-flex rounded-lg border border-gray-200 p-0.5 bg-gray-100/80 shadow-inner"
            role="group"
            aria-label="Resposta Sim ou Não"
        >
            {['Sim', 'Não'].map((option) => {
                const selected = value === option;
                return (
                    <button
                        key={option}
                        type="button"
                        disabled={disabled}
                        aria-pressed={selected}
                        onClick={() => onChange(option)}
                        className={`min-h-[40px] min-w-[4.75rem] px-4 py-2 text-sm font-semibold rounded-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-1 ${
                            selected
                                ? 'bg-white text-emerald-800 shadow border border-emerald-200/80'
                                : 'text-gray-600 hover:text-gray-900'
                        } ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
                    >
                        {option}
                    </button>
                );
            })}
        </div>
    );
}
