<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\RegraCondicional;
use App\Models\TabelaKarnaugh;

class RegrasCondicionaisEngine
{
    /**
     * Resultado do processamento das regras.
     */
    private ?TabelaKarnaugh $tabelaSelecionada = null;
    private array $produtosAdicionados = [];
    private array $produtosRemovidos = [];
    private array $regrasAplicadas = [];

    /**
     * Processar regras condicionais com base na avaliação clínica.
     *
     * @param array $avaliacaoClinica Dados da avaliação clínica
     * @return self
     */
    public function processar(array $avaliacaoClinica): self
    {
        // 1. Primeiro, processar regras de seleção de tabela
        $regrasSelecao = RegraCondicional::getRegrasSelecaoTabela();

        foreach ($regrasSelecao as $regra) {
            if ($regra->verificarCondicoes($avaliacaoClinica)) {
                $this->regrasAplicadas[] = $regra;
                $this->aplicarAcoes($regra);
            }
        }

        // Se nenhuma regra definiu tabela, usar a padrão
        if ($this->tabelaSelecionada === null) {
            $this->tabelaSelecionada = TabelaKarnaugh::getPadrao();
        }

        // 2. Depois, processar regras de modificação apenas para a tabela selecionada
        if ($this->tabelaSelecionada) {
            $regrasModificacao = RegraCondicional::getRegrasModificacaoParaTabela(
                $this->tabelaSelecionada->id
            );

            foreach ($regrasModificacao as $regra) {
                if ($regra->verificarCondicoes($avaliacaoClinica)) {
                    $this->regrasAplicadas[] = $regra;
                    $this->aplicarAcoes($regra);
                }
            }
        }

        return $this;
    }

    /**
     * Aplicar ações de uma regra.
     */
    private function aplicarAcoes(RegraCondicional $regra): void
    {
        foreach ($regra->acoes as $acao) {
            switch ($acao->tipo_acao) {
                case 'usar_tabela':
                    if ($acao->tabelaKarnaugh && $acao->tabelaKarnaugh->ativo) {
                        // Última regra vence em conflito
                        $this->tabelaSelecionada = $acao->tabelaKarnaugh;
                    }
                    break;

                case 'adicionar_item':
                    if ($acao->produto_id) {
                        $this->produtosAdicionados[$acao->produto_id] = [
                            'produto_id' => $acao->produto_id,
                            'marcar' => $acao->marcar,
                            'categoria' => $acao->categoria,
                        ];
                    }
                    break;

                case 'remover_item':
                    if ($acao->produto_id) {
                        $this->produtosRemovidos[$acao->produto_id] = true;
                    }
                    break;
            }
        }
    }

    /**
     * Obter produtos sugeridos para um caso clínico.
     *
     * @param string $casoClinico Código do caso clínico (ex: PSM1R1A1)
     * @return array Lista de produtos sugeridos com flag de marcação
     */
    public function obterProdutosSugeridos(string $casoClinico): array
    {
        $produtosSugeridos = [];

        // 1. Buscar produtos da tabela Karnaugh selecionada
        if ($this->tabelaSelecionada) {
            $produtosTabela = $this->tabelaSelecionada->buscarProdutosPorCaso($casoClinico);
            
            foreach ($produtosTabela as $item) {
                // Buscar produto pelo código
                $produto = $this->buscarProdutoPorCodigo($item['produto_codigo']);
                
                $produtoId = $produto?->id;
                
                // Verificar se não está na lista de remoção
                if ($produtoId && isset($this->produtosRemovidos[$produtoId])) {
                    continue;
                }

                $produtosSugeridos[] = [
                    'produto_id' => $produtoId,
                    'produto' => $produto,
                    'categoria' => $item['categoria'],
                    'produto_codigo' => $item['produto_codigo'],
                    'grupo' => $item['grupo'],
                    // Produtos do primeiro grupo e com flag "marcar" vêm selecionados
                    'selecionado' => $item['marcar'] && $item['grupo'] === 'primeiro',
                    'origem' => 'tabela_karnaugh',
                ];
            }
        }

        // 2. Adicionar produtos das regras de "adicionar_item"
        foreach ($this->produtosAdicionados as $produtoId => $info) {
            // Verificar se não está na lista de remoção
            if (isset($this->produtosRemovidos[$produtoId])) {
                continue;
            }

            // Verificar se já não está na lista
            $jaExiste = false;
            foreach ($produtosSugeridos as $ps) {
                if ($ps['produto_id'] === $produtoId) {
                    $jaExiste = true;
                    break;
                }
            }

            if (!$jaExiste) {
                $produto = Produto::find($produtoId);
                if ($produto) {
                    $produtosSugeridos[] = [
                        'produto_id' => $produtoId,
                        'produto' => $produto,
                        'categoria' => $info['categoria'] ?? 'Regra Condicional',
                        'produto_codigo' => $produto->codigo ?? $produto->nome,
                        'grupo' => 'regra',
                        'selecionado' => $info['marcar'],
                        'origem' => 'regra_condicional',
                    ];
                }
            }
        }

        // 3. Remover produtos da lista de remoção (já filtrados acima)

        return $produtosSugeridos;
    }

    /**
     * Buscar produto pelo código ou nome.
     */
    private function buscarProdutoPorCodigo(string $codigo): ?Produto
    {
        // Primeiro, tentar buscar pelo código exato
        $produto = Produto::where('codigo', $codigo)
            ->where('ativo', true)
            ->first();

        if ($produto) {
            return $produto;
        }

        // Tentar buscar pelo nome exato
        $produto = Produto::where('nome', $codigo)
            ->where('ativo', true)
            ->first();

        if ($produto) {
            return $produto;
        }

        // Tentar buscar parcial no nome ou código
        $produto = Produto::where('ativo', true)
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', 'like', "%{$codigo}%")
                    ->orWhere('nome', 'like', "%{$codigo}%");
            })
            ->first();

        return $produto;
    }

    /**
     * Obter a tabela Karnaugh selecionada.
     */
    public function getTabelaSelecionada(): ?TabelaKarnaugh
    {
        return $this->tabelaSelecionada;
    }

    /**
     * Obter regras que foram aplicadas.
     */
    public function getRegrasAplicadas(): array
    {
        return $this->regrasAplicadas;
    }

    /**
     * Obter produtos adicionados por regras.
     */
    public function getProdutosAdicionados(): array
    {
        return $this->produtosAdicionados;
    }

    /**
     * Obter produtos removidos por regras.
     */
    public function getProdutosRemovidos(): array
    {
        return $this->produtosRemovidos;
    }

    /**
     * Gerar código Karnaugh baseado na avaliação clínica.
     */
    public static function gerarCodigoKarnaugh(array $avaliacaoClinica): string
    {
        $tipoPeleMap = [
            'Seca' => 'PS',
            'Normal' => 'PN',
            'Mista Ressecada' => 'PR',
            'Mista' => 'PM',
            'Oleosa' => 'PO',
        ];

        $intensidadeMap = [
            'Pouca ou Nenhuma' => 1,
            'Moderado' => 2,
            'Intenso' => 3,
        ];

        $tipoPele = $tipoPeleMap[$avaliacaoClinica['tipo_pele'] ?? ''] ?? 'PN';
        $manchas = $intensidadeMap[$avaliacaoClinica['manchas'] ?? ''] ?? 1;
        $rugas = $intensidadeMap[$avaliacaoClinica['rugas'] ?? ''] ?? 1;
        $acne = $intensidadeMap[$avaliacaoClinica['acne'] ?? ''] ?? 1;

        // Formato: PSM1R1A1 (Pele + Manchas + Rugas + Acne)
        return "{$tipoPele}M{$manchas}R{$rugas}A{$acne}";
    }
}
