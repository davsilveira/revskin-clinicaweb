import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { getReceitasIndexReturnHref } from '@/utils/receitasListNavigation';

/**
 * Link para a listagem de receitas mantendo busca/filtros salvos ao abrir um detalhe.
 */
export default function ReceitasIndexBackLink({ className, children }) {
    const [href] = useState(() => getReceitasIndexReturnHref());

    return (
        <Link href={href} className={className}>
            {children}
        </Link>
    );
}
