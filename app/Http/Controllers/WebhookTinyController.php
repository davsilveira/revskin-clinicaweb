<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookTinyJob;
use App\Models\AtendimentoCallcenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookTinyController extends Controller
{
    /**
     * Handle webhook when order is finalized in Tiny ERP
     */
    public function pedidoFinalizado(Request $request)
    {
        // Log do webhook recebido
        Log::info('Tiny ERP: Webhook recebido', [
            'payload' => $request->all(),
        ]);

        // Validar estrutura básica do payload
        $pedidoId = $request->input('pedido.id') ?? $request->input('id');
        $situacao = $request->input('pedido.situacao') ?? $request->input('situacao');

        if (!$pedidoId) {
            Log::warning('Tiny ERP: Webhook sem ID do pedido', [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ID do pedido não encontrado no payload',
            ], 400);
        }

        // Processar webhook de forma assíncrona
        ProcessWebhookTinyJob::dispatch($pedidoId, $situacao, $request->all());

        // Retornar resposta imediata para Tiny
        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido e processando',
        ], 200);
    }
}
