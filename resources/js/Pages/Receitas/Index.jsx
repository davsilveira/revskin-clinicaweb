import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useMemo, useRef } from 'react';
import debounce from 'lodash/debounce';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import PatientDrawer from '@/Components/PatientDrawer';
import { nomeExibicaoSemTitulo } from '@/utils/nomeExibicao';
import { sequenciaNumeroReceita } from '@/utils/receitaNumero';
import PacientesIndexBackLink from '@/Components/PacientesIndexBackLink';
import { persistReceitasIndexQueryFromLocation } from '@/utils/receitasListNavigation';

function buildReceitasIndexParams(term, st, pacId) {
    const params = {};
    const t = (term ?? '').trim();
    if (t) {
        params.search = t;
    }
    if (st !== undefined && st !== null && st !== '') {
        params.status = st;
    }
    if (pacId) {
        params.paciente_id = pacId;
    }
    return params;
}

export default function ReceitasIndex({
    receitas,
    filters,
    pacienteFiltrado = null,
    medicosPacienteDrawer = [],
    receitasIndexIsAdmin = false,
    receitasIndexIsSecretaria = false,
    receitasIndexCanSelectMedico = true,
}) {
    const { auth } = usePage().props;
    const isMedico = auth.user.role === 'medico';
    const [patientDrawerOpen, setPatientDrawerOpen] = useState(false);
    const [search, setSearch] = useState(() => (filters?.search != null && filters.search !== '' ? String(filters.search) : ''));
    const [status, setStatus] = useState(() =>
        filters?.paciente_id ? '' : filters?.status != null ? String(filters.status) : ''
    );
    const pacienteId = filters?.paciente_id;
    const listagemPorPaciente = Boolean(pacienteId);
    const receitasQuerySearch = listagemPorPaciente ? '' : search;
    const receitasQueryStatus = listagemPorPaciente ? '' : status;
    const skipNextReceitasFetch = useRef(true);

    useEffect(() => {
        if (!filters) {
            return;
        }
        const patientScoped = Boolean(filters.paciente_id);
        const nextSearch = patientScoped
            ? ''
            : filters.search != null && filters.search !== ''
              ? String(filters.search)
              : '';
        setSearch((prev) => (prev === nextSearch ? prev : nextSearch));
        const nextStatus = patientScoped
            ? ''
            : filters.status != null
              ? String(filters.status)
              : '';
        setStatus((prev) => (prev === nextStatus ? prev : nextStatus));
    }, [filters?.search, filters?.status, filters?.paciente_id]);

    useEffect(() => {
        persistReceitasIndexQueryFromLocation();
    }, [filters?.search, filters?.status, filters?.paciente_id]);

    const runReceitasQuery = useMemo(
        () =>
            debounce((term, st, pacId) => {
                router.get('/receitas', buildReceitasIndexParams(term, st, pacId), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['receitas', 'filters'],
                });
            }, 350),
        []
    );

    useEffect(() => {
        if (skipNextReceitasFetch.current) {
            skipNextReceitasFetch.current = false;
            return;
        }
        runReceitasQuery(receitasQuerySearch, receitasQueryStatus, pacienteId);
    }, [receitasQuerySearch, receitasQueryStatus, pacienteId, runReceitasQuery]);

    useEffect(() => () => runReceitasQuery.cancel(), [runReceitasQuery]);

    const telefonesExibicao = (p) => {
        if (!p) return [];
        const out = [];
        if (p.celular) out.push(p.celular);
        if (p.telefone1) out.push(p.telefone1);
        (p.telefones || []).forEach((t) => {
            if (t?.numero) {
                out.push(t.tipo ? `${t.tipo}: ${t.numero}` : t.numero);
            }
        });
        return [...new Set(out)];
    };

    const refreshReceitasIndex = () => {
        runReceitasQuery.cancel();
        router.get('/receitas', buildReceitasIndexParams(receitasQuerySearch, receitasQueryStatus, pacienteId), {
            preserveState: true,
            preserveScroll: true,
            only: ['receitas', 'filters'],
        });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        runReceitasQuery.cancel();
        router.get('/receitas', buildReceitasIndexParams(receitasQuerySearch, receitasQueryStatus, pacienteId), {
            preserveState: true,
            preserveScroll: true,
            only: ['receitas', 'filters'],
        });
    };

    const getStatusBadge = (statusVal) => {
        const badges = {
            aberta: 'bg-gray-100 text-gray-800',
            finalizada: 'bg-green-100 text-green-800',
            cancelada: 'bg-red-100 text-red-800',
        };
        const labels = {
            aberta: 'Aberta',
            finalizada: 'Finalizada',
            cancelada: 'Cancelada',
        };
        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${badges[statusVal] || 'bg-gray-100'}`}>
                {labels[statusVal] || statusVal}
            </span>
        );
    };

    const receitasList = receitas?.data || [];

    const desktopEmptyColSpan = 4 + (listagemPorPaciente ? 0 : 1) + (!isMedico ? 1 : 0);
    const rowVisit = (id) => {
        persistReceitasIndexQueryFromLocation();
        router.visit(`/receitas/${id}`);
    };

    return (
        <DashboardLayout>
            <Head title="Receitas" />

            <div className="py-4 lg:py-6 px-0">
                {pacienteFiltrado && (
                    <div className="mb-4">
                        <PacientesIndexBackLink className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                            Voltar para Pacientes
                        </PacientesIndexBackLink>
                    </div>
                )}
                <PageHeader
                    title="Receitas"
                    description="Gerencie as receitas médicas"
                    actions={
                        <>
                            <Link
                                href={
                                    pacienteFiltrado
                                        ? `/assistente-receita?paciente_id=${pacienteFiltrado.id}`
                                        : '/assistente-receita'
                                }
                                className="w-full sm:w-auto justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Assistente de Receita
                            </Link>
                            <Link
                                href={
                                    pacienteFiltrado
                                        ? `/receitas/create?paciente_id=${pacienteFiltrado.id}`
                                        : '/receitas/create'
                                }
                                className="w-full sm:w-auto justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Receita sem Assistente
                            </Link>
                        </>
                    }
                />

                {pacienteFiltrado && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5 mb-6">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm flex-1 min-w-0">
                                <div className="space-y-3">
                                    <div>
                                        <span className="text-gray-500 block text-xs font-medium uppercase tracking-wide">
                                            Paciente
                                        </span>
                                        <span className="text-gray-900 font-semibold">{pacienteFiltrado.nome}</span>
                                    </div>
                                    <div>
                                        <span className="text-gray-500 block text-xs font-medium uppercase tracking-wide">
                                            Nº Registro
                                        </span>
                                        <span className="text-gray-900">
                                            {pacienteFiltrado.codigo != null && String(pacienteFiltrado.codigo).trim() !== ''
                                                ? String(pacienteFiltrado.codigo).trim()
                                                : '—'}
                                        </span>
                                    </div>
                                </div>
                                <div className="space-y-3">
                                    <div>
                                        <span className="text-gray-500 block text-xs font-medium uppercase tracking-wide">
                                            Médico
                                        </span>
                                        <span className="text-gray-900">
                                            {nomeExibicaoSemTitulo(
                                                pacienteFiltrado.medico?.linkedUser?.name ||
                                                    pacienteFiltrado.medico?.linked_user?.name ||
                                                    pacienteFiltrado.medico?.apelido
                                            ) || '—'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-gray-500 block text-xs font-medium uppercase tracking-wide">
                                            Indicado por
                                        </span>
                                        <span className="text-gray-900">{pacienteFiltrado.indicado_por || '—'}</span>
                                    </div>
                                </div>
                                <div className="sm:col-span-2">
                                    <span className="text-gray-500 block text-xs font-medium uppercase tracking-wide">
                                        Telefones
                                    </span>
                                    <span className="text-gray-900">
                                        {telefonesExibicao(pacienteFiltrado).length
                                            ? telefonesExibicao(pacienteFiltrado).join(' · ')
                                            : '—'}
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setPatientDrawerOpen(true)}
                                className="shrink-0 min-h-[44px] px-4 py-2 border border-gray-300 rounded-lg text-gray-800 font-medium hover:bg-gray-50 transition-colors"
                            >
                                Editar paciente
                            </button>
                        </div>
                    </div>
                )}

                {!listagemPorPaciente && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                        <form onSubmit={handleSearch} className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                            <input
                                type="text"
                                placeholder="Buscar por paciente..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full min-w-0 flex-1 px-4 py-2.5 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                autoComplete="off"
                            />
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full sm:w-auto min-h-[44px] px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="">Todos os status</option>
                                <option value="aberta">Aberta</option>
                                <option value="finalizada">Finalizada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                            <button
                                type="submit"
                                className="w-full sm:w-auto min-h-[44px] px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                Filtrar
                            </button>
                        </form>
                    </div>
                )}

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <ResponsiveEntityList
                        desktop={
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                {listagemPorPaciente ? 'Receita' : 'Código'}
                                            </th>
                                            {!listagemPorPaciente && (
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Paciente
                                                </th>
                                            )}
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Data
                                            </th>
                                            {!isMedico && (
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Valor Total
                                                </th>
                                            )}
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {receitasList.length > 0 ? (
                                            receitasList.map((receita) => (
                                                <tr
                                                    key={receita.id}
                                                    className="hover:bg-gray-50 cursor-pointer"
                                                    onClick={() => rowVisit(receita.id)}
                                                >
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className="text-sm font-medium text-gray-900">
                                                            {listagemPorPaciente
                                                                ? sequenciaNumeroReceita(receita.numero)
                                                                : receita.numero}
                                                        </span>
                                                    </td>
                                                    {!listagemPorPaciente && (
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm font-medium text-gray-900">
                                                                {receita.paciente?.nome}
                                                            </div>
                                                            <div className="text-sm text-gray-500">
                                                                {receita.paciente?.cpf}
                                                            </div>
                                                        </td>
                                                    )}
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {new Date(receita.data_receita).toLocaleDateString('pt-BR')}
                                                    </td>
                                                    {!isMedico && (
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            {new Intl.NumberFormat('pt-BR', {
                                                                style: 'currency',
                                                                currency: 'BRL',
                                                            }).format(receita.valor_total)}
                                                        </td>
                                                    )}
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {getStatusBadge(receita.status)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right" onClick={(e) => e.stopPropagation()}>
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Link
                                                                href={`/receitas/${receita.id}`}
                                                                onClick={() => persistReceitasIndexQueryFromLocation()}
                                                                className="inline-flex p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                                aria-label={isMedico ? 'Visualizar receita' : 'Ver receita'}
                                                            >
                                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                            </Link>
                                                            {receita.status === 'finalizada' ? (
                                                                <a
                                                                    href={`/receitas/${receita.id}/pdf`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="inline-flex p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                                    aria-label="Download PDF"
                                                                >
                                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                                    </svg>
                                                                </a>
                                                            ) : !isMedico ? (
                                                                <Link
                                                                    href={`/receitas/${receita.id}/edit`}
                                                                    onClick={() => persistReceitasIndexQueryFromLocation()}
                                                                    className="inline-flex p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                                    aria-label="Editar receita"
                                                                >
                                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                </Link>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={desktopEmptyColSpan} className="px-6 py-12 text-center">
                                                    <div className="text-gray-500">
                                                        <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        <p>Nenhuma receita encontrada</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        }
                        mobile={
                            <div className="divide-y divide-gray-200">
                                {receitasList.length > 0 ? (
                                    receitasList.map((receita) => (
                                        <div key={receita.id} className="p-4">
                                            <div className="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                                <button
                                                    type="button"
                                                    className="w-full text-left p-3 min-h-[44px] hover:bg-gray-50 transition-colors"
                                                    onClick={() => rowVisit(receita.id)}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <div className="font-medium text-gray-900">
                                                                {listagemPorPaciente
                                                                    ? sequenciaNumeroReceita(receita.numero)
                                                                    : receita.numero}
                                                            </div>
                                                            {!listagemPorPaciente && (
                                                                <>
                                                                    <div className="text-sm font-medium text-gray-900 mt-1 break-words">
                                                                        {receita.paciente?.nome || '—'}
                                                                    </div>
                                                                    <p className="text-sm text-gray-600 mt-1">
                                                                        {receita.paciente?.cpf || '—'} ·{' '}
                                                                        {new Date(receita.data_receita).toLocaleDateString('pt-BR')}
                                                                    </p>
                                                                </>
                                                            )}
                                                            {listagemPorPaciente && (
                                                                <p className="text-sm text-gray-600 mt-1">
                                                                    {new Date(receita.data_receita).toLocaleDateString('pt-BR')}
                                                                </p>
                                                            )}
                                                            {!isMedico && (
                                                                <p className="text-sm text-gray-900 mt-0.5 font-medium">
                                                                    {new Intl.NumberFormat('pt-BR', {
                                                                        style: 'currency',
                                                                        currency: 'BRL',
                                                                    }).format(receita.valor_total)}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="flex-shrink-0">{getStatusBadge(receita.status)}</div>
                                                    </div>
                                                </button>
                                                <div
                                                    className="flex flex-wrap items-center justify-end gap-1 px-2 py-2 border-t border-gray-100 bg-gray-50/60"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <Link
                                                        href={`/receitas/${receita.id}`}
                                                        onClick={() => persistReceitasIndexQueryFromLocation()}
                                                        className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                        aria-label={isMedico ? 'Visualizar receita' : 'Ver receita'}
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </Link>
                                                    {receita.status === 'finalizada' ? (
                                                        <a
                                                            href={`/receitas/${receita.id}/pdf`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                            aria-label="Download PDF"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                            </svg>
                                                        </a>
                                                    ) : !isMedico ? (
                                                        <Link
                                                            href={`/receitas/${receita.id}/edit`}
                                                            onClick={() => persistReceitasIndexQueryFromLocation()}
                                                            className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                            aria-label="Editar receita"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </Link>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="px-4 py-12 text-center text-gray-500">
                                        <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p>Nenhuma receita encontrada</p>
                                    </div>
                                )}
                            </div>
                        }
                    />

                    {receitas?.links && receitas.links.length > 3 && (
                        <div className="px-4 sm:px-6 py-4 border-t border-gray-200 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                            <div className="text-sm text-gray-500">
                                Mostrando {receitas.from} a {receitas.to} de {receitas.total} resultados
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {receitas.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        preserveState
                                        className={`min-h-[44px] min-w-[44px] px-3 py-1 inline-flex items-center justify-center rounded text-sm ${
                                            link.active
                                                ? 'bg-emerald-600 text-white'
                                                : link.url
                                                  ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                                  : 'bg-gray-50 text-gray-400 pointer-events-none'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <PatientDrawer
                isOpen={patientDrawerOpen}
                onClose={() => setPatientDrawerOpen(false)}
                paciente={pacienteFiltrado}
                onSave={() => {
                    setPatientDrawerOpen(false);
                    refreshReceitasIndex();
                }}
                isAdmin={receitasIndexIsAdmin}
                showMedicoField={receitasIndexCanSelectMedico}
                medicos={medicosPacienteDrawer}
                medicoRequired={receitasIndexIsSecretaria}
                enableAutoSave
            />
        </DashboardLayout>
    );
}
