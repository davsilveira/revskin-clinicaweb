import { useCallback, useLayoutEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Intercepta fechar drawer e navegação GET quando há alterações não salvas.
 * Mesmo padrão da receita: modal + router.on('before') + beforeunload.
 *
 * @param {Object} options
 * @param {boolean} options.isOpen — drawer visível
 * @param {boolean} options.isDirty — há alterações em relação ao baseline
 * @param {() => void} options.onConfirmClose — fechar drawer de facto (reset estado)
 * @param {() => Promise<boolean>} options.saveBeforeClose — salvar; true se persistiu; false se validação/rede falhou (deve preencher erros no form). Se false, o modal fecha para o utilizador ver o drawer com os erros.
 */
export default function useDrawerUnsavedChanges({
    isOpen,
    isDirty,
    onConfirmClose,
    saveBeforeClose,
}) {
    const [showUnsavedModal, setShowUnsavedModal] = useState(false);
    const [savingBeforeLeave, setSavingBeforeLeave] = useState(false);
    const pendingVisitRef = useRef(null);
    /** Evita que router.reload / visit disparados por onConfirmClose reativerem o modal no mesmo tick (antes do React atualizar isOpen/isDirty). */
    const bypassNavigationGuardRef = useRef(false);

    const requestClose = useCallback(() => {
        if (!isDirty) {
            onConfirmClose();
            return;
        }
        setShowUnsavedModal(true);
    }, [isDirty, onConfirmClose]);

    const handleUnsavedCancel = useCallback(() => {
        setShowUnsavedModal(false);
        pendingVisitRef.current = null;
    }, []);

    const handleUnsavedDiscard = useCallback(() => {
        bypassNavigationGuardRef.current = true;
        setShowUnsavedModal(false);
        const visit = pendingVisitRef.current;
        pendingVisitRef.current = null;
        onConfirmClose();
        if (visit) {
            router.visit(visit.url, { ...visit, onBefore: undefined });
        }
        setTimeout(() => {
            bypassNavigationGuardRef.current = false;
        }, 0);
    }, [onConfirmClose]);

    const handleUnsavedSave = useCallback(async () => {
        setSavingBeforeLeave(true);
        try {
            const ok = await saveBeforeClose();
            if (!ok) {
                setShowUnsavedModal(false);
                pendingVisitRef.current = null;
                return;
            }
            bypassNavigationGuardRef.current = true;
            setShowUnsavedModal(false);
            const visit = pendingVisitRef.current;
            pendingVisitRef.current = null;
            onConfirmClose();
            if (visit) {
                router.visit(visit.url, { ...visit, onBefore: undefined });
            }
            setTimeout(() => {
                bypassNavigationGuardRef.current = false;
            }, 0);
        } finally {
            setSavingBeforeLeave(false);
        }
    }, [saveBeforeClose, onConfirmClose]);

    useLayoutEffect(() => {
        if (!isOpen || !isDirty) return;

        const removeListener = router.on('before', (event) => {
            if (bypassNavigationGuardRef.current) return;
            if (event.detail?.visit?.method !== 'get') return;
            event.preventDefault();
            pendingVisitRef.current = event.detail.visit;
            setShowUnsavedModal(true);
        });

        return removeListener;
    }, [isOpen, isDirty]);

    useLayoutEffect(() => {
        if (!isOpen || !isDirty) return;

        const handler = (e) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [isOpen, isDirty]);

    return {
        requestClose,
        showUnsavedModal,
        savingBeforeLeave,
        handleUnsavedCancel,
        handleUnsavedDiscard,
        handleUnsavedSave,
    };
}
