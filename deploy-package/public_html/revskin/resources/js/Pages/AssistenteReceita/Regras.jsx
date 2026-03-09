import { useState, useCallback, useMemo } from 'react';
import { router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

// Colunas da Tabela de Karnaugh
const COLUNAS = [
    { id: 'caso_clinico', title: 'Caso Clínico', width: 120, fixed: true },
    { id: 'creme_noite', title: 'Creme da Noite', width: 180 },
    { id: 'creme_dia', title: 'Creme do Dia', width: 180 },
    { id: 'creme_dia_verao', title: 'Creme Dia Verão', width: 150 },
    { id: 'creme_dia_inverno', title: 'Creme Dia Inverno', width: 150 },
    { id: 'limpeza_syndet', title: 'Limpeza Syndet', width: 180 },
    { id: 'creme_olhos', title: 'Creme dos Olhos', width: 140 },
    { id: 'base_tonalite', title: 'Base Tonalité', width: 150 },
    { id: 'serum_vitamina_c', title: 'Sérum Vit. C', width: 140 },
    { id: 'locao_clareadora', title: 'Loção Clareadora', width: 150 },
    { id: 'gel_secativo', title: 'Gel Secativo', width: 140 },
    { id: 'creme_firmador', title: 'Creme Firmador', width: 170 },
    { id: 'serum_anti_queda', title: 'Sérum Anti-Queda', width: 150 },
    { id: 'duo_mask', title: 'Duo Mask', width: 120 },
    { id: 'protetor_solar', title: 'Protetor Solar', width: 180 },
    { id: 'creme_noite_maos', title: 'Creme Noite Mãos', width: 180 },
    { id: 'creme_dia_maos', title: 'Creme Dia Mãos', width: 170 },
    { id: 'creme_corpo', title: 'Creme do Corpo', width: 140 },
];

// Componente de célula editável
function EditableCell({ value, onChange, isFirst }) {
    const [editing, setEditing] = useState(false);
    const [tempValue, setTempValue] = useState(value);

    const handleBlur = () => {
        setEditing(false);
        if (tempValue !== value) {
            onChange(tempValue);
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            handleBlur();
        }
        if (e.key === 'Escape') {
            setTempValue(value);
            setEditing(false);
        }
    };

    if (editing) {
        return (
            <input
                type="text"
                value={tempValue}
                onChange={(e) => setTempValue(e.target.value)}
                onBlur={handleBlur}
                onKeyDown={handleKeyDown}
                autoFocus
                className="w-full h-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-emerald-500 bg-white"
            />
        );
    }

    return (
        <div
            onClick={() => setEditing(true)}
            className={`px-2 py-1 text-sm cursor-text min-h-[28px] ${
                isFirst ? 'font-medium text-gray-900 bg-gray-50' : 'text-gray-700'
            } ${value ? '' : 'text-gray-400'}`}
        >
            {value || (isFirst ? '' : '-')}
        </div>
    );
}

export default function TabelaKarnaugh({ regras: regrasIniciais = [] }) {
    const [regras, setRegras] = useState(regrasIniciais);
    const [hasChanges, setHasChanges] = useState(false);
    const [saving, setSaving] = useState(false);
    const [search, setSearch] = useState('');
    const [selectedRows, setSelectedRows] = useState(new Set());

    // Filtrar regras pela busca
    const regrasFiltradas = useMemo(() => {
        if (!search.trim()) return regras;
        const termo = search.toLowerCase();
        return regras.filter(r => 
            r.caso_clinico?.toLowerCase().includes(termo) ||
            Object.values(r.produtos || {}).some(v => v?.toLowerCase().includes(termo))
        );
    }, [regras, search]);

    // Handler para edição de células
    const onCellChange = useCallback((regraId, colId, novoValor) => {
        setRegras(prev => {
            return prev.map(regra => {
                if (regra.id !== regraId) return regra;
                
                if (colId === 'caso_clinico') {
                    return { ...regra, caso_clinico: novoValor };
                }
                
                return {
                    ...regra,
                    produtos: {
                        ...regra.produtos,
                        [colId]: novoValor,
                    },
                };
            });
        });
        setHasChanges(true);
    }, []);

    // Adicionar nova linha
    const addRow = useCallback(() => {
        const novoId = Math.max(0, ...regras.map(r => r.id || 0)) + 1;
        setRegras(prev => [
            ...prev,
            {
                id: novoId,
                caso_clinico: `NOVO${novoId}`,
                produtos: {},
            },
        ]);
        setHasChanges(true);
    }, [regras]);

    // Toggle seleção de linha
    const toggleRowSelection = useCallback((id) => {
        setSelectedRows(prev => {
            const newSet = new Set(prev);
            if (newSet.has(id)) {
                newSet.delete(id);
            } else {
                newSet.add(id);
            }
            return newSet;
        });
    }, []);

    // Selecionar/desselecionar todas
    const toggleAllRows = useCallback(() => {
        if (selectedRows.size === regrasFiltradas.length) {
            setSelectedRows(new Set());
        } else {
            setSelectedRows(new Set(regrasFiltradas.map(r => r.id)));
        }
    }, [selectedRows, regrasFiltradas]);

    // Excluir linhas selecionadas
    const deleteSelectedRows = useCallback(() => {
        if (selectedRows.size === 0) return;
        if (!confirm(`Deseja excluir ${selectedRows.size} linha(s)?`)) return;

        setRegras(prev => prev.filter(r => !selectedRows.has(r.id)));
        setSelectedRows(new Set());
        setHasChanges(true);
    }, [selectedRows]);

    // Salvar alterações
    const salvar = useCallback(async () => {
        setSaving(true);
        router.post('/assistente/regras', { regras }, {
            preserveState: true,
            onSuccess: () => {
                setHasChanges(false);
                setSaving(false);
            },
            onError: () => {
                setSaving(false);
            },
        });
    }, [regras]);

    return (
        <DashboardLayout>
            <div className="p-6">
                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Tabela de Karnaugh</h1>
                        <p className="text-gray-600 mt-1">
                            Gerencie as regras de prescrição automática do assistente
                        </p>
                    </div>
                    <div className="flex gap-3">
                        {hasChanges && (
                            <span className="px-3 py-2 text-sm text-amber-700 bg-amber-50 rounded-lg flex items-center gap-2">
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Alterações não salvas
                            </span>
                        )}
                        <button
                            onClick={salvar}
                            disabled={!hasChanges || saving}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            {saving ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    Salvando...
                                </>
                            ) : (
                                <>
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    Salvar Alterações
                                </>
                            )}
                        </button>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="bg-white rounded-t-xl border border-b-0 border-gray-200 p-4 flex justify-between items-center">
                    <div className="flex gap-3">
                        <button
                            onClick={addRow}
                            className="px-3 py-1.5 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors flex items-center gap-1"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            Nova Linha
                        </button>
                        <button
                            onClick={deleteSelectedRows}
                            disabled={selectedRows.size === 0}
                            className="px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Excluir ({selectedRows.size})
                        </button>
                    </div>
                    <div className="flex items-center gap-4">
                        <span className="text-sm text-gray-500">
                            {regrasFiltradas.length} de {regras.length} regras
                        </span>
                        <input
                            type="text"
                            placeholder="Buscar..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 w-64"
                        />
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white border border-gray-200 rounded-b-xl overflow-hidden">
                    <div className="overflow-x-auto" style={{ maxHeight: 'calc(100vh - 380px)' }}>
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th className="w-10 px-3 py-3 bg-gray-50">
                                        <input
                                            type="checkbox"
                                            checked={selectedRows.size === regrasFiltradas.length && regrasFiltradas.length > 0}
                                            onChange={toggleAllRows}
                                            className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                        />
                                    </th>
                                    <th className="w-10 px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                                        #
                                    </th>
                                    {COLUNAS.map((col) => (
                                        <th
                                            key={col.id}
                                            className="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 whitespace-nowrap"
                                            style={{ minWidth: col.width }}
                                        >
                                            {col.title}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-100">
                                {regrasFiltradas.length > 0 ? (
                                    regrasFiltradas.map((regra, index) => (
                                        <tr 
                                            key={regra.id} 
                                            className={`hover:bg-gray-50 ${selectedRows.has(regra.id) ? 'bg-emerald-50' : ''}`}
                                        >
                                            <td className="px-3 py-1">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedRows.has(regra.id)}
                                                    onChange={() => toggleRowSelection(regra.id)}
                                                    className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                                />
                                            </td>
                                            <td className="px-3 py-1 text-xs text-gray-400">
                                                {index + 1}
                                            </td>
                                            {COLUNAS.map((col, colIndex) => (
                                                <td key={col.id} className="border-r border-gray-100 last:border-r-0">
                                                    <EditableCell
                                                        value={col.id === 'caso_clinico' ? regra.caso_clinico : regra.produtos?.[col.id] || ''}
                                                        onChange={(val) => onCellChange(regra.id, col.id, val)}
                                                        isFirst={colIndex === 0}
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={COLUNAS.length + 2} className="px-6 py-12 text-center">
                                            <div className="text-gray-500">
                                                <svg className="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p className="font-medium">Nenhuma regra encontrada</p>
                                                <p className="text-sm mt-1">Clique em "Nova Linha" para adicionar uma regra</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Legenda */}
                <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 className="text-sm font-medium text-blue-900 mb-2">Legenda dos Códigos de Caso Clínico</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-blue-800">
                        <div>
                            <strong>P</strong> = Pele <br/>
                            <span className="text-xs">SM = Seca/Mista, O = Oleosa</span>
                        </div>
                        <div>
                            <strong>R</strong> = Rugas <br/>
                            <span className="text-xs">1 = Leve, 2 = Moderado, 3 = Intenso</span>
                        </div>
                        <div>
                            <strong>A</strong> = Acne <br/>
                            <span className="text-xs">1 = Leve, 2 = Moderado, 3 = Intenso</span>
                        </div>
                        <div>
                            <strong>M</strong> = Manchas <br/>
                            <span className="text-xs">Quando presente no código</span>
                        </div>
                    </div>
                </div>

                {/* Instruções */}
                <div className="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <h3 className="text-sm font-medium text-gray-900 mb-2">Como usar</h3>
                    <ul className="text-sm text-gray-600 space-y-1">
                        <li>• Clique em qualquer célula para editar</li>
                        <li>• Pressione <kbd className="px-1.5 py-0.5 bg-gray-200 rounded text-xs">Enter</kbd> para confirmar ou <kbd className="px-1.5 py-0.5 bg-gray-200 rounded text-xs">Esc</kbd> para cancelar</li>
                        <li>• Selecione linhas usando os checkboxes à esquerda</li>
                        <li>• Não esqueça de salvar as alterações!</li>
                    </ul>
                </div>
            </div>
        </DashboardLayout>
    );
}
