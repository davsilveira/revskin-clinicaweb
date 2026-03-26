/**
 * Normaliza texto de anotações para exibição inline (sem sequências literais \n visíveis).
 */
export function formatAnotacaoDisplay(text) {
    if (text == null || text === '') return '';
    let s = String(text);
    s = s.replace(/\r\n/g, ' ').replace(/\r/g, ' ');
    s = s.replace(/\\n/g, ' ');
    s = s.replace(/\n/g, ' ');
    return s.replace(/\s+/g, ' ').trim();
}
