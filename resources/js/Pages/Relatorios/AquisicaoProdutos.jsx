import { Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import ResponsiveEntityList from '@/Components/ResponsiveEntityList';
import ExportConfirmModal from '@/Components/ExportConfirmModal';
import Toast from '@/Components/Toast';
import debounce from 'lodash/debounce';

export default function AquisicaoProdutos({ medicos, pacientes, produtos, dados, filters, isAdmin, isMedico }) {
    const { auth, flash } = usePage().props || {};
    const [toast, setToast] = useState(null);
    const [exportModal, setExportModal] = useState({ open: false, format: null });

    useEffect(() => {
        if (flash?.success) setToast({ message: flash.success, type: 'success' });
        if (flash?.error) setToast({ message: flash.error, type: 'error' });
    }, [flash?.success, flash?.error]);
    // Calcular datas padrão (última semana)
    const getDefaultDates = () => {
        const hoje = new Date();
        const umaSemanaAtras = new Date();
        umaSemanaAtras.setDate(hoje.getDate() - 7);
        
        return {
            inicio: umaSemanaAtras.toISOString().split('T')[0],
            fim: hoje.toISOString().split('T')[0],
        };
    };

    const defaultDates = getDefaultDates();
    
    const [dataInicio, setDataInicio] = useState(filters?.data_inicio || defaultDates.inicio);
    const [dataFim, setDataFim] = useState(filters?.data_fim || defaultDates.fim);
    const [showMoreFilters, setShowMoreFilters] = useState(false);
    
    // Estados para multi-select de Médicos
    const [searchMedico, setSearchMedico] = useState('');
    const [medicoResults, setMedicoResults] = useState([]);
    const [showMedicoDropdown, setShowMedicoDropdown] = useState(false);
    const [selectedMedicos, setSelectedMedicos] = useState([]);
    const [loadingMedicos, setLoadingMedicos] = useState(false);
    
    // Estados para multi-select de Pacientes
    const [searchPaciente, setSearchPaciente] = useState('');
    const [pacienteResults, setPacienteResults] = useState([]);
    const [showPacienteDropdown, setShowPacienteDropdown] = useState(false);
    const [selectedPacientes, setSelectedPacientes] = useState([]);
    const [loadingPacientes, setLoadingPacientes] = useState(false);
    
    // Estados para multi-select de Produtos
    const [searchProduto, setSearchProduto] = useState('');
    const [produtoResults, setProdutoResults] = useState([]);
    const [showProdutoDropdown, setShowProdutoDropdown] = useState(false);
    const [selectedProdutos, setSelectedProdutos] = useState([]);
    const [loadingProdutos, setLoadingProdutos] = useState(false);

    // Sincronizar estado com filters quando mudarem
    useEffect(() => {
        setDataInicio(filters?.data_inicio || defaultDates.inicio);
        setDataFim(filters?.data_fim || defaultDates.fim);
        
        // Inicializar seleções de filtros múltiplos se vierem dos filters
        if (filters?.medico_ids && Array.isArray(filters.medico_ids) && filters.medico_ids.length > 0) {
            const medicosSelecionados = medicos?.filter(m => filters.medico_ids.includes(m.id)) || [];
            setSelectedMedicos(medicosSelecionados);
        } else {
            setSelectedMedicos([]);
        }
        
        if (filters?.paciente_ids && Array.isArray(filters.paciente_ids) && filters.paciente_ids.length > 0) {
            const pacientesSelecionados = pacientes?.filter(p => filters.paciente_ids.includes(p.id)) || [];
            setSelectedPacientes(pacientesSelecionados);
        } else {
            setSelectedPacientes([]);
        }
        
        if (filters?.produto_ids && Array.isArray(filters.produto_ids) && filters.produto_ids.length > 0) {
            const produtosSelecionados = produtos?.filter(pr => filters.produto_ids.includes(pr.id)) || [];
            setSelectedProdutos(produtosSelecionados);
        } else {
            setSelectedProdutos([]);
        }
    }, [filters, medicos, pacientes, produtos]);

    // Busca de médicos
    const searchMedicosApi = useCallback(
        debounce(async (term) => {
            if (term.length < 2) {
                setMedicoResults([]);
                setShowMedicoDropdown(false);
                return;
            }
            setLoadingMedicos(true);
            try {
                const response = await fetch(`/api/medicos/search?q=${encodeURIComponent(term)}`);
                const results = await response.json();
                const filtered = results.filter(m => !selectedMedicos.find(s => s.id === m.id));
                setMedicoResults(filtered);
                setShowMedicoDropdown(true);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingMedicos(false);
            }
        }, 300),
        [selectedMedicos]
    );

    useEffect(() => {
        if (searchMedico) {
            searchMedicosApi(searchMedico);
        }
    }, [searchMedico, searchMedicosApi]);

    // Busca de pacientes
    const searchPacientesApi = useCallback(
        debounce(async (term) => {
            if (term.length < 2) {
                setPacienteResults([]);
                setShowPacienteDropdown(false);
                return;
            }
            setLoadingPacientes(true);
            try {
                const response = await fetch(`/api/pacientes/search?q=${encodeURIComponent(term)}`);
                const results = await response.json();
                const filtered = results.filter(p => !selectedPacientes.find(s => s.id === p.id));
                setPacienteResults(filtered);
                setShowPacienteDropdown(true);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingPacientes(false);
            }
        }, 300),
        [selectedPacientes]
    );

    useEffect(() => {
        if (searchPaciente) {
            searchPacientesApi(searchPaciente);
        }
    }, [searchPaciente, searchPacientesApi]);

    // Busca de produtos
    const searchProdutosApi = useCallback(
        debounce(async (term) => {
            if (term.length < 2) {
                setProdutoResults([]);
                setShowProdutoDropdown(false);
                return;
            }
            setLoadingProdutos(true);
            try {
                const response = await fetch(`/api/produtos/search?q=${encodeURIComponent(term)}`);
                const results = await response.json();
                const filtered = results.filter(pr => !selectedProdutos.find(s => s.id === pr.id));
                setProdutoResults(filtered);
                setShowProdutoDropdown(true);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingProdutos(false);
            }
        }, 300),
        [selectedProdutos]
    );

    useEffect(() => {
        if (searchProduto) {
            searchProdutosApi(searchProduto);
        }
    }, [searchProduto, searchProdutosApi]);

    // Funções para adicionar/remover seleções
    const addMedico = (medico) => {
        const newMedicos = [...selectedMedicos, medico];
        setSelectedMedicos(newMedicos);
        setSearchMedico('');
        setShowMedicoDropdown(false);
    };

    const removeMedico = (medicoId) => {
        setSelectedMedicos(selectedMedicos.filter(m => m.id !== medicoId));
    };

    const addPaciente = (paciente) => {
        const newPacientes = [...selectedPacientes, paciente];
        setSelectedPacientes(newPacientes);
        setSearchPaciente('');
        setShowPacienteDropdown(false);
    };

    const removePaciente = (pacienteId) => {
        setSelectedPacientes(selectedPacientes.filter(p => p.id !== pacienteId));
    };

    const addProduto = (produto) => {
        const newProdutos = [...selectedProdutos, produto];
        setSelectedProdutos(newProdutos);
        setSearchProduto('');
        setShowProdutoDropdown(false);
    };

    const removeProduto = (produtoId) => {
        setSelectedProdutos(selectedProdutos.filter(pr => pr.id !== produtoId));
    };

    const handleFiltrar = (e) => {
        e.preventDefault();
        const params = {
            data_inicio: dataInicio || defaultDates.inicio,
            data_fim: dataFim || defaultDates.fim,
        };
        
        if (isAdmin && selectedMedicos.length > 0) {
            params.medico_ids = selectedMedicos.map(m => m.id);
        }
        
        if (selectedPacientes.length > 0) {
            params.paciente_ids = selectedPacientes.map(p => p.id);
        }
        
        if (selectedProdutos.length > 0) {
            params.produto_ids = selectedProdutos.map(pr => pr.id);
        }
        
        router.get('/relatorios/aquisicao-produtos', params, { preserveState: true });
    };

    const openExportModal = (format) => {
        setExportModal({ open: true, format });
    };

    const handleExportConfirm = (extraEmails) => {
        const payload = {
            format: exportModal.format,
            data_inicio: dataInicio || defaultDates.inicio,
            data_fim: dataFim || defaultDates.fim,
            medico_ids: isAdmin && selectedMedicos.length > 0 ? selectedMedicos.map(m => m.id) : [],
            paciente_ids: selectedPacientes.length > 0 ? selectedPacientes.map(p => p.id) : [],
            produto_ids: selectedProdutos.length > 0 ? selectedProdutos.map(pr => pr.id) : [],
            extra_emails: extraEmails,
        };

        router.post('/relatorios/aquisicao-produtos/export', payload, {
            preserveScroll: true,
            onSuccess: () => {
                setExportModal({ open: false, format: null });
                setToast({
                    message: 'Pedido registrado. Você receberá um e-mail com o link para download quando o arquivo estiver pronto.',
                    type: 'success'
                });
            },
            onError: (errors) => {
                setToast({
                    message: Object.values(errors).flat().find(Boolean) || 'Erro ao solicitar exportação.',
                    type: 'error'
                });
            }
        });
    };

    const getItemCount = () => {
        if (!dados?.pacientes?.length) return 0;
        const productsSum = dados.pacientes.reduce((acc, p) => acc + (p.produtos?.length || 0), 0);
        return productsSum > 0 ? productsSum : dados.pacientes.length;
    };

    const buildExcelDownloadUrl = () => {
        const params = new URLSearchParams();
        params.set('data_inicio', dataInicio || defaultDates.inicio);
        params.set('data_fim', dataFim || defaultDates.fim);
        if (isAdmin && selectedMedicos.length > 0) {
            selectedMedicos.forEach((m) => params.append('medico_ids[]', String(m.id)));
        }
        if (selectedPacientes.length > 0) {
            selectedPacientes.forEach((p) => params.append('paciente_ids[]', String(p.id)));
        }
        if (selectedProdutos.length > 0) {
            selectedProdutos.forEach((pr) => params.append('produto_ids[]', String(pr.id)));
        }
        return `/relatorios/aquisicao-produtos/excel?${params.toString()}`;
    };

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        }).format(value);
    };

    const formatPhone = (phone) => {
        if (!phone) return '';
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length === 11) {
            return `(${cleaned.substring(0, 2)}) ${cleaned.substring(2, 7)}-${cleaned.substring(7)}`;
        }
        return phone;
    };

    const formatCpf = (cpf) => {
        if (!cpf) return '';
        const cleaned = String(cpf).replace(/\D/g, '');
        if (cleaned.length === 11) {
            return `${cleaned.substring(0, 3)}.${cleaned.substring(3, 6)}.${cleaned.substring(6, 9)}-${cleaned.substring(9)}`;
        }
        return cpf;
    };

    const parseDdMmYyyy = (str) => {
        if (!str) return 0;
        const [dd, mm, yyyy] = String(str).split('/');
        return new Date(yyyy, (parseInt(mm, 10) || 1) - 1, parseInt(dd, 10) || 1).getTime();
    };

    const ultimaModificacaoPorProduto = (produtos) => {
        if (!produtos?.length) return {};
        const map = {};
        produtos.forEach((p) => {
            const nome = p.produto_nome;
            const d = p.data_receita;
            const t = parseDdMmYyyy(d);
            if (!map[nome] || t > (map[nome].t || 0)) map[nome] = { t, data: d };
        });
        const out = {};
        Object.keys(map).forEach((k) => { out[k] = map[k].data; });
        return out;
    };

    return (
        <DashboardLayout>
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
            <ExportConfirmModal
                open={exportModal.open}
                onClose={() => setExportModal({ open: false, format: null })}
                onConfirm={handleExportConfirm}
                itemCount={getItemCount()}
                itemLabel="itens"
                formatLabel={exportModal.format === 'pdf' ? 'PDF' : 'Excel'}
                defaultEmail={auth?.user?.email}
                title={exportModal.format === 'pdf' ? 'Enviar PDF por e-mail' : 'Enviar Excel por e-mail'}
            />
            <div className="py-4 lg:py-6 px-0">
                <Link
                    href="/relatorios"
                    className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm mb-4"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar para Relatórios
                </Link>
                <PageHeader
                    title="Relatório de Aquisição de Produtos"
                    description="Relatório detalhado de produtos adquiridos por paciente"
                />

                {/* Filtros */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <form onSubmit={handleFiltrar}>
                        {/* Datas primeiro */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Data Início
                                </label>
                                <input
                                    type="date"
                                    value={dataInicio}
                                    onChange={(e) => setDataInicio(e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Data Fim
                                </label>
                                <input
                                    type="date"
                                    value={dataFim}
                                    onChange={(e) => setDataFim(e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div className="flex items-end">
                                <button
                                    type="submit"
                                    className="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors cursor-pointer"
                                >
                                    Filtrar
                                </button>
                            </div>
                        </div>

                        {/* Mais Filtros */}
                        <div className="pt-4">
                            <button
                                type="button"
                                onClick={() => setShowMoreFilters(!showMoreFilters)}
                                className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 mb-4"
                            >
                                <svg 
                                    className={`w-4 h-4 transition-transform ${showMoreFilters ? 'rotate-180' : ''}`} 
                                    fill="none" 
                                    viewBox="0 0 24 24" 
                                    stroke="currentColor"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                                {showMoreFilters ? 'Ocultar filtros' : 'Mais filtros'}
                            </button>

                            {showMoreFilters && (
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    {/* Médico Multi-select */}
                                    {isAdmin && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Médico
                                            </label>
                                            
                                            {/* Selected Medicos */}
                                            {selectedMedicos.length > 0 && (
                                                <div className="flex flex-wrap gap-2 mb-2">
                                                    {selectedMedicos.map((medico) => (
                                                        <div
                                                            key={medico.id}
                                                            className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-100 text-blue-800 rounded-full text-sm"
                                                        >
                                                            <span>{medico.nome}</span>
                                                            {medico.crm && <span className="text-blue-600 text-xs">CRM: {medico.crm}</span>}
                                                            <button
                                                                type="button"
                                                                onClick={() => removeMedico(medico.id)}
                                                                className="text-blue-600 hover:text-blue-800"
                                                            >
                                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                            
                                            {/* Medico Search */}
                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    placeholder="Buscar médico pelo nome ou CRM..."
                                                    value={searchMedico}
                                                    onChange={(e) => setSearchMedico(e.target.value)}
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                />
                                                {loadingMedicos && (
                                                    <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                                        <svg className="animate-spin h-5 w-5 text-gray-400" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                        </svg>
                                                    </div>
                                                )}
                                                {showMedicoDropdown && medicoResults.length > 0 && (
                                                    <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-auto">
                                                        {medicoResults.map((medico) => (
                                                            <button
                                                                key={medico.id}
                                                                type="button"
                                                                onClick={() => addMedico(medico)}
                                                                className="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                                            >
                                                                <div className="font-medium text-gray-900">{medico.nome}</div>
                                                                <div className="text-sm text-gray-500">{medico.crm} - {medico.especialidade}</div>
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Paciente Multi-select */}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Paciente
                                        </label>
                                        
                                        {/* Selected Pacientes */}
                                        {selectedPacientes.length > 0 && (
                                            <div className="flex flex-wrap gap-2 mb-2">
                                                {selectedPacientes.map((paciente) => (
                                                    <div
                                                        key={paciente.id}
                                                        className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-100 text-blue-800 rounded-full text-sm"
                                                    >
                                                        <span>{paciente.nome}</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => removePaciente(paciente.id)}
                                                            className="text-blue-600 hover:text-blue-800"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        
                                        {/* Paciente Search */}
                                        <div className="relative">
                                            <input
                                                type="text"
                                                placeholder="Buscar paciente pelo nome ou CPF..."
                                                value={searchPaciente}
                                                onChange={(e) => setSearchPaciente(e.target.value)}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            />
                                            {loadingPacientes && (
                                                <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                                    <svg className="animate-spin h-5 w-5 text-gray-400" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                    </svg>
                                                </div>
                                            )}
                                            {showPacienteDropdown && pacienteResults.length > 0 && (
                                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-auto">
                                                    {pacienteResults.map((paciente) => (
                                                        <button
                                                            key={paciente.id}
                                                            type="button"
                                                            onClick={() => addPaciente(paciente)}
                                                            className="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                                        >
                                                            <div className="font-medium text-gray-900">{paciente.nome}</div>
                                                            {paciente.cpf && <div className="text-sm text-gray-500">CPF: {paciente.cpf}</div>}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Produto Multi-select */}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Produto
                                        </label>
                                        
                                        {/* Selected Produtos */}
                                        {selectedProdutos.length > 0 && (
                                            <div className="flex flex-wrap gap-2 mb-2">
                                                {selectedProdutos.map((produto) => (
                                                    <div
                                                        key={produto.id}
                                                        className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-100 text-blue-800 rounded-full text-sm"
                                                    >
                                                        <span>{produto.nome || produto.codigo}</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeProduto(produto.id)}
                                                            className="text-blue-600 hover:text-blue-800"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        
                                        {/* Produto Search */}
                                        <div className="relative">
                                            <input
                                                type="text"
                                                placeholder="Buscar produto pelo nome ou código..."
                                                value={searchProduto}
                                                onChange={(e) => setSearchProduto(e.target.value)}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            />
                                            {loadingProdutos && (
                                                <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                                    <svg className="animate-spin h-5 w-5 text-gray-400" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                    </svg>
                                                </div>
                                            )}
                                            {showProdutoDropdown && produtoResults.length > 0 && (
                                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-auto">
                                                    {produtoResults.map((produto) => (
                                                        <button
                                                            key={produto.id}
                                                            type="button"
                                                            onClick={() => addProduto(produto)}
                                                            className="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                                        >
                                                            <div className="font-medium text-gray-900">{produto.nome}</div>
                                                            {produto.codigo && <div className="text-sm text-gray-500">Código: {produto.codigo}</div>}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </form>
                </div>

                {/* Resultados */}
                {dados && (
                    <>
                        {/* Relatório agrupado por paciente */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            {/* Header com botões de exportação */}
                            {dados && (
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between p-4 border-b border-gray-200">
                                    <div className="hidden lg:block" />
                                    <div className="flex flex-col sm:flex-row flex-wrap gap-2 w-full lg:w-auto lg:justify-end">
                                        <button
                                            type="button"
                                            onClick={() => window.location.assign(buildExcelDownloadUrl())}
                                            className="min-h-[44px] w-full sm:w-auto justify-center px-3 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-1 cursor-pointer"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Baixar Excel
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => openExportModal('xlsx')}
                                            className="min-h-[44px] w-full sm:w-auto justify-center px-3 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-colors flex items-center gap-1 cursor-pointer"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            Enviar Excel por e-mail
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => openExportModal('pdf')}
                                            className="min-h-[44px] w-full sm:w-auto justify-center px-3 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-colors flex items-center gap-1 cursor-pointer"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            Enviar PDF por e-mail
                                        </button>
                                    </div>
                                </div>
                            )}
                            
                            {dados && dados.pacientes && dados.pacientes.length > 0 && (
                                <div className="p-6 space-y-6">
                                    {dados.pacientes?.map((pacienteData, index) => (
                                        <div key={pacienteData.paciente.id} className="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                                            {/* Cabeçalho do Paciente: Nome, CPF, Dra. (só admin) */}
                                            <div className="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                                                    <h3 className="text-lg font-semibold text-gray-900">
                                                        {pacienteData.paciente.nome}
                                                    </h3>
                                                    {pacienteData.paciente.cpf && (
                                                        <span className="text-sm text-gray-600">
                                                            CPF: {formatCpf(pacienteData.paciente.cpf)}
                                                        </span>
                                                    )}
                                                    {isAdmin && pacienteData.paciente.medico_nome && (
                                                        <span className="text-sm text-gray-600">
                                                            Dra. {pacienteData.paciente.medico_nome}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {(() => {
                                                const ultimaMod = ultimaModificacaoPorProduto(pacienteData.produtos);
                                                return (
                                                    <ResponsiveEntityList
                                                        desktop={
                                                            <div className="overflow-x-auto">
                                                                <table className="min-w-full divide-y divide-gray-200">
                                                                    <thead className="bg-gray-50">
                                                                        <tr>
                                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                                Produto
                                                                            </th>
                                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                                Última Modificação
                                                                            </th>
                                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                                Aquisições no Período
                                                                            </th>
                                                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                                Qtd
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody className="bg-white divide-y divide-gray-200">
                                                                        {pacienteData.produtos?.map((produto, prodIndex) => (
                                                                            <tr key={prodIndex} className="hover:bg-gray-50">
                                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                                    {produto.produto_nome}
                                                                                </td>
                                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                                    {ultimaMod[produto.produto_nome] ?? produto.data_receita}
                                                                                </td>
                                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                                    {produto.data_aquisicao}
                                                                                </td>
                                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                                                    {produto.quantidade}
                                                                                </td>
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        }
                                                        mobile={
                                                            <div className="divide-y divide-gray-200 bg-white">
                                                                {pacienteData.produtos?.map((produto, prodIndex) => (
                                                                    <div key={prodIndex} className="p-4 max-w-full">
                                                                        <div className="font-medium text-gray-900 break-words">{produto.produto_nome}</div>
                                                                        <dl className="mt-2 space-y-1 text-sm text-gray-600">
                                                                            <div>
                                                                                <dt className="inline text-gray-500">Últ. modificação: </dt>
                                                                                <dd className="inline">{ultimaMod[produto.produto_nome] ?? produto.data_receita}</dd>
                                                                            </div>
                                                                            <div>
                                                                                <dt className="inline text-gray-500">Aquisição no período: </dt>
                                                                                <dd className="inline">{produto.data_aquisicao}</dd>
                                                                            </div>
                                                                            <div>
                                                                                <dt className="inline text-gray-500">Qtd: </dt>
                                                                                <dd className="inline font-medium text-gray-900">{produto.quantidade}</dd>
                                                                            </div>
                                                                        </dl>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        }
                                                    />
                                                );
                                            })()}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </>
                )}

                {!dados && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Selecione os Filtros</h3>
                        <p className="text-gray-500">
                            Escolha o período e outros filtros para gerar o relatório
                        </p>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
