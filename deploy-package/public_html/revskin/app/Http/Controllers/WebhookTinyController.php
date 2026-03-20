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
        $payload = $request->all();
        if (empty($payload)) {
            Log::info('Tiny ERP: Webhook recebido com payload vazio', [
                'raw_body' => $request->getContent(),
                'content_type' => $request->header('Content-Type'),
            ]);
        } else {
            Log::info('Tiny ERP: Webhook recebido', [
                'tipo' => $request->input('tipo'),
                'dados_id' => $request->input('dados.id'),
                'dados_codigoSituacao' => $request->input('dados.codigoSituacao'),
                'dados_descricaoSituacao' => $request->input('dados.descricaoSituacao'),
                'dados_idVendaTiny' => $request->input('dados.idVendaTiny'),
                'dados_situacao' => $request->input('dados.situacao'),
                'payload_completo' => $payload,
            ]);
        }

        $pedidoId = null;
        $situacao = null;

        $tipo = $request->input('tipo');
        if (in_array($tipo, ['inclusao_pedido', 'atualizacao_pedido'])) {
            $pedidoId = $request->input('dados.id');
            $situacao = $request->input('dados.codigoSituacao')
                ?? $request->input('dados.descricaoSituacao');
        }

        if ($tipo === 'situacao_pedido') {
            $pedidoId = $request->input('dados.idVendaTiny');
            $situacao = $request->input('dados.situacao');
        }

        if ($tipo === 'rastreio') {
            $pedidoId = $request->input('dados.idVendaTiny');
            $situacao = 'enviado';
        }

        if (!$pedidoId) {
            $pedidoId = $request->input('pedido.id')
                ?? $request->input('id')
                ?? $request->input('dados.id')
                ?? $request->input('dados.idVendaTiny');
        }

        if (!$situacao) {
            $situacao = $request->input('pedido.situacao')
                ?? $request->input('situacao')
                ?? $request->input('dados.codigoSituacao')
                ?? $request->input('dados.descricaoSituacao')
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

        Log::info('Tiny ERP: Webhook será processado', [
            'pedido_id_final' => $pedidoId,
            'situacao_final' => $situacao,
        ]);

        ProcessWebhookTinyJob::dispatch($pedidoId, $situacao, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido e processando',
        ], 200);
    }
}
