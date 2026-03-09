export default function Checkbox({ 
    checked, 
    onChange, 
    label, 
    error, 
    disabled = false,
    className = '',
    ...props 
}) {
    return (
        <div className={className}>
            <label className="flex items-center gap-3 cursor-pointer">
                <input
                    type="checkbox"
                    checked={checked || false}
                    onChange={onChange}
                    disabled={disabled}
                    className={`w-5 h-5 text-blue-600 bg-gray-50 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 transition-all duration-200 ${
                        error ? 'border-red-400 bg-red-50' : ''
                    } ${disabled ? 'bg-gray-100 cursor-not-allowed opacity-60' : ''}`}
                    {...props}
                />
                {label && (
                    <span className="text-sm font-medium text-gray-700">
                        {label}
                    </span>
                )}
            </label>
            {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
        </div>
    );
}

