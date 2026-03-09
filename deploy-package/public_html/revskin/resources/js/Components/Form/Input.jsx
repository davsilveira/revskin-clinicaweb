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
    ...props 
}) {
    const inputProps = {
        value: value || '',
        onChange,
        placeholder,
        disabled,
        maxLength,
        className: `w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 ${
            error ? 'border-red-400 bg-red-50' : ''
        } ${disabled ? 'bg-gray-100 cursor-not-allowed opacity-60' : ''} ${
            multiline ? 'min-h-[100px] resize-y' : 'h-[44px]'
        }`,
        ...props
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
                <textarea
                    {...inputProps}
                    rows={rows}
                />
            ) : (
                <input
                    type={type}
                    {...inputProps}
                />
            )}
            {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
        </div>
    );
}

