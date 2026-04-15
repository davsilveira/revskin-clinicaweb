/**
 * Guarda a query string da listagem de pacientes para voltar da tela de receitas por paciente.
 */
export const PACIENTES_INDEX_QUERY_STORAGE_KEY = 'revskin.pacientes.index.query';

export function persistPacientesIndexQueryFromLocation() {
    if (typeof window === 'undefined') {
        return;
    }
    if (!window.location.pathname.startsWith('/pacientes')) {
        return;
    }
    const q = window.location.search;
    if (q) {
        sessionStorage.setItem(PACIENTES_INDEX_QUERY_STORAGE_KEY, q);
    } else {
        sessionStorage.removeItem(PACIENTES_INDEX_QUERY_STORAGE_KEY);
    }
}

export function getPacientesIndexReturnHref() {
    if (typeof window === 'undefined') {
        return '/pacientes';
    }
    const q = sessionStorage.getItem(PACIENTES_INDEX_QUERY_STORAGE_KEY);
    return q ? `/pacientes${q}` : '/pacientes';
}
