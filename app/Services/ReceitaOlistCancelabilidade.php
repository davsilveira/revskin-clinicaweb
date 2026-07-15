<?php

namespace App\Services;

use App\Models\Receita;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Verifica no oList (Tiny ERP) se a receita ainda pode ser cancelada.
 */
class ReceitaOlistCancelabilidade
{
    /**
     * @return array{
     *     allowed: bool,
     *     reason: string|null,
     *     situacao: mixed,
     *     situacao_label: string|null,
     *     tiny_pedido_id: string|null,
     *     checked_olist: bool
     * }
     */
    public static function verificar(Receita $receita, ?TinyErpClient $client = null): array
    {
        $base = [
            'allowed' => true,
            'reason' => null,
            'situacao' => null,
            'situacao_label' => null,
            'tiny_pedido_id' => null,
            'checked_olist' => false,
        ];

        if (! Setting::get('tiny_enabled', false)) {
            return $base;
        }

        $receita->loadMissing('atendimentoCallcenter');

        $tinyPedidoId = trim((string) ($receita->tiny_pedido_id ?? ''));
        if ($tinyPedidoId === '') {
            $tinyPedidoId = trim((string) ($receita->atendimentoCallcenter?->tiny_pedido_id ?? ''));
        }

        if ($tinyPedidoId === '') {
            return $base;
        }

        $base['tiny_pedido_id'] = $tinyPedidoId;
        $client ??= app(TinyErpClient::class);

        try {
            $pedido = $client->obterPedido((int) $tinyPedidoId);
        } catch (\Throwable $e) {
            Log::warning('oList: falha ao consultar pedido para cancelamento', [
                'receita_id' => $receita->id,
                'tiny_pedido_id' => $tinyPedidoId,
                'error' => $e->getMessage(),
            ]);

            return array_merge($base, [
                'allowed' => false,
                'reason' => 'Não foi possível verificar o status do pedido no oList. Tente novamente em instantes ou contate o suporte do ClinicaWeb.',
                'checked_olist' => true,
            ]);
        }

        if (($pedido['status'] ?? null) !== 'success') {
            Log::warning('oList: resposta de erro ao consultar pedido para cancelamento', [
                'receita_id' => $receita->id,
                'tiny_pedido_id' => $tinyPedidoId,
                'message' => $pedido['message'] ?? null,
            ]);

            return array_merge($base, [
                'allowed' => false,
                'reason' => 'Não foi possível verificar o status do pedido no oList. Tente novamente em instantes ou contate o suporte do ClinicaWeb.',
                'checked_olist' => true,
            ]);
        }

        $situacao = $pedido['data']['situacao'] ?? null;
        $label = TinyErpClient::labelSituacaoPedido($situacao);
        $base['situacao'] = $situacao;
        $base['situacao_label'] = $label;
        $base['checked_olist'] = true;

        if (TinyErpClient::isSituacaoPedidoCancelada($situacao)) {
            return $base;
        }

        if (TinyErpClient::isSituacaoPedidoFaturada($situacao)) {
            return array_merge($base, [
                'allowed' => false,
                'reason' => 'Esta receita não pode ser cancelada porque o pedido no oList já está em status «'.$label.'» (já faturado ou em processo de entrega). Solicite suporte ao ClinicaWeb se precisar de ajuda.',
            ]);
        }

        return $base;
    }
}
