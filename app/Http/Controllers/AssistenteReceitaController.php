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
     * Mapeamento de tipo de pele para código Karnaugh.
     * Baseado na documentação: PN, PS, PR, PM, PO
     */
    private const TIPO_PELE_MAP = [
        'Normal' => 'PN',
        'Seca' => 'PS',
        'Mista' => 'PM',
        'Oleosa' => 'PO',
        'Mista-Ressecada' => 'PR',
    ];

    /**
     * Mapeamento de intensidade para número.
     */
    private const INTENSIDADE_MAP = [
        'Não' => 1,
        'Leve' => 1,
        'Moderado' => 2,
        'Intenso' => 3,
    ];

    /**
     * Mapeamento de local de uso para cada categoria de produto.
     */
    private const LOCAL_USO_MAP = [
        'creme_noite' => 'Creme da Noite',
        'creme_dia' => 'Creme do Dia',
        'creme_dia_verao' => 'Creme do Dia (Verão)',
        'creme_dia_inverno' => 'Creme do Dia (Inverno)',
        'limpeza_syndet' => 'Limpeza Syndet',
        'creme_olhos' => 'Creme dos Olhos',
        'base_tonalite' => 'Base Tonalité',
        'serum_vitamina_c' => 'Sérum Vitamina C',
        'gel_secativo' => 'Gel Secativo',
        'creme_firmador' => 'Creme Firmador',
        'serum_anti_queda' => 'Sérum Anti-Queda',
        'duo_mask' => 'Duo Mask',
        'protetor_solar' => 'Protetor Solar',
        'creme_noite_maos' => 'Creme Noite (Mãos)',
        'creme_dia_maos' => 'Creme Dia (Mãos)',
        'creme_corpo' => 'Creme Corpo',
    ];

    /**
     * Show the assistant wizard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        // Para admin, fornecer lista de médicos para seleção
        // Para médico, usar seu próprio medico_id
        $medicos = [];
        $currentMedicoId = $user->medico_id;
        
        if ($user->isAdmin()) {
            $medicos = Medico::where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'crm'])
                ->map(fn($m) => [
                    'id' => $m->id,
                    'label' => "{$m->nome} (CRM: {$m->crm})"
                ]);
        }
        
        return Inertia::render('AssistenteReceita/Index', [
            'tipoPeleOptions' => $this->getTipoPeleOptions(),
            'intensidadeOptions' => $this->getIntensidadeOptions(),
            'faixaEtariaOptions' => AssistenteCasoClinico::getFaixaEtariaOptions(),
            'medicos' => $medicos,
            'currentMedicoId' => $currentMedicoId,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    /**
     * Get tipo de pele options.
     */
    private function getTipoPeleOptions(): array
    {
        return [
            'Normal' => 'Normal',
            'Oleosa' => 'Oleosa',
            'Seca' => 'Seca',
            'Mista' => 'Mista',
        ];
    }

    /**
     * Get intensidade options.
     */
    private function getIntensidadeOptions(): array
    {
        return [
            'Não' => 'Não',
            'Leve' => 'Leve',
            'Moderado' => 'Moderado',
            'Intenso' => 'Intenso',
        ];
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

        // Montar código do caso clínico baseado nas seleções
        $codigoKarnaugh = $this->montarCodigoKarnaugh($validated);

        // Buscar na tabela Karnaugh
        $produtosKarnaugh = $this->buscarProdutosKarnaugh($codigoKarnaugh);

        $produtosSugeridos = [];

        if ($produtosKarnaugh) {
            foreach ($produtosKarnaugh as $categoria => $nomeProduto) {
                if (empty($nomeProduto) || $nomeProduto === '-') {
                    continue;
                }

                // Buscar produto por nome ou código
                $produto = $this->buscarProdutoPorNome($nomeProduto);
                
                if ($produto) {
                    $produtosSugeridos[] = [
                        'produto_id' => $produto->id,
                        'produto' => $produto,
                        'local_uso' => self::LOCAL_USO_MAP[$categoria] ?? $categoria,
                        'quantidade' => 1,
                        'anotacoes' => null,
                    ];
                } else {
                    // Produto não encontrado no banco, mas incluir como referência
                    $produtosSugeridos[] = [
                        'produto_id' => null,
                        'produto' => ['nome' => $nomeProduto, 'id' => null],
                        'local_uso' => self::LOCAL_USO_MAP[$categoria] ?? $categoria,
                        'quantidade' => 1,
                        'anotacoes' => 'Produto não cadastrado: ' . $nomeProduto,
                        'nao_encontrado' => true,
                    ];
                }
            }
        }

        return response()->json([
            'codigo_karnaugh' => $codigoKarnaugh,
            'produtos_sugeridos' => $produtosSugeridos,
        ]);
    }

    /**
     * Montar código Karnaugh a partir das condições selecionadas.
     * 
     * Formato: {tipoPele}M{manchas}R{rugas}A{acne}
     * Onde tipoPele = PN, PS, PM, PO, PR (2 caracteres)
     * 
     * Exemplos: PSM1R1A1, POM2R3A2, PNM1R2A1
     */
    private function montarCodigoKarnaugh(array $condicoes): string
    {
        // Tipo de pele: PN, PS, PM, PO, PR
        $tipoPele = self::TIPO_PELE_MAP[$condicoes['tipo_pele'] ?? 'Normal'] ?? 'PN';
        
        // Manchas: 1, 2 ou 3
        $manchas = self::INTENSIDADE_MAP[$condicoes['manchas'] ?? 'Não'] ?? 1;
        
        // Rugas: 1, 2 ou 3
        $rugas = self::INTENSIDADE_MAP[$condicoes['rugas'] ?? 'Não'] ?? 1;
        
        // Acne: 1, 2 ou 3
        $acne = self::INTENSIDADE_MAP[$condicoes['acne'] ?? 'Não'] ?? 1;

        // Formato: {tipoPele}M{manchas}R{rugas}A{acne}
        // Exemplo: PS + M + 1 + R + 1 + A + 1 = PSM1R1A1
        return "{$tipoPele}M{$manchas}R{$rugas}A{$acne}";
    }

    /**
     * Buscar produtos na tabela Karnaugh pelo código.
     */
    private function buscarProdutosKarnaugh(string $codigo): ?array
    {
        // Primeiro tentar no banco de dados
        $regra = AssistenteRegra::where('linha_id', $codigo)
            ->where('ativo', true)
            ->first();

        if ($regra && !empty($regra->produtos)) {
            return $regra->produtos;
        }

        // Fallback: buscar no arquivo JSON
        $jsonPath = database_path('seeders/karnaugh_data.json');
        if (file_exists($jsonPath)) {
            $dados = json_decode(file_get_contents($jsonPath), true);
            
            foreach ($dados as $linha) {
                // Normalizar código (remover espaços, uppercase)
                $codigoLinha = strtoupper(preg_replace('/\s+/', '', $linha['caso_clinico']));
                $codigoBusca = strtoupper(preg_replace('/\s+/', '', $codigo));
                
                if ($codigoLinha === $codigoBusca) {
                    return $linha['produtos'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Buscar produto por nome ou código.
     */
    private function buscarProdutoPorNome(string $nome): ?Produto
    {
        // Limpar o nome
        $nome = trim($nome);
        
        if (empty($nome) || $nome === '-') {
            return null;
        }

        // Buscar por nome exato
        $produto = Produto::where('nome', $nome)->first();
        if ($produto) {
            return $produto;
        }

        // Buscar por código
        $produto = Produto::where('codigo', $nome)->first();
        if ($produto) {
            return $produto;
        }

        // Buscar por nome parcial (LIKE)
        $produto = Produto::where('nome', 'LIKE', '%' . $nome . '%')->first();
        if ($produto) {
            return $produto;
        }

        // Buscar por nome_completo se existir
        $produto = Produto::where('nome_completo', 'LIKE', '%' . $nome . '%')->first();
        
        return $produto;
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

        // Determinar médico: do request, do usuário, ou primeiro ativo
        $medicoId = $validated['medico_id'] ?? $user->medico_id;
        
        if (!$medicoId) {
            // Fallback: usar primeiro médico ativo
            $medico = Medico::where('ativo', true)->first();
            if (!$medico) {
                return response()->json([
                    'error' => 'Nenhum médico cadastrado. Por favor, cadastre um médico primeiro.',
                ], 422);
            }
            $medicoId = $medico->id;
        }

        $receita = Receita::create([
            'numero' => Receita::gerarNumero(),
            'paciente_id' => $validated['paciente_id'],
            'medico_id' => $medicoId,
            'data_receita' => now(),
            'status' => 'rascunho',
        ]);

        foreach ($validated['itens'] as $index => $item) {
            // Buscar o preço do produto se não foi fornecido
            $valorUnitario = $item['valor_unitario'] ?? null;
            if ($valorUnitario === null || $valorUnitario == 0) {
                $produto = \App\Models\Produto::find($item['produto_id']);
                $valorUnitario = $produto?->preco ?? 0;
            }
            
            $receita->itens()->create([
                'produto_id' => $item['produto_id'],
                'local_uso' => $item['local_uso'] ?? null,
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $valorUnitario,
                'valor_total' => $item['quantidade'] * $valorUnitario,
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
        // Normalizar código
        $codigo = strtoupper(preg_replace('/\s+/', '', $codigo));
        
        $condicoes = [];
        
        // Tipo de pele: PSM ou PO
        if (preg_match('/^P(SM|O)/', $codigo, $matches)) {
            $condicoes['tipo_pele'] = $matches[1] === 'SM' ? 'seca_mista' : 'oleosa';
        }
        
        // Manchas: M1, M2 ou M3
        if (preg_match('/M(\d)/', $codigo, $matches)) {
            $condicoes['manchas'] = (int) $matches[1];
        }
        
        // Rugas: R1, R2 ou R3
        if (preg_match('/R(\d)/', $codigo, $matches)) {
            $condicoes['rugas'] = (int) $matches[1];
        }
        
        // Acne: A1, A2 ou A3
        if (preg_match('/A(\d)/', $codigo, $matches)) {
            $condicoes['acne'] = (int) $matches[1];
        }
        
        return $condicoes;
    }
}
