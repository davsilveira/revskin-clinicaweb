export default function Select({ 
    value, 
    onChange, 
    label, 
    error, 
    required = false,
    disabled = false,
    options = [],
    placeholder = 'Selecione...',
    children,
    className = '',
    ...props 
}) {
    return (
        <div className={className}>
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    {label}
                    {required && <span className="text-red-500 ml-1">*</span>}
                </label>
            )}
            <select
                value={value || ''}
                onChange={onChange}
                disabled={disabled}
                className={`w-full px-4 py-3 h-[44px] bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 ${
                    error ? 'border-red-400 bg-red-50' : ''
                } ${disabled ? 'bg-gray-100 cursor-not-allowed opacity-60' : ''}`}
                {...props}
            >
                {children ? (
                    children
                ) : (
                    <>
                        <option value="">{placeholder}</option>
                        {options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </>
                )}
            </select>
            {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
        </div>
    );
}

