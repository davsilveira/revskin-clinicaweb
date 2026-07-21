import { Head, Link, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

const MESSAGES = {
    403: {
        title: 'Acesso negado',
        description: 'Você não tem permissão para acessar esta página.',
    },
    404: {
        title: 'Página não encontrada',
        description: 'O endereço solicitado não existe ou foi movido.',
    },
    500: {
        title: 'Erro interno',
        description: 'Ocorreu um erro inesperado. Tente novamente em instantes.',
    },
    503: {
        title: 'Serviço indisponível',
        description: 'O sistema está temporariamente indisponível. Tente novamente em breve.',
    },
};

export default function Error({ status = 500 }) {
    const { auth } = usePage().props;
    const homeHref = auth?.user?.role === 'callcenter' ? '/receitas' : '/pacientes';
    const info = MESSAGES[status] ?? {
        title: 'Erro',
        description: 'Não foi possível carregar esta página.',
    };

    return (
        <DashboardLayout>
            <Head title={info.title} />

            <div className="flex flex-col items-center justify-center py-16 px-4 text-center">
                <p className="text-6xl font-semibold text-gray-300 tracking-tight">{status}</p>
                <h1 className="mt-4 text-2xl font-bold text-gray-900">{info.title}</h1>
                <p className="mt-2 max-w-md text-gray-600">{info.description}</p>
                <Link
                    href={homeHref}
                    className="mt-8 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
                >
                    Voltar ao início
                </Link>
            </div>
        </DashboardLayout>
    );
}
