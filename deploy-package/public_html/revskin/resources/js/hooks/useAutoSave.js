import { useCallback, useRef, useState, useEffect } from 'react';
import debounce from 'lodash/debounce';

/**
 * Hook for auto-saving form data after a delay
 * @param {Function} saveFunction - Function to call for saving (should return a Promise)
 * @param {number} delay - Delay in milliseconds before saving (default: 2000ms)
 * @param {boolean} enabled - Whether autosave is enabled
 */
export default function useAutoSave(saveFunction, delay = 2000, enabled = true) {
    const [lastSaved, setLastSaved] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const saveRef = useRef(saveFunction);

    // Keep the save function reference up to date
    useEffect(() => {
        saveRef.current = saveFunction;
    }, [saveFunction]);

    // Ref to track enabled state for debounced function
    const enabledRef = useRef(enabled);
    useEffect(() => {
        enabledRef.current = enabled;
    }, [enabled]);

    // Debounced save function - uses refs to avoid stale closures
    const debouncedSave = useCallback(
        debounce(async () => {
            if (!enabledRef.current) return;
            
            setIsSaving(true);
            try {
                await saveRef.current();
                setLastSaved(new Date());
                setHasUnsavedChanges(false);
            } catch (error) {
                console.error('Autosave error:', error);
            } finally {
                setIsSaving(false);
            }
        }, delay),
        [delay]
    );

    // Trigger autosave on data change
    const triggerAutoSave = useCallback(() => {
        if (!enabledRef.current) return;
        setHasUnsavedChanges(true);
        debouncedSave();
    }, [debouncedSave]);

    // Cancel pending saves
    const cancelAutoSave = useCallback(() => {
        debouncedSave.cancel();
    }, [debouncedSave]);

    // Force immediate save
    const saveNow = useCallback(async () => {
        debouncedSave.cancel();
        if (!enabled) return;
        
        setIsSaving(true);
        try {
            await saveRef.current();
            setLastSaved(new Date());
            setHasUnsavedChanges(false);
        } catch (error) {
            console.error('Save error:', error);
            throw error;
        } finally {
            setIsSaving(false);
        }
    }, [debouncedSave, enabled]);

    // Format last saved time
    const getLastSavedText = useCallback(() => {
        if (!lastSaved) return null;
        return lastSaved.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }, [lastSaved]);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            debouncedSave.cancel();
        };
    }, [debouncedSave]);

    return {
        lastSaved,
        lastSavedText: getLastSavedText(),
        isSaving,
        hasUnsavedChanges,
        triggerAutoSave,
        cancelAutoSave,
        saveNow,
    };
}
