import { useForm } from '@inertiajs/react';
import Checkbox from '@/Components/Form/Checkbox';

export default function ReceitasSettings({ settings, onToast }) {
    const { data, setData, put, processing } = useForm({
        ocultar_canceladas: settings.ocultar_canceladas || false,
    });

    const handleSubmit = (event) => {
        event.preventDefault();

        put('/settings/receitas', {
            preserveScroll: true,
            onSuccess: () => onToast?.({ message: 'Configuracoes salvas com sucesso!', type: 'success' }),
            onError: () => onToast?.({ message: 'Erro ao salvar configuracoes.', type: 'error' }),
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div>
                <label className="flex items-center gap-3 cursor-pointer">
                    <Checkbox
                        checked={data.ocultar_canceladas}
                        onChange={(event) => setData('ocultar_canceladas', event.target.checked)}
                    />
                    <span className="text-sm font-medium text-gray-700">Ocultar receitas canceladas</span>
                </label>
                <p className="mt-1 text-xs text-gray-500">
                    Quando ativado, as receitas canceladas saem da listagem de Receitas (continuam acessíveis pelo
                    filtro "Cancelada" ou "Todas"), da ficha do paciente e não são abertas ao clicar no paciente.
                </p>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button
                    type="submit"
                    disabled={processing}
                    className="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                >
                    {processing ? 'Salvando...' : 'Salvar configuracoes'}
                </button>
            </div>
        </form>
    );
}
