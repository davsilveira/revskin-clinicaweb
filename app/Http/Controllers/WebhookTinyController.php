<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookTinyJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookTinyController extends Controller
{
    /**
     * Handle webhook when order status changes in Tiny ERP.
     * Supports both V2 and V3 webhook formats.
     */
    public function pedidoFinalizado(Request $request)
    {
        Log::info('Tiny ERP: Webhook recebido', [
            'payload' => $request->all(),
        ]);

        $pedidoId = null;
        $situacao = null;

        $tipo = $request->input('tipo');
        if ($tipo === 'situacao_pedido') {
            $pedidoId = $request->input('dados.idVendaTiny');
            $situacao = $request->input('dados.situacao');
        }

        if (!$pedidoId) {
            $pedidoId = $request->input('pedido.id')
                ?? $request->input('id')
                ?? $request->input('dados.idVendaTiny');
        }

        if (!$situacao) {
            $situacao = $request->input('pedido.situacao')
                ?? $request->input('situacao')
                ?? $request->input('dados.situacao');
        }

        if (!$pedidoId) {
            Log::warning('Tiny ERP: Webhook sem ID do pedido', [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ID do pedido não encontrado no payload',
            ], 400);
        }

        ProcessWebhookTinyJob::dispatch($pedidoId, $situacao, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido e processando',
        ], 200);
    }
}
