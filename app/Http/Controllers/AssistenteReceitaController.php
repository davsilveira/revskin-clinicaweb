<?php

namespace App\Http\Controllers;

use App\Models\AssistenteCasoClinico;
use App\Models\AssistenteRegra;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistenteReceitaController extends Controller
{
    /**
     * Show the assistant wizard.
     */
    public function index(): Response
    {
        return Inertia::render('AssistenteReceita/Index', [
            'tipoPeleOptions' => AssistenteCasoClinico::getTipoPeleOptions(),
            'intensidadeOptions' => AssistenteCasoClinico::getIntensidadeOptions(),
            'faixaEtariaOptions' => AssistenteCasoClinico::getFaixaEtariaOptions(),
        ]);
    }

    /**
     * Initialize assistant session.
     */
    public function iniciar(Request $request)
    {
        $validated = $request->validate([
            'paciente_id' => 'nullable|exists:pacientes,id',
            'paciente_nome' => 'nullable|string',
        ]);

        $paciente = null;
        if ($validated['paciente_id']) {
            $paciente = Paciente::find($validated['paciente_id']);
        }

        return response()->json([
            'paciente' => $paciente,
            'step' => 1,
        ]);
    }

    /**
     * Process wizard step and get suggestions.
     */
    public function processar(Request $request)
    {
        $validated = $request->validate([
            'tipo_pele' => 'nullable|string',
            'manchas' => 'nullable|string',
            'rugas' => 'nullable|string',
            'acne' => 'nullable|string',
            'flacidez' => 'nullable|string',
            'faixa_etaria' => 'nullable|string',
        ]);

        // Find matching case
        $caso = AssistenteCasoClinico::encontrarCaso($validated);

        // If no case found, try rules
        $regras = AssistenteRegra::encontrarRegras($validated);

        $produtosSugeridos = [];

        if ($caso) {
            foreach ($caso->tratamentos as $tratamento) {
                foreach ($tratamento->itens as $item) {
                    $produtosSugeridos[] = [
                        'produto_id' => $item->produto_id,
                        'produto' => $item->produto,
                        'local_uso' => $item->local_uso,
                        'quantidade' => $item->quantidade,
                        'anotacoes' => $item->anotacoes,
                    ];
                }
            }
        } elseif ($regras->isNotEmpty()) {
            foreach ($regras as $regra) {
                foreach ($regra->produtos as $produtoData) {
                    $produto = Produto::find($produtoData['produto_id']);
                    if ($produto) {
                        $produtosSugeridos[] = [
                            'produto_id' => $produto->id,
                            'produto' => $produto,
                            'local_uso' => $produtoData['local_uso'] ?? null,
                            'quantidade' => $produtoData['quantidade'] ?? 1,
                            'anotacoes' => $produtoData['anotacoes'] ?? null,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'caso' => $caso,
            'produtos_sugeridos' => $produtosSugeridos,
        ]);
    }

    /**
     * Generate receita from assistant.
     */
    public function gerarReceita(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'paciente_id' => 'required|exists:pacientes,id',
            'medico_id' => 'nullable|exists:medicos,id',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'nullable|numeric|min:0',
        ]);

        $medicoId = $validated['medico_id'] ?? $user->medico_id;

        $receita = Receita::create([
            'numero' => Receita::gerarNumero(),
            'paciente_id' => $validated['paciente_id'],
            'medico_id' => $medicoId,
            'data_receita' => now(),
            'status' => 'rascunho',
        ]);

        foreach ($validated['itens'] as $index => $item) {
            $receita->itens()->create([
                'produto_id' => $item['produto_id'],
                'local_uso' => $item['local_uso'] ?? null,
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $item['valor_unitario'] ?? 0,
                'valor_total' => $item['quantidade'] * ($item['valor_unitario'] ?? 0),
                'imprimir' => true,
                'ordem' => $index,
            ]);
        }

        $receita->calcularTotais();

        return response()->json([
            'receita_id' => $receita->id,
            'redirect' => route('receitas.edit', $receita),
        ]);
    }

    /**
     * Admin: Show Karnaugh table (spreadsheet).
     */
    public function regras(): Response
    {
        // Carregar regras do banco ou do arquivo JSON inicial
        $regras = AssistenteRegra::orderBy('id')->get();
        
        // Se não houver regras no banco, carregar do arquivo JSON de seed
        if ($regras->isEmpty()) {
            $jsonPath = database_path('seeders/karnaugh_data.json');
            if (file_exists($jsonPath)) {
                $regras = collect(json_decode(file_get_contents($jsonPath), true));
            }
        } else {
            // Converter para formato esperado pelo frontend
            $regras = $regras->map(function ($regra) {
                return [
                    'id' => $regra->id,
                    'caso_clinico' => $regra->linha_id,
                    'produtos' => $regra->produtos ?? [],
                ];
            });
        }

        return Inertia::render('AssistenteReceita/Regras', [
            'regras' => $regras,
        ]);
    }

    /**
     * Admin: Save Karnaugh table rules.
     */
    public function salvarRegras(Request $request)
    {
        $validated = $request->validate([
            'regras' => 'required|array',
            'regras.*.id' => 'nullable|integer',
            'regras.*.caso_clinico' => 'required|string',
            'regras.*.produtos' => 'nullable|array',
        ]);

        // Get IDs from request
        $requestIds = collect($validated['regras'])
            ->pluck('id')
            ->filter()
            ->toArray();

        // Delete rules not in the list
        AssistenteRegra::whereNotIn('id', $requestIds)->delete();

        // Update or create rules
        foreach ($validated['regras'] as $regraData) {
            $data = [
                'linha_id' => $regraData['caso_clinico'],
                'condicoes' => $this->parseCasoClinico($regraData['caso_clinico']),
                'produtos' => $regraData['produtos'] ?? [],
                'ativo' => true,
            ];

            if (!empty($regraData['id']) && AssistenteRegra::find($regraData['id'])) {
                AssistenteRegra::find($regraData['id'])->update($data);
            } else {
                AssistenteRegra::create($data);
            }
        }

        return back()->with('success', 'Tabela de Karnaugh salva com sucesso!');
    }

    /**
     * Parse caso clínico code into conditions.
     */
    private function parseCasoClinico(string $codigo): array
    {
        // Exemplo: PSM1R1A1 = Pele Seca/Mista, Rugas 1, Acne 1
        $condicoes = [];
        
        if (preg_match('/^P(SM|O)(\d)/', $codigo, $matches)) {
            $condicoes['tipo_pele'] = $matches[1] === 'SM' ? 'seca_mista' : 'oleosa';
        }
        
        if (preg_match('/R(\d)/', $codigo, $matches)) {
            $condicoes['rugas'] = (int) $matches[1];
        }
        
        if (preg_match('/A(\d)/', $codigo, $matches)) {
            $condicoes['acne'] = (int) $matches[1];
        }
        
        if (preg_match('/M(\d)?/', $codigo, $matches)) {
            $condicoes['manchas'] = isset($matches[1]) ? (int) $matches[1] : 1;
        }
        
        return $condicoes;
    }

}

