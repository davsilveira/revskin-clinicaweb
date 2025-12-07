<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TinyIntegrationController extends Controller
{
    protected string $baseUrl = 'https://api.tiny.com.br/api2/';

    /**
     * Get Tiny API token from settings.
     */
    protected function getToken(): ?string
    {
        return Setting::get('tiny_api_token');
    }

    /**
     * Check if integration is configured.
     */
    protected function isConfigured(): bool
    {
        return !empty($this->getToken());
    }

    /**
     * Make API request to Tiny.
     */
    protected function makeRequest(string $endpoint, array $data = []): array
    {
        $token = $this->getToken();

        if (!$token) {
            return ['status' => 'error', 'message' => 'Token Tiny não configurado'];
        }

        $data['token'] = $token;
        $data['formato'] = 'json';

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                $result = $response->json();
                return $result['retorno'] ?? $result;
            }

            Log::error('Tiny API Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['status' => 'error', 'message' => 'Erro na comunicação com Tiny'];
        } catch (\Exception $e) {
            Log::error('Tiny API Exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Show integration settings.
     */
    public function settings(): Response
    {
        return Inertia::render('Settings/Integrations/Tiny', [
            'token' => $this->getToken() ? '***configurado***' : null,
            'isConfigured' => $this->isConfigured(),
        ]);
    }

    /**
     * Update integration settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        Setting::set('tiny_api_token', $validated['token']);

        return back()->with('success', 'Configurações do Tiny atualizadas!');
    }

    /**
     * Test connection.
     */
    public function testConnection()
    {
        $result = $this->makeRequest('info.php');

        if (isset($result['status']) && $result['status'] === 'OK') {
            return response()->json([
                'success' => true,
                'message' => 'Conexão com Tiny estabelecida com sucesso!',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Erro ao conectar com Tiny',
        ], 400);
    }

    /**
     * Sync products from Tiny.
     */
    public function syncProdutos()
    {
        $result = $this->makeRequest('produtos.pesquisa.php', [
            'pesquisa' => '',
        ]);

        if (isset($result['status']) && $result['status'] === 'Erro') {
            return response()->json([
                'success' => false,
                'message' => $result['erros'][0]['erro'] ?? 'Erro ao buscar produtos',
            ], 400);
        }

        $produtos = $result['produtos'] ?? [];
        $synced = 0;

        foreach ($produtos as $produtoData) {
            $produto = $produtoData['produto'];
            
            Produto::updateOrCreate(
                ['codigo' => $produto['codigo']],
                [
                    'nome' => $produto['nome'],
                    'descricao' => $produto['descricao'] ?? null,
                    'ativo' => true,
                ]
            );
            $synced++;
        }

        return response()->json([
            'success' => true,
            'message' => "Sincronizados {$synced} produtos do Tiny",
        ]);
    }

    /**
     * Sync cliente to Tiny.
     */
    public function syncCliente(Paciente $paciente)
    {
        $clienteData = [
            'contatos' => [
                'contato' => [
                    'nome' => $paciente->nome,
                    'tipo_pessoa' => 'F',
                    'cpf_cnpj' => preg_replace('/\D/', '', $paciente->cpf ?? ''),
                    'email' => $paciente->email1,
                    'fone' => preg_replace('/\D/', '', $paciente->telefone1 ?? ''),
                    'endereco' => $paciente->endereco,
                    'numero' => $paciente->numero,
                    'complemento' => $paciente->complemento,
                    'bairro' => $paciente->bairro,
                    'cidade' => $paciente->cidade,
                    'uf' => $paciente->uf,
                    'cep' => preg_replace('/\D/', '', $paciente->cep ?? ''),
                ],
            ],
        ];

        $result = $this->makeRequest('contato.incluir.php', [
            'contato' => json_encode($clienteData),
        ]);

        if (isset($result['status']) && $result['status'] === 'OK') {
            // Store Tiny ID in paciente
            $paciente->update(['tiny_id' => $result['registros'][0]['registro']['id'] ?? null]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente sincronizado com Tiny',
                'tiny_id' => $result['registros'][0]['registro']['id'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['erros'][0]['erro'] ?? 'Erro ao sincronizar cliente',
        ], 400);
    }

    /**
     * Create proposta/pedido in Tiny from receita.
     */
    public function criarProposta(Receita $receita)
    {
        $receita->load(['paciente', 'medico', 'itens.produto']);

        $itens = [];
        foreach ($receita->itens as $item) {
            $itens[] = [
                'item' => [
                    'codigo' => $item->produto->codigo,
                    'descricao' => $item->produto->nome,
                    'quantidade' => $item->quantidade,
                    'valor_unitario' => $item->valor_unitario,
                ],
            ];
        }

        $pedidoData = [
            'pedido' => [
                'data_pedido' => $receita->data_receita->format('d/m/Y'),
                'cliente' => [
                    'nome' => $receita->paciente->nome,
                    'cpf_cnpj' => preg_replace('/\D/', '', $receita->paciente->cpf ?? ''),
                ],
                'itens' => $itens,
                'valor_frete' => $receita->valor_frete,
                'valor_desconto' => $receita->desconto_valor,
                'obs' => "Receita #{$receita->numero} - Médico: {$receita->medico->nome}",
            ],
        ];

        $result = $this->makeRequest('pedido.incluir.php', [
            'pedido' => json_encode($pedidoData),
        ]);

        if (isset($result['status']) && $result['status'] === 'OK') {
            $tinyPedidoId = $result['registros'][0]['registro']['id'] ?? null;

            // Store reference
            $receita->update(['tiny_pedido_id' => $tinyPedidoId]);

            return response()->json([
                'success' => true,
                'message' => 'Proposta criada no Tiny com sucesso!',
                'tiny_pedido_id' => $tinyPedidoId,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['erros'][0]['erro'] ?? 'Erro ao criar proposta no Tiny',
        ], 400);
    }

    /**
     * Get pedidos from Tiny.
     */
    public function listarPedidos(Request $request)
    {
        $result = $this->makeRequest('pedidos.pesquisa.php', [
            'dataInicial' => $request->get('data_inicio', now()->subMonth()->format('d/m/Y')),
            'dataFinal' => $request->get('data_fim', now()->format('d/m/Y')),
        ]);

        if (isset($result['status']) && $result['status'] === 'Erro') {
            return response()->json([
                'success' => false,
                'message' => $result['erros'][0]['erro'] ?? 'Erro ao listar pedidos',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'pedidos' => $result['pedidos'] ?? [],
        ]);
    }
}

