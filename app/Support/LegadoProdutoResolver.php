<?php

namespace App\Support;

use App\Models\Produto;
use Illuminate\Support\Collection;

final class LegadoProdutoResolver
{
    private const CODIGOS_IGNORAR = ['...', 'W-AMOSTRA'];

    /**
     * @param  Collection<string, Produto>  $produtoCache
     * @param  array<string, string>  $mapeamento
     */
    public static function findPorItemLegado(array $receitaItem, Collection $produtoCache, array $mapeamento): ?Produto
    {
        $legado = trim((string) ($receitaItem['codigo_produto_legado'] ?? ''));
        $codigoMapeado = trim((string) ($receitaItem['codigo_produto_mapeado'] ?? ''));
        $codigoBusca = $legado !== ''
            ? LegadoCodigoProdutoMapeamento::paraBase($legado, $mapeamento)
            : $codigoMapeado;

        if ($codigoBusca === '' || in_array($codigoBusca, self::CODIGOS_IGNORAR, true)) {
            return null;
        }

        $produto = self::findPorCodigo($codigoBusca, $produtoCache);
        if ($produto) {
            return $produto;
        }

        if ($legado !== '' && $legado !== $codigoBusca) {
            $produto = self::findPorCodigo($legado, $produtoCache);
            if ($produto) {
                return $produto;
            }
        }

        $descLegado = trim((string) ($receitaItem['descricao_produto_legado'] ?? ''));
        if ($descLegado !== '') {
            $parsed = LegadoProdutoDescricaoParser::parse($descLegado);
            if ($parsed['nome'] !== '') {
                return self::findPorNome($parsed['nome'], $produtoCache);
            }
        }

        return null;
    }

    /**
     * @param  Collection<string, Produto>  $produtoCache
     */
    public static function findPorCodigo(string $codigo, Collection $produtoCache): ?Produto
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        foreach (self::variantesCodigo($codigo) as $c) {
            $produto = $produtoCache->get($c);
            if ($produto && ! $produto->legado_somente_leitura) {
                return $produto;
            }

            $produto = Produto::query()
                ->where('legado_somente_leitura', false)
                ->where(function ($q) use ($c) {
                    $q->where('codigo', $c)
                        ->orWhere('codigo', 'like', $c.' %');
                })
                ->first();

            if ($produto) {
                $produtoCache->put($produto->codigo, $produto);

                return $produto;
            }
        }

        return null;
    }

    /**
     * @param  Collection<string, Produto>  $produtoCache
     */
    public static function findPorNome(string $nome, Collection $produtoCache, int $minScore = 85): ?Produto
    {
        $nomeNorm = self::normalizarNome($nome);
        if ($nomeNorm === '') {
            return null;
        }

        $melhor = null;
        $melhorScore = 0;

        foreach ($produtoCache as $produto) {
            if ($produto->legado_somente_leitura) {
                continue;
            }
            $score = 0;
            similar_text($nomeNorm, self::normalizarNome($produto->nome), $score);
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $produto;
            }
        }

        return $melhorScore >= $minScore ? $melhor : null;
    }

    /**
     * @return list<string>
     */
    public static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        $vars = [$codigo];
        $dash = str_replace(' ', '-', $codigo);
        $space = str_replace('-', ' ', $codigo);
        if ($dash !== $codigo) {
            $vars[] = $dash;
        }
        if ($space !== $codigo) {
            $vars[] = $space;
        }
        $semSufixo = preg_replace('/-(G\d+|G15|G30)$/i', '', $codigo);
        if ($semSufixo && $semSufixo !== $codigo) {
            $vars[] = $semSufixo;
        }

        return array_values(array_unique($vars));
    }

    public static function normalizarNome(string $nome): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($nome))) ?? '';
    }

    /**
     * @param  Collection<string, Produto>  $produtoCache
     * @param  array<string, string>  $mapeamento
     */
    public static function criarStubSeNecessario(
        array $receitaItem,
        Collection $produtoCache,
        array $mapeamento,
        bool $allowCreate
    ): ?Produto {
        if (! $allowCreate) {
            return null;
        }

        $legado = trim((string) ($receitaItem['codigo_produto_legado'] ?? ''));
        $codigoBusca = $legado !== ''
            ? LegadoCodigoProdutoMapeamento::paraBase($legado, $mapeamento)
            : trim((string) ($receitaItem['codigo_produto_mapeado'] ?? ''));

        if ($codigoBusca === '' || in_array($codigoBusca, self::CODIGOS_IGNORAR, true)) {
            return null;
        }

        $descLegado = trim((string) ($receitaItem['descricao_produto_legado'] ?? ''));
        $parsed = $descLegado !== ''
            ? LegadoProdutoDescricaoParser::parse($descLegado)
            : ['nome' => '', 'formula' => '', 'modo_uso' => ''];

        $nome = $parsed['nome'] !== ''
            ? mb_substr($parsed['nome'], 0, 255)
            : mb_substr($legado !== '' ? $legado : $codigoBusca, 0, 255);

        $codigoNovo = $legado !== '' ? mb_substr($legado, 0, 255) : $codigoBusca;
        if ($codigoNovo !== $codigoBusca && Produto::where('codigo', $codigoNovo)->exists()) {
            $codigoNovo = $codigoBusca;
        }

        $novo = Produto::create([
            'codigo' => $codigoNovo,
            'nome' => $nome,
            'descricao' => $parsed['formula'] !== '' ? $parsed['formula'] : null,
            'modo_uso' => $parsed['modo_uso'] !== '' ? $parsed['modo_uso'] : null,
            'legado_somente_leitura' => true,
            'ativo' => true,
            'preco' => (float) ($receitaItem['valor_unitario'] ?? 0),
        ]);
        $produtoCache->put($novo->codigo, $novo);

        return $novo;
    }
}
