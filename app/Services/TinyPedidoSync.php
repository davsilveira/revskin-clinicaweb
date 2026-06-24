<?php

namespace App\Services;

use App\Jobs\CancelarPedidoTinyJob;
use App\Models\Receita;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class TinyPedidoSync
{
    public static function agendarCancelamento(?Receita $receita): void
    {
        if (! Setting::get('tiny_enabled', false) || $receita === null) {
            return;
        }

        $receita->loadMissing('atendimentoCallcenter');

        $temPedidoTiny = filled($receita->tiny_pedido_id)
            || filled($receita->atendimentoCallcenter?->tiny_pedido_id);

        if (! $temPedidoTiny) {
            return;
        }

        CancelarPedidoTinyJob::dispatch($receita)->delay(now()->addMinute());

        Log::info('Tiny ERP: CancelarPedidoTinyJob despachado', [
            'receita_id' => $receita->id,
            'receita_numero' => $receita->numero,
            'tiny_pedido_id' => $receita->tiny_pedido_id ?? $receita->atendimentoCallcenter?->tiny_pedido_id,
        ]);
    }
}
