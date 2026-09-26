import { useState } from 'react';
import { nomeExibicaoSemTitulo } from '@/utils/nomeExibicao';

const CAMPOS = ['indicado_por', 'codigo', 'anotacoes'];

const inputClass = 'w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500';

function PencilIcon() {
    return (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
    );
}

function DiskIcon() {
    return (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3h11l5 5v11a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 3v5h8V3M7 21v-7h10v7" />
        </svg>
    );
}

function XIcon() {
    return (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

/**
 * Card de um vínculo médico↔paciente (campos privados do médico) no drawer do paciente.
 * Admin pode editar inline: lápis abre os campos, disquete salva direto no vínculo.
 */
export default function VinculoMedicoCard({ pacienteId, vinculo, csrfToken, editable = false, onSaved }) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState({});
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    const startEdit = () => {
        setDraft(Object.fromEntries(CAMPOS.map((c) => [c, vinculo[c] ?? ''])));
        setErrors({});
        setEditing(true);
    };

    const cancel = () => {
        setEditing(false);
        setErrors({});
    };

    const save = async () => {
        if (saving) return;
        setSaving(true);
        setErrors({});
        try {
            const response = await fetch(`/api/pacientes/${pacienteId}/vinculos/${vinculo.medico_id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(draft),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errs = Object.fromEntries(Object.entries(body?.errors || {}).map(([k, v]) => [k, v?.[0]]));
                setErrors(Object.keys(errs).length ? errs : { geral: body?.message || 'Não foi possível salvar.' });
                return;
            }
            onSaved?.(body.privados_por_medico);
            setEditing(false);
        } catch {
            setErrors({ geral: 'Não foi possível salvar. Verifique a conexão.' });
        } finally {
            setSaving(false);
        }
    };

    const onKeyDown = (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            cancel();
        } else if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            // Não deixa o Enter submeter o formulário do drawer.
            e.preventDefault();
            save();
        }
    };

    const setCampo = (campo) => (e) => {
        setDraft((prev) => ({ ...prev, [campo]: e.target.value }));
        setErrors((prev) => ({ ...prev, [campo]: null }));
    };

    const valor = (campo) => (vinculo[campo]?.trim() ? vinculo[campo] : '—');

    const rowClass = `grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-1 sm:gap-3 ${editing ? 'sm:items-start' : ''}`;
    const dtClass = `text-gray-500 ${editing ? 'sm:pt-2' : ''}`;

    const iconButton = 'p-1.5 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-200 disabled:opacity-50';

    return (
        <div className="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-3">
            <div className="flex items-center justify-between gap-2">
                <h4 className="text-sm font-semibold text-gray-900">
                    {nomeExibicaoSemTitulo(vinculo.medico_nome) || `Médico #${vinculo.medico_id}`}
                </h4>
                <div className="flex items-center gap-2">
                    {vinculo.ativo === false && (
                        <span className="text-xs font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded">
                            Vínculo inativo
                        </span>
                    )}
                    {editable && !editing && (
                        <button type="button" onClick={startEdit} className={iconButton} title="Editar" aria-label="Editar dados do médico">
                            <PencilIcon />
                        </button>
                    )}
                    {editing && (
                        <>
                            <button type="button" onClick={cancel} disabled={saving} className={iconButton} title="Cancelar" aria-label="Cancelar edição">
                                <XIcon />
                            </button>
                            <button
                                type="button"
                                onClick={save}
                                disabled={saving}
                                className="p-1.5 rounded-md text-blue-600 hover:text-blue-800 hover:bg-blue-50 disabled:opacity-50"
                                title="Salvar"
                                aria-label="Salvar dados do médico"
                            >
                                <DiskIcon />
                            </button>
                        </>
                    )}
                </div>
            </div>
            <dl className="space-y-2 text-sm" onKeyDown={editing ? onKeyDown : undefined}>
                <div className={rowClass}>
                    <dt className={dtClass}>Indicado por</dt>
                    <dd className="text-gray-900">
                        {editing ? (
                            <input type="text" className={inputClass} value={draft.indicado_por} onChange={setCampo('indicado_por')} maxLength={255} autoComplete="off" autoFocus />
                        ) : valor('indicado_por')}
                    </dd>
                </div>
                <div className={rowClass}>
                    <dt className={dtClass}>Nº Registro</dt>
                    <dd className="text-gray-900 tabular-nums">
                        {editing ? (
                            <>
                                <input type="text" className={inputClass} value={draft.codigo} onChange={setCampo('codigo')} maxLength={255} autoComplete="off" />
                                {errors.codigo && <p className="mt-1 text-xs text-red-600">{errors.codigo}</p>}
                            </>
                        ) : valor('codigo')}
                    </dd>
                </div>
                <div className={rowClass}>
                    <dt className={dtClass}>Observações</dt>
                    <dd className="text-gray-900 whitespace-pre-wrap">
                        {editing ? (
                            <textarea className={inputClass} rows={4} value={draft.anotacoes} onChange={setCampo('anotacoes')} />
                        ) : valor('anotacoes')}
                    </dd>
                </div>
            </dl>
            {(errors.geral || errors.indicado_por || errors.anotacoes) && (
                <p className="text-xs text-red-600">{errors.geral || errors.indicado_por || errors.anotacoes}</p>
            )}
        </div>
    );
}
