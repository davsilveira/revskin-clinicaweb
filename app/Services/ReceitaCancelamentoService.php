<?php

namespace App\Services;

use App\Models\AtendimentoCallcenter;
use App\Models\Receita;
use App\Models\ReceitaItemAquisicao;
use Illuminate\Support\Facades\Log;

class ReceitaCancelamentoService
{
    public static function cancelarReceita(Receita $receita, ?string $tinyPedidoIdContext = null): void
    {
        if ($receita->status === 'cancelada') {
            return;
        }

        $receita->load(['itens', 'atendimentoCallcenter']);

        foreach ($receita->itens as $item) {
            $item->update(['vendido' => false]);
        }

        $itemIds = $receita->itens->pluck('id')->toArray();
        if ($itemIds !== []) {
            ReceitaItemAquisicao::query()
                ->whereIn('receita_item_id', $itemIds)
                ->when($tinyPedidoIdContext !== null, function ($query) use ($tinyPedidoIdContext) {
                    $query->where(function ($q) use ($tinyPedidoIdContext) {
                        $q->where('tiny_pedido_id', $tinyPedidoIdContext)
                            ->orWhereNull('tiny_pedido_id');
                    });
                })
                ->delete();
        }

        $receita->update(['status' => 'cancelada', 'ativo' => false]);

        $atendimento = $receita->atendimentoCallcenter;
        if ($atendimento) {
            $atendimento->update(['status' => AtendimentoCallcenter::STATUS_CANCELADO]);
        }

        Log::info('Receita cancelada via integração', [
            'receita_id' => $receita->id,
            'itens_revertidos' => count($itemIds),
        ]);
    }
}
