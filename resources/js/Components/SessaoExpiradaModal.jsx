import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

export const EVENTO_SESSAO_EXPIRADA = 'sessao-expirada';

/**
 * Modal única para sessão expirada (HTTP 419: token CSRF da página não vale mais).
 * Abre com o evento `sessao-expirada` (disparado em bootstrap.js para fetch/axios) e no lugar da
 * tela branca "419 Page Expired" das visitas Inertia.
 */
export default function SessaoExpiradaModal() {
    const [aberta, setAberta] = useState(false);

    useEffect(() => {
        const abrir = () => setAberta(true);
        window.addEventListener(EVENTO_SESSAO_EXPIRADA, abrir);
        const removeInvalid = router.on('invalid', (event) => {
            if (event.detail?.response?.status !== 419) return;
            event.preventDefault();
            abrir();
        });
        return () => {
            window.removeEventListener(EVENTO_SESSAO_EXPIRADA, abrir);
            removeInvalid();
        };
    }, []);

    if (!aberta) return null;

    return (
        <div className="fixed inset-0 z-[10000] flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="sessao-expirada-titulo">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
                        <svg className="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div className="min-w-0">
                        <h3 id="sessao-expirada-titulo" className="text-lg font-semibold text-gray-900">
                            Sua sessão expirou
                        </h3>
                        <p className="mt-2 text-sm text-gray-600">
                            A página ficou muito tempo aberta sem uso e o sistema não consegue mais salvar por ela.
                            Recarregue a página para continuar.
                        </p>
                        <p className="mt-2 text-sm text-gray-600">
                            Se você digitou algo que ainda não foi salvo, clique em <strong>Copiar antes</strong>,
                            copie o texto e só depois recarregue.
                        </p>
                    </div>
                </div>
                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={() => setAberta(false)}
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        Copiar antes
                    </button>
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                        className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                    >
                        Recarregar página
                    </button>
                </div>
            </div>
        </div>
    );
}
