import { useState } from 'react';

/**
 * Parse emails from textarea: one per line or comma-separated.
 * Returns array of trimmed, non-empty, valid-looking emails.
 */
export function parseExtraEmails(text) {
    if (!text || typeof text !== 'string') return [];
    const raw = text.split(/[\n,;]+/).map((e) => e.trim()).filter(Boolean);
    const emails = raw.filter((e) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e));
    return [...new Set(emails)];
}

export default function ExportConfirmModal({
    open,
    onClose,
    onConfirm,
    itemCount,
    itemLabel = 'itens',
    formatLabel = 'PDF',
    defaultEmail,
    title = 'Confirmar exportação',
}) {
    const [extraEmailsRaw, setExtraEmailsRaw] = useState('');

    const handleConfirm = () => {
        const extra = parseExtraEmails(extraEmailsRaw);
        onConfirm(extra);
        setExtraEmailsRaw('');
        onClose();
    };

    const handleClose = () => {
        setExtraEmailsRaw('');
        onClose();
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className="fixed inset-0 bg-black/50 transition-opacity"
                    onClick={handleClose}
                />
                <div className="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 transform transition-all">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="flex-shrink-0 w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                            <svg className="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    </div>

                    <p className="text-gray-600 mb-2">
                        {itemCount} {itemLabel} serão exportados em {formatLabel}.
                    </p>
                    <p className="text-gray-600 mb-4">
                        Será enviado para: <span className="font-medium text-gray-900">{defaultEmail || 'seu email'}</span>
                    </p>

                    <div className="mb-6">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Emails adicionais (opcional)
                        </label>
                        <textarea
                            value={extraEmailsRaw}
                            onChange={(e) => setExtraEmailsRaw(e.target.value)}
                            rows={3}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            placeholder="um por linha ou separados por vírgula"
                        />
                    </div>

                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={handleClose}
                            className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={handleConfirm}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                        >
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
