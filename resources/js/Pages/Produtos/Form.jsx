import { useForm, Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function ProdutoForm({ produto, categorias }) {
    const isEditing = !!produto;

    const { data, setData, post, put, processing, errors } = useForm({
        codigo: produto?.codigo || '',
        nome: produto?.nome || '',
        descricao: produto?.descricao || '',
        categoria: produto?.categoria || '',
        unidade: produto?.unidade || 'UN',
        preco_custo: produto?.preco_custo || '',
        preco_venda: produto?.preco_venda || '',
        estoque_minimo: produto?.estoque_minimo || 0,
        tiny_id: produto?.tiny_id || '',
        ativo: produto?.ativo ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/produtos/${produto.id}`);
        } else {
            post('/produtos');
        }
    };

    return (
        <DashboardLayout>
            <div className="p-6 max-w-4xl mx-auto">
                <div className="mb-6">
                    <Link
                        href="/produtos"
                        className="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Voltar para Produtos
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">
                        {isEditing ? 'Editar Produto' : 'Novo Produto'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Informações do Produto</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Código
                                </label>
                                <input
                                    type="text"
                                    value={data.codigo}
                                    onChange={(e) => setData('codigo', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="SKU ou código interno"
                                />
                                {errors.codigo && <p className="mt-1 text-sm text-red-600">{errors.codigo}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Nome *
                                </label>
                                <input
                                    type="text"
                                    value={data.nome}
                                    onChange={(e) => setData('nome', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                {errors.nome && <p className="mt-1 text-sm text-red-600">{errors.nome}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Descrição
                                </label>
                                <textarea
                                    value={data.descricao}
                                    onChange={(e) => setData('descricao', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Categoria
                                </label>
                                <input
                                    type="text"
                                    list="categorias-list"
                                    value={data.categoria}
                                    onChange={(e) => setData('categoria', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Ex: Dermocosméticos"
                                />
                                <datalist id="categorias-list">
                                    {categorias?.map((cat) => (
                                        <option key={cat} value={cat} />
                                    ))}
                                </datalist>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Unidade
                                </label>
                                <select
                                    value={data.unidade}
                                    onChange={(e) => setData('unidade', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="UN">Unidade (UN)</option>
                                    <option value="CX">Caixa (CX)</option>
                                    <option value="KIT">Kit (KIT)</option>
                                    <option value="PCT">Pacote (PCT)</option>
                                    <option value="FR">Frasco (FR)</option>
                                    <option value="TB">Tubo (TB)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Preços */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Preços e Estoque</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Preço de Custo
                                </label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2 text-gray-500">R$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.preco_custo}
                                        onChange={(e) => setData('preco_custo', e.target.value)}
                                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Preço de Venda *
                                </label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2 text-gray-500">R$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.preco_venda}
                                        onChange={(e) => setData('preco_venda', e.target.value)}
                                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                                {errors.preco_venda && <p className="mt-1 text-sm text-red-600">{errors.preco_venda}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Estoque Mínimo
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={data.estoque_minimo}
                                    onChange={(e) => setData('estoque_minimo', parseInt(e.target.value) || 0)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Integração */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Integração</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    ID Tiny ERP
                                </label>
                                <input
                                    type="text"
                                    value={data.tiny_id}
                                    onChange={(e) => setData('tiny_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="ID do produto no Tiny"
                                />
                            </div>

                            <div className="flex items-center">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.ativo}
                                        onChange={(e) => setData('ativo', e.target.checked)}
                                        className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                    />
                                    <span className="text-sm font-medium text-gray-700">Produto ativo</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-4">
                        <Link
                            href="/produtos"
                            className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                        >
                            {processing ? 'Salvando...' : (isEditing ? 'Atualizar' : 'Cadastrar')}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}

