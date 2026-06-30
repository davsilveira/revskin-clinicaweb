<?php

namespace App\Services;

use App\Jobs\MarcarNegociacaoPerdidaRdJob;
use App\Models\Receita;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class RdNegociacaoSync
{
    public static function agendarMarcarPerdida(?Receita $receita): void
    {
        if (! Setting::get('rd_enabled', false) || $receita === null) {
            return;
        }

        if (! filled($receita->rd_deal_id)) {
            return;
        }

        $fieldId = trim((string) Setting::get('rd_cancelamento_field_id', ''));
        $fieldValue = trim((string) Setting::get('rd_cancelamento_field_value', ''));

        if ($fieldId === '' || $fieldValue === '') {
            Log::info('RD Station CRM: Cancelamento não sincronizado — campo de cancelamento não configurado', [
                'receita_id' => $receita->id,
            ]);

            return;
        }

        MarcarNegociacaoPerdidaRdJob::dispatch($receita)->delay(now()->addMinute());

        Log::info('RD Station CRM: MarcarNegociacaoPerdidaRdJob despachado', [
            'receita_id' => $receita->id,
            'rd_deal_id' => $receita->rd_deal_id,
        ]);
    }
}
