/**
 * Parte sequencial do número da receita (ex.: "3791-0002" → "2"), sem zeros à esquerda.
 */
export function sequenciaNumeroReceita(numero) {
    if (numero == null || numero === '') return '—';
    const parts = String(numero).split('-');
    const last = parts[parts.length - 1];
    const n = parseInt(last, 10);
    return Number.isNaN(n) ? String(numero) : String(n);
}

/**
 * Título ou rótulo com sequência: "Receita #2", "Editar Receita #3".
 * Se não houver sequência válida, devolve só o prefixo.
 */
export function tituloReceitaComSequencia(prefix, numero) {
    const s = sequenciaNumeroReceita(numero);
    if (s === '—') {
        return prefix;
    }
    return `${prefix} #${s}`;
}
