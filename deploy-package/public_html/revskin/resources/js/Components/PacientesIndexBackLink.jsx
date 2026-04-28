import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { getPacientesIndexReturnHref } from '@/utils/pacientesListNavigation';

export default function PacientesIndexBackLink({ className, children }) {
    const [href] = useState(() => getPacientesIndexReturnHref());

    return (
        <Link href={href} className={className}>
            {children}
        </Link>
    );
}
