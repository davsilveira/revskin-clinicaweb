import { useEffect, useRef, useState } from 'react';

function normalizar(valor) {
    return valor != null && valor !== '' ? String(valor) : '';
}

/**
 * Estado do campo "Pesquisar" de uma listagem que busca no servidor com debounce.
 *
 * O servidor devolve `filters.search` aparado (TrimStrings) e as respostas podem chegar
 * fora de ordem. Copiar esse valor de volta no input apagava o espaço recém-digitado
 * ("pereira " → "pereira") e trazia de volta termos antigos ("pereira lo" → "pereira").
 * Aqui o eco de qualquer termo que o próprio usuário digitou é ignorado; só valores de
 * fora (voltar do navegador, link com ?search=) substituem o que está no campo.
 *
 * @param {unknown} serverSearch filters.search vindo das props
 * @param {{ force?: boolean }} [options] force: adota o valor do servidor mesmo se já digitado
 */
export default function useSearchFilter(serverSearch, { force = false } = {}) {
    const [search, setSearchState] = useState(() => normalizar(serverSearch));
    const digitados = useRef(new Set([normalizar(serverSearch).trim()]));

    const setSearch = (valor) => {
        setSearchState((prev) => {
            const next = typeof valor === 'function' ? valor(prev) : valor;
            digitados.current.add(normalizar(next).trim());
            return next;
        });
    };

    useEffect(() => {
        const next = normalizar(serverSearch);
        if (!force && digitados.current.has(next.trim())) {
            return;
        }
        digitados.current = new Set([next.trim()]);
        setSearchState((prev) => (prev === next ? prev : next));
    }, [serverSearch, force]);

    return [search, setSearch];
}
