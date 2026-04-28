/**
 * Guarda a query string da listagem de receitas para o link "Voltar para Receitas do Paciente"
 * (o histórico do navegador já preserva; o Link precisa do mesmo destino).
 */
export const RECEITAS_INDEX_QUERY_STORAGE_KEY = 'revskin.receitas.index.query';

export function persistReceitasIndexQueryFromLocation() {
    if (typeof window === 'undefined') {
        return;
    }
    const q = window.location.search;
    if (q) {
        sessionStorage.setItem(RECEITAS_INDEX_QUERY_STORAGE_KEY, q);
    } else {
        sessionStorage.removeItem(RECEITAS_INDEX_QUERY_STORAGE_KEY);
    }
}

export function getReceitasIndexReturnHref() {
    if (typeof window === 'undefined') {
        return '/receitas';
    }
    const q = sessionStorage.getItem(RECEITAS_INDEX_QUERY_STORAGE_KEY);
    return q ? `/receitas${q}` : '/receitas';
}
