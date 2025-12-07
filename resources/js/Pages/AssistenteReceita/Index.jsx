import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function AssistenteReceitaIndex({ casos, pacientes }) {
    const [step, setStep] = useState(1);
    const [searchPaciente, setSearchPaciente] = useState('');
    const [showPacienteDropdown, setShowPacienteDropdown] = useState(false);
    const [selectedPaciente, setSelectedPaciente] = useState(null);
    const [selectedCasos, setSelectedCasos] = useState([]);
    const [tratamentosSugeridos, setTratamentosSugeridos] = useState([]);
    const [loading, setLoading] = useState(false);

    const filteredPacientes = searchPaciente.length >= 2
        ? pacientes?.filter(p => 
            p.nome.toLowerCase().includes(searchPaciente.toLowerCase()) ||
            p.cpf.includes(searchPaciente)
          ).slice(0, 10)
        : [];

    const selectPaciente = (paciente) => {
        setSelectedPaciente(paciente);
        setSearchPaciente('');
        setShowPacienteDropdown(false);
    };

    const toggleCaso = (casoId) => {
        setSelectedCasos(prev => 
            prev.includes(casoId)
                ? prev.filter(id => id !== casoId)
                : [...prev, casoId]
        );
    };

    const processarCasos = async () => {
        if (selectedCasos.length === 0) return;
        
        setLoading(true);
        try {
            const response = await fetch('/assistente-receita/processar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({ casos: selectedCasos }),
            });
            const data = await response.json();
            setTratamentosSugeridos(data.tratamentos || []);
            setStep(3);
        } catch (error) {
            console.error('Erro ao processar casos:', error);
        } finally {
            setLoading(false);
        }
    };

    const gerarReceita = async () => {
        if (!selectedPaciente || tratamentosSugeridos.length === 0) return;

        setLoading(true);
        try {
            const response = await fetch('/assistente-receita/gerar-receita', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                    paciente_id: selectedPaciente.id,
                    tratamentos: tratamentosSugeridos,
                }),
            });
            const data = await response.json();
            if (data.receita_id) {
                router.visit(`/receitas/${data.receita_id}/edit`);
            }
        } catch (error) {
            console.error('Erro ao gerar receita:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-4xl mx-auto">
                <div className="mb-6">
                    <Link
                        href="/receitas"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Receitas
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Assistente de Receitas</h1>
                    <p className="text-gray-600 mt-1">
                        Selecione os casos clínicos para gerar uma receita automaticamente
                    </p>
                </div>

                {/* Progress Steps */}
                <div className="flex items-center justify-center mb-8">
                    {[1, 2, 3].map((s) => (
                        <div key={s} className="flex items-center">
                            <div className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold ${
                                step >= s ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-500'
                            }`}>
                                {s}
                            </div>
                            {s < 3 && (
                                <div className={`w-20 h-1 ${step > s ? 'bg-emerald-600' : 'bg-gray-200'}`} />
                            )}
                        </div>
                    ))}
                </div>

                <div className="flex justify-center gap-8 text-sm text-gray-600 mb-8">
                    <span className={step === 1 ? 'text-emerald-600 font-medium' : ''}>Paciente</span>
                    <span className={step === 2 ? 'text-emerald-600 font-medium' : ''}>Casos Clínicos</span>
                    <span className={step === 3 ? 'text-emerald-600 font-medium' : ''}>Tratamentos</span>
                </div>

                {/* Step 1: Selecionar Paciente */}
                {step === 1 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Selecione o Paciente</h2>
                        
                        {selectedPaciente ? (
                            <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6">
                                <div>
                                    <div className="font-medium text-gray-900">{selectedPaciente.nome}</div>
                                    <div className="text-sm text-gray-500">{selectedPaciente.cpf}</div>
                                </div>
                                <button
                                    onClick={() => setSelectedPaciente(null)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        ) : (
                            <div className="relative mb-6">
                                <input
                                    type="text"
                                    placeholder="Digite o nome ou CPF do paciente..."
                                    value={searchPaciente}
                                    onChange={(e) => {
                                        setSearchPaciente(e.target.value);
                                        setShowPacienteDropdown(true);
                                    }}
                                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {showPacienteDropdown && filteredPacientes.length > 0 && (
                                    <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                                        {filteredPacientes.map((paciente) => (
                                            <button
                                                key={paciente.id}
                                                type="button"
                                                onClick={() => selectPaciente(paciente)}
                                                className="w-full text-left px-4 py-3 hover:bg-gray-50 border-b last:border-0"
                                            >
                                                <div className="font-medium text-gray-900">{paciente.nome}</div>
                                                <div className="text-sm text-gray-500">{paciente.cpf}</div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex justify-end">
                            <button
                                onClick={() => setStep(2)}
                                disabled={!selectedPaciente}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Próximo
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 2: Selecionar Casos Clínicos */}
                {step === 2 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Selecione os Casos Clínicos</h2>
                        <p className="text-gray-500 mb-6">
                            Marque os casos clínicos que se aplicam ao paciente
                        </p>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            {casos?.map((caso) => (
                                <label
                                    key={caso.id}
                                    className={`flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition-all ${
                                        selectedCasos.includes(caso.id)
                                            ? 'border-emerald-500 bg-emerald-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedCasos.includes(caso.id)}
                                        onChange={() => toggleCaso(caso.id)}
                                        className="mt-1 w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                    />
                                    <div>
                                        <div className="font-medium text-gray-900">{caso.nome}</div>
                                        {caso.descricao && (
                                            <div className="text-sm text-gray-500 mt-1">{caso.descricao}</div>
                                        )}
                                    </div>
                                </label>
                            ))}
                        </div>

                        {casos?.length === 0 && (
                            <div className="text-center py-8 text-gray-500">
                                <p>Nenhum caso clínico cadastrado</p>
                                <Link
                                    href="/assistente/regras"
                                    className="text-emerald-600 hover:text-emerald-700 text-sm mt-2 inline-block"
                                >
                                    Configurar casos clínicos →
                                </Link>
                            </div>
                        )}

                        <div className="flex justify-between">
                            <button
                                onClick={() => setStep(1)}
                                className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Voltar
                            </button>
                            <button
                                onClick={processarCasos}
                                disabled={selectedCasos.length === 0 || loading}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {loading ? 'Processando...' : 'Processar'}
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 3: Tratamentos Sugeridos */}
                {step === 3 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Tratamentos Sugeridos</h2>
                        <p className="text-gray-500 mb-6">
                            Revise os tratamentos sugeridos e gere a receita
                        </p>

                        {tratamentosSugeridos.length > 0 ? (
                            <div className="space-y-4 mb-6">
                                {tratamentosSugeridos.map((tratamento, index) => (
                                    <div key={index} className="border border-gray-200 rounded-lg p-4">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="font-medium text-gray-900">
                                                {tratamento.produto?.nome || 'Produto'}
                                            </span>
                                            <span className="text-emerald-600 font-medium">
                                                {new Intl.NumberFormat('pt-BR', {
                                                    style: 'currency',
                                                    currency: 'BRL',
                                                }).format(tratamento.preco || 0)}
                                            </span>
                                        </div>
                                        {tratamento.posologia && (
                                            <p className="text-sm text-gray-600">{tratamento.posologia}</p>
                                        )}
                                        {tratamento.caso_clinico && (
                                            <span className="inline-block mt-2 px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">
                                                {tratamento.caso_clinico}
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-gray-500 mb-6">
                                <p>Nenhum tratamento sugerido para os casos selecionados</p>
                            </div>
                        )}

                        <div className="flex justify-between">
                            <button
                                onClick={() => setStep(2)}
                                className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Voltar
                            </button>
                            <button
                                onClick={gerarReceita}
                                disabled={tratamentosSugeridos.length === 0 || loading}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {loading ? 'Gerando...' : 'Gerar Receita'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

