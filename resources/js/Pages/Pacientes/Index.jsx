import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useMemo, useRef } from 'react';
import debounce from 'lodash/debounce';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PatientDrawer from '@/Components/PatientDrawer';
import Toast from '@/Components/Toast';
import PageHeader from '@/Components/PageHeader';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import { persistPacientesIndexQueryFromLocation } from '@/utils/pacientesListNavigation';

/** Alinha o select de status ao que veio do backend (query / Inertia). */
function normalizarAtivoFiltro(filtersObj) {
    if (!filtersObj || !Object.prototype.hasOwnProperty.call(filtersObj, 'ativo')) {
        return '1';
    }
    const val = filtersObj.ativo;
    if (val === '' || val === null) {
        return '';
    }
    if (val === true || val === 1 || val === '1' || val === 'true') {
        return '1';
    }
    if (val === false || val === 0 || val === '0' || val === 'false') {
        return '0';
    }
    return String(val);
}

export default function PacientesIndex({ pacientes, medicos = [], tiposTelefone = {}, isAdmin = false, isSecretaria = false, canSelectMedico = false, filters }) {
    const { auth } = usePage().props;
    const isCallcenter = auth?.user?.role === 'callcenter';

    // Opção 2: nomes dos médicos vinculados (pivot). Fallback ao médico de origem.
    const medicosLabel = (paciente) => {
        const nomes = (paciente.medicos || [])
            .map((m) => m?.nome || m?.linkedUser?.name)
            .filter(Boolean);
        if (nomes.length > 0) return nomes.join(', ');
        return paciente.medico?.nome || paciente.medico?.linkedUser?.name || '—';
    };
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingPaciente, setEditingPaciente] = useState(null);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState(() => (filters?.search != null && filters.search !== '' ? String(filters.search) : ''));
    const [status, setStatus] = useState(() => normalizarAtivoFiltro(filters));
    const skipNextPacientesFetch = useRef(true);

    // Voltar do navegador / visita Inertia: alinhar ao que veio na URL (deps estáveis para não reiniciar o debounce a cada resposta).
    useEffect(() => {
        if (!filters) {
            return;
        }
        const nextSearch = filters.search != null && filters.search !== '' ? String(filters.search) : '';
        setSearch((prev) => (prev === nextSearch ? prev : nextSearch));
        const nextStatus = normalizarAtivoFiltro(filters);
        setStatus((prev) => (prev === nextStatus ? prev : nextStatus));
    }, [filters?.search, filters?.ativo]);

    useEffect(() => {
        persistPacientesIndexQueryFromLocation();
    }, [filters?.search, filters?.ativo]);

    const runPacientesQuery = useMemo(
        () =>
            debounce((term, ativo) => {
                const params = { ativo };
                const t = term?.trim();
                if (t) {
                    params.search = t;
                }
                router.get('/pacientes', params, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['pacientes', 'filters'],
                });
            }, 350),
        []
    );

    useEffect(() => {
        if (skipNextPacientesFetch.current) {
            skipNextPacientesFetch.current = false;
            return;
        }
        runPacientesQuery(search, status);
    }, [search, status, runPacientesQuery]);

    useEffect(() => () => runPacientesQuery.cancel(), [runPacientesQuery]);

    const openCreateDrawer = () => {
        setEditingPaciente(null);
        setDrawerOpen(true);
    };

    const openEditDrawer = (paciente) => {
        setEditingPaciente(paciente);
        setDrawerOpen(true);
    };

    const closeDrawer = () => {
        setDrawerOpen(false);
        setEditingPaciente(null);
    };

    const handleSave = () => {
        const msg = editingPaciente ? 'Paciente atualizado com sucesso!' : 'Paciente cadastrado com sucesso!';
        closeDrawer();
        setToast({ message: msg, type: 'success' });
        const qs = typeof window !== 'undefined' ? window.location.search : '';
        router.visit(`/pacientes${qs}`, {
            only: ['pacientes', 'filters'],
            preserveState: true,
            preserveScroll: true,
        });
    };

    const pacientesList = pacientes?.data || pacientes || [];

    const rowClick = (paciente) => {
        if (isCallcenter) {
            const ultimaId = paciente.ultima_receita_id;
            if (ultimaId) {
                persistPacientesIndexQueryFromLocation();
                router.visit(`/receitas/${ultimaId}`);
                return;
            }
            router.visit(`/pacientes/${paciente.id}`);
            return;
        }
        if (isSecretaria) {
            openEditDrawer(paciente);
            return;
        }
        const ultimaId = paciente.ultima_receita_id;
        if (ultimaId) {
            persistPacientesIndexQueryFromLocation();
            router.visit(`/receitas/${ultimaId}`);
            return;
        }
        openEditDrawer(paciente);
    };

    return (
        <DashboardLayout>
            <Head title="Pacientes" />

            <div className="py-4 lg:py-6 px-0">
                <PageHeader
                    title="Pacientes"
                    description="Gerencie os pacientes cadastrados"
                    actions={
                        !isCallcenter && (
                        <button
                            type="button"
                            onClick={openCreateDrawer}
                            className="w-full sm:w-auto justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            Novo Paciente
                        </button>
                        )
                    }
                />

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                        <input
                            type="text"
                            placeholder="Buscar por nome, CPF ou Nº registro…"
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
                            <option value="">Todos</option>
                            <option value="1">Ativos</option>
                            <option value="0">Inativos</option>
                        </select>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <ResponsiveEntityList
                        desktop={
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                            {canSelectMedico && (
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Médico(s)</th>
                                            )}
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nº Registro</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefone</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cidade</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {pacientesList.length > 0 ? (
                                            pacientesList.map((paciente) => (
                                                <tr
                                                    key={paciente.id}
                                                    className="hover:bg-gray-50 cursor-pointer"
                                                    onClick={() => rowClick(paciente)}
                                                >
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-medium text-gray-900">{paciente.nome}</div>
                                                    </td>
                                                    {canSelectMedico && (
                                                        <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title={medicosLabel(paciente)}>
                                                            {medicosLabel(paciente)}
                                                        </td>
                                                    )}
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 tabular-nums">
                                                        {paciente.codigo != null && String(paciente.codigo).trim() !== ''
                                                            ? String(paciente.codigo).trim()
                                                            : '—'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.cpf || '—'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.celular || paciente.telefone1 || '—'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{paciente.cidade ? `${paciente.cidade}/${paciente.uf}` : '—'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${paciente.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                            {paciente.ativo ? 'Ativo' : 'Inativo'}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right" onClick={(e) => e.stopPropagation()}>
                                                        <div className="flex items-center justify-end gap-1">
                                                            {!isSecretaria && (
                                                                <Link
                                                                    href={`/receitas?paciente_id=${paciente.id}`}
                                                                    onClick={() => persistPacientesIndexQueryFromLocation()}
                                                                    className="group relative p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                                    aria-label="Ver receitas"
                                                                >
                                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                    </svg>
                                                                    <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 pointer-events-none whitespace-nowrap z-10">
                                                                        Ver receitas
                                                                    </span>
                                                                </Link>
                                                            )}
                                                            {!isCallcenter && (
                                                            <span className="group relative inline-block">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openEditDrawer(paciente)}
                                                                    className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                                    aria-label="Editar"
                                                                >
                                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                </button>
                                                                <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 pointer-events-none whitespace-nowrap z-10">
                                                                    Editar
                                                                </span>
                                                            </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={canSelectMedico ? 8 : 7} className="px-6 py-12 text-center text-gray-500">
                                                    Nenhum paciente encontrado
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        }
                        mobile={
                            <div className="divide-y divide-gray-200">
                                {pacientesList.length > 0 ? (
                                    pacientesList.map((paciente) => (
                                        <div key={paciente.id} className="p-4">
                                            <div className="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                                <button
                                                    type="button"
                                                    className="w-full text-left p-3 min-h-[44px] hover:bg-gray-50 transition-colors"
                                                    onClick={() => rowClick(paciente)}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <div className="font-medium text-gray-900">{paciente.nome}</div>
                                                            <p className="text-sm text-gray-600 mt-1 tabular-nums">
                                                                Nº registro:{' '}
                                                                {paciente.codigo != null && String(paciente.codigo).trim() !== ''
                                                                    ? String(paciente.codigo).trim()
                                                                    : '—'}
                                                            </p>
                                                            <p className="text-sm text-gray-600 mt-0.5">
                                                                {paciente.cpf || '—'} · {paciente.celular || paciente.telefone1 || '—'}
                                                            </p>
                                                            <p className="text-sm text-gray-500 mt-0.5">
                                                                {paciente.cidade ? `${paciente.cidade}/${paciente.uf}` : '—'}
                                                            </p>
                                                            {canSelectMedico && (
                                                                <p className="text-sm text-gray-500 mt-0.5">
                                                                    Médico(s): {medicosLabel(paciente)}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <span className={`flex-shrink-0 px-2 py-1 text-xs font-medium rounded-full ${paciente.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                            {paciente.ativo ? 'Ativo' : 'Inativo'}
                                                        </span>
                                                    </div>
                                                </button>
                                                <div
                                                    className="flex flex-wrap items-center justify-end gap-1 px-2 py-2 border-t border-gray-100 bg-gray-50/60"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    {!isSecretaria && (
                                                        <Link
                                                            href={`/receitas?paciente_id=${paciente.id}`}
                                                            onClick={() => persistPacientesIndexQueryFromLocation()}
                                                            className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                            aria-label="Ver receitas"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </Link>
                                                    )}
                                                    {!isCallcenter && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditDrawer(paciente)}
                                                        className="min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg"
                                                        aria-label="Editar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="px-4 py-12 text-center text-gray-500">Nenhum paciente encontrado</div>
                                )}
                            </div>
                        }
                    />

                    {pacientes?.links && pacientes.links.length > 3 && (
                        <div className="px-4 sm:px-6 py-4 border-t border-gray-200 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                            <div className="text-sm text-gray-500">
                                Mostrando {pacientes.from} a {pacientes.to} de {pacientes.total}
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {pacientes.links.map((link, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => link.url && router.get(link.url)}
                                        disabled={!link.url}
                                        className={`min-h-[44px] min-w-[44px] px-3 py-1 rounded text-sm ${link.active ? 'bg-emerald-600 text-white' : link.url ? 'bg-gray-100 hover:bg-gray-200' : 'bg-gray-50 text-gray-400'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <PatientDrawer
                isOpen={drawerOpen}
                onClose={closeDrawer}
                paciente={editingPaciente}
                onSave={handleSave}
                isAdmin={isAdmin}
                showMedicoField={canSelectMedico}
                medicos={medicos}
                medicoRequired={isSecretaria}
                enableAutoSave={true}
            />

            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
        </DashboardLayout>
    );
}
