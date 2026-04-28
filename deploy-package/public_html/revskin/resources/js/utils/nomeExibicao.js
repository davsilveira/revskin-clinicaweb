/**
 * Remove prefixos honoríficos comuns de nomes de médicos para exibição uniforme.
 * Não altera o valor persistido — só para UI.
 */
export function nomeExibicaoSemTitulo(nome) {
    if (nome == null || typeof nome !== 'string') {
        return nome ?? '';
    }
    const t = nome.trim();
    if (!t) {
        return t;
    }
    return t
        .replace(/^(?:dr\.?a?|dra\.?|doutor(?:a)?)\s+/iu, '')
        .trim();
}
