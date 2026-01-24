<?php

namespace App\Http\Controllers;

use App\Models\RegraCondicional;
use App\Models\RegraCondicao;
use App\Models\RegraAcao;
use App\Models\TabelaKarnaugh;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RegraCondicionalController extends Controller
{
    /**
     * Listar todas as regras condicionais.
     */
    public function index(Request $request)
    {
        $query = RegraCondicional::with(['condicoes', 'acoes.tabelaKarnaugh', 'acoes.produto', 'tabelaAlvo'])
            ->orderBy('ordem');

        // Filtrar por tipo ou tabela
        $filtro = $request->get('filtro', 'todas');
        
        if ($filtro === 'selecao_tabela') {
            $query->selecaoTabela();
        } elseif (is_numeric($filtro)) {
            // Filtro por tabela específica (modificação)
            $query->modificacaoTabela()->paraTabela((int) $filtro);
        }

        $regras = $query->get();

        $tabelasKarnaugh = TabelaKarnaugh::ativo()
            ->orderBy('nome')
            ->get(['id', 'nome', 'padrao']);

        $produtos = Produto::where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);

        return Inertia::render('AssistenteReceita/RegrasCondicionais', [
            'regras' => $regras,
            'tabelasKarnaugh' => $tabelasKarnaugh,
            'produtos' => $produtos,
            'camposDisponiveis' => RegraCondicao::CAMPOS_DISPONIVEIS,
            'operadores' => RegraCondicao::OPERADORES,
            'tiposAcao' => RegraAcao::TIPOS_ACAO,
            'tiposRegra' => RegraCondicional::TIPOS,
            'opcoesValores' => $this->getOpcoesValores(),
            'filtroAtual' => $filtro,
        ]);
    }

    /**
     * Criar nova regra.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:1000',
            'tipo' => 'required|in:selecao_tabela,modificacao_tabela',
            'tabela_karnaugh_id' => 'nullable|exists:tabelas_karnaugh,id',
            'ativo' => 'boolean',
            'condicoes' => 'required|array|min:1',
            'condicoes.*.campo' => 'required|string',
            'condicoes.*.operador' => 'required|string',
            'condicoes.*.valor' => 'nullable|string',
            'acoes' => 'required|array|min:1',
            'acoes.*.tipo_acao' => 'required|string|in:usar_tabela,adicionar_item,remover_item,modificar_quantidade,alterar_marcacao',
            'acoes.*.tabela_karnaugh_id' => 'nullable|exists:tabelas_karnaugh,id',
            'acoes.*.produto_id' => 'nullable|exists:produtos,id',
            'acoes.*.marcar' => 'boolean',
            'acoes.*.quantidade' => 'nullable|integer|min:1',
            'acoes.*.categoria' => 'nullable|string|max:255',
        ]);

        // Validar consistência tipo/tabela
        if ($validated['tipo'] === 'modificacao_tabela' && empty($validated['tabela_karnaugh_id'])) {
            return back()->withErrors(['tabela_karnaugh_id' => 'Selecione a tabela alvo para regras de modificação.']);
        }

        // Validar ações conforme tipo
        foreach ($validated['acoes'] as $acao) {
            if ($validated['tipo'] === 'selecao_tabela' && $acao['tipo_acao'] !== 'usar_tabela') {
                return back()->withErrors(['acoes' => 'Regras de seleção só podem ter ação "Usar Tabela Karnaugh".']);
            }
            if ($validated['tipo'] === 'modificacao_tabela' && $acao['tipo_acao'] === 'usar_tabela') {
                return back()->withErrors(['acoes' => 'Regras de modificação não podem ter ação "Usar Tabela Karnaugh".']);
            }
            // Validar que modificar_quantidade tem quantidade definida
            if ($acao['tipo_acao'] === 'modificar_quantidade' && empty($acao['quantidade'])) {
                return back()->withErrors(['acoes' => 'A ação "Modificar Quantidade" requer um valor de quantidade.']);
            }
        }

        DB::transaction(function () use ($validated) {
            // Calcular próxima ordem
            $maxOrdem = RegraCondicional::max('ordem') ?? 0;

            $regra = RegraCondicional::create([
                'nome' => $validated['nome'],
                'descricao' => $validated['descricao'] ?? null,
                'tipo' => $validated['tipo'],
                'tabela_karnaugh_id' => $validated['tipo'] === 'modificacao_tabela' 
                    ? $validated['tabela_karnaugh_id'] 
                    : null,
                'ordem' => $maxOrdem + 1,
                'ativo' => $validated['ativo'] ?? true,
            ]);

            // Criar condições
            foreach ($validated['condicoes'] as $condicao) {
                $regra->condicoes()->create([
                    'campo' => $condicao['campo'],
                    'operador' => $condicao['operador'],
                    'valor' => $condicao['operador'] === 'qualquer' ? null : ($condicao['valor'] ?? null),
                ]);
            }

            // Criar ações
            foreach ($validated['acoes'] as $index => $acao) {
                $regra->acoes()->create([
                    'tipo_acao' => $acao['tipo_acao'],
                    'tabela_karnaugh_id' => $acao['tabela_karnaugh_id'] ?? null,
                    'produto_id' => $acao['produto_id'] ?? null,
                    'marcar' => $acao['marcar'] ?? true,
                    'quantidade' => $acao['quantidade'] ?? null,
                    'categoria' => $acao['categoria'] ?? null,
                    'ordem' => $index,
                ]);
            }
        });

        return redirect()
            ->route('assistente.regras.index')
            ->with('success', 'Regra criada com sucesso!');
    }

    /**
     * Atualizar regra existente.
     */
    public function update(Request $request, RegraCondicional $regraCondicional)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:1000',
            'tipo' => 'required|in:selecao_tabela,modificacao_tabela',
            'tabela_karnaugh_id' => 'nullable|exists:tabelas_karnaugh,id',
            'ativo' => 'boolean',
            'condicoes' => 'required|array|min:1',
            'condicoes.*.campo' => 'required|string',
            'condicoes.*.operador' => 'required|string',
            'condicoes.*.valor' => 'nullable|string',
            'acoes' => 'required|array|min:1',
            'acoes.*.tipo_acao' => 'required|string|in:usar_tabela,adicionar_item,remover_item,modificar_quantidade,alterar_marcacao',
            'acoes.*.tabela_karnaugh_id' => 'nullable|exists:tabelas_karnaugh,id',
            'acoes.*.produto_id' => 'nullable|exists:produtos,id',
            'acoes.*.marcar' => 'boolean',
            'acoes.*.quantidade' => 'nullable|integer|min:1',
            'acoes.*.categoria' => 'nullable|string|max:255',
        ]);

        // Validar consistência tipo/tabela
        if ($validated['tipo'] === 'modificacao_tabela' && empty($validated['tabela_karnaugh_id'])) {
            return back()->withErrors(['tabela_karnaugh_id' => 'Selecione a tabela alvo para regras de modificação.']);
        }

        // Validar ações conforme tipo
        foreach ($validated['acoes'] as $acao) {
            if ($validated['tipo'] === 'selecao_tabela' && $acao['tipo_acao'] !== 'usar_tabela') {
                return back()->withErrors(['acoes' => 'Regras de seleção só podem ter ação "Usar Tabela Karnaugh".']);
            }
            if ($validated['tipo'] === 'modificacao_tabela' && $acao['tipo_acao'] === 'usar_tabela') {
                return back()->withErrors(['acoes' => 'Regras de modificação não podem ter ação "Usar Tabela Karnaugh".']);
            }
            // Validar que modificar_quantidade tem quantidade definida
            if ($acao['tipo_acao'] === 'modificar_quantidade' && empty($acao['quantidade'])) {
                return back()->withErrors(['acoes' => 'A ação "Modificar Quantidade" requer um valor de quantidade.']);
            }
        }

        DB::transaction(function () use ($validated, $regraCondicional) {
            $regraCondicional->update([
                'nome' => $validated['nome'],
                'descricao' => $validated['descricao'] ?? null,
                'tipo' => $validated['tipo'],
                'tabela_karnaugh_id' => $validated['tipo'] === 'modificacao_tabela' 
                    ? $validated['tabela_karnaugh_id'] 
                    : null,
                'ativo' => $validated['ativo'] ?? true,
            ]);

            // Recriar condições
            $regraCondicional->condicoes()->delete();
            foreach ($validated['condicoes'] as $condicao) {
                $regraCondicional->condicoes()->create([
                    'campo' => $condicao['campo'],
                    'operador' => $condicao['operador'],
                    'valor' => $condicao['operador'] === 'qualquer' ? null : ($condicao['valor'] ?? null),
                ]);
            }

            // Recriar ações
            $regraCondicional->acoes()->delete();
            foreach ($validated['acoes'] as $index => $acao) {
                $regraCondicional->acoes()->create([
                    'tipo_acao' => $acao['tipo_acao'],
                    'tabela_karnaugh_id' => $acao['tabela_karnaugh_id'] ?? null,
                    'produto_id' => $acao['produto_id'] ?? null,
                    'marcar' => $acao['marcar'] ?? true,
                    'quantidade' => $acao['quantidade'] ?? null,
                    'categoria' => $acao['categoria'] ?? null,
                    'ordem' => $index,
                ]);
            }
        });

        return redirect()
            ->route('assistente.regras.index')
            ->with('success', 'Regra atualizada com sucesso!');
    }

    /**
     * Excluir regra.
     */
    public function destroy(RegraCondicional $regraCondicional)
    {
        $nome = $regraCondicional->nome;
        $regraCondicional->delete();

        return redirect()
            ->route('assistente.regras.index')
            ->with('success', "Regra '{$nome}' excluída com sucesso!");
    }

    /**
     * Reordenar regras.
     */
    public function reordenar(Request $request)
    {
        $validated = $request->validate([
            'ordens' => 'required|array',
            'ordens.*.id' => 'required|exists:assistente_regras_condicionais,id',
            'ordens.*.ordem' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ordens'] as $item) {
                RegraCondicional::where('id', $item['id'])
                    ->update(['ordem' => $item['ordem']]);
            }
        });

        return back()->with('success', 'Ordem das regras atualizada!');
    }

    /**
     * Obter opções de valores para cada campo.
     */
    private function getOpcoesValores(): array
    {
        return [
            'gravidez' => ['Sim', 'Não'],
            'rosacea' => ['Sim', 'Não'],
            'fototipo' => ['1', '1.5', '2', '2.5', '3', '3.5', '4', '4.5'],
            'tipo_pele' => ['Seca', 'Normal', 'Mista Ressecada', 'Mista', 'Oleosa'],
            'manchas' => ['Pouca ou Nenhuma', 'Moderado', 'Intenso'],
            'rugas' => ['Pouca ou Nenhuma', 'Moderado', 'Intenso'],
            'acne' => ['Pouca ou Nenhuma', 'Moderado', 'Intenso'],
            'flacidez' => ['Pouca ou Nenhuma', 'Moderado', 'Intenso'],
        ];
    }
}
