/**
 * Topo de página padronizado: coluna no mobile, linha no desktop (lg+).
 */
export default function PageHeader({ title, description, subtitle, actions, className = '' }) {
    return (
        <div className={`mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between ${className}`}>
            <div className="min-w-0">
                <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                {description ? <p className="text-gray-600 mt-1">{description}</p> : null}
                {subtitle ? <div className="mt-2 text-sm text-gray-600 space-y-1">{subtitle}</div> : null}
            </div>
            {actions ? (
                <div className="flex flex-col sm:flex-row sm:flex-wrap gap-2 w-full lg:w-auto lg:justify-end lg:flex-shrink-0">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
