<?php

namespace App\Support;

use App\Models\Produto;
use App\Models\Receita;
use Illuminate\Validation\ValidationException;

class ReceitaProdutoLegadoGuard
{
    /**
     * Receita nova não pode incluir produtos marcados como legado (somente importação).
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    public static function assertNovaReceitaSemProdutoLegado(array $itens): void
    {
        foreach ($itens as $idx => $item) {
            $pid = (int) ($item['produto_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $p = Produto::query()->find($pid);
            if ($p && $p->legado_somente_leitura) {
                throw ValidationException::withMessages([
                    "itens.{$idx}.produto_id" => ['Produtos descontinuados (importação) não podem ser adicionados a receitas novas.'],
                ]);
            }
        }
    }

    /**
     * Receita não pode ser finalizada enquanto contiver produtos legados (descontinuados).
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    public static function assertSemProdutoLegadoAoFinalizar(array $itens): void
    {
        foreach ($itens as $item) {
            $pid = (int) ($item['produto_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $p = Produto::query()->find($pid);
            if ($p && $p->legado_somente_leitura) {
                throw ValidationException::withMessages([
                    'status' => ['Substitua os produtos descontinuados (em vermelho) antes de finalizar a receita.'],
                ]);
            }
        }
    }

    /**
     * Linhas com produto legado devem manter os mesmos dados (não trocar produto nem alterar campos).
     *
     * @param  array<int, array<string, mixed>>  $itensIncoming
     */
    public static function assertItensLegadoInalterados(Receita $receita, array $itensIncoming): void
    {
        $receita->loadMissing('itens');

        foreach ($itensIncoming as $idx => $itemData) {
            $produtoId = (int) ($itemData['produto_id'] ?? 0);
            if ($produtoId <= 0) {
                continue;
            }

            $produto = Produto::query()->find($produtoId);
            if (! $produto || ! $produto->legado_somente_leitura) {
                continue;
            }

            $itemId = isset($itemData['id']) ? (int) $itemData['id'] : 0;
            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    "itens.{$idx}.produto_id" => ['Não é permitido incluir ou duplicar produto descontinuado.'],
                ]);
            }

            $existing = $receita->itens->firstWhere('id', $itemId);
            if (! $existing || (int) $existing->produto_id !== $produtoId) {
                throw ValidationException::withMessages([
                    "itens.{$idx}.produto_id" => ['Produto descontinuado não pode ser alterado ou movido de linha.'],
                ]);
            }

            $checks = [
                'quantidade' => (int) $existing->quantidade === (int) ($itemData['quantidade'] ?? 0),
                'local_uso' => (string) ($existing->local_uso ?? '') === (string) ($itemData['local_uso'] ?? ''),
                'anotacoes' => (string) ($existing->anotacoes ?? '') === (string) ($itemData['anotacoes'] ?? ''),
                'imprimir' => (bool) $existing->imprimir === (bool) ($itemData['imprimir'] ?? true),
                'grupo' => (string) ($existing->grupo ?? 'recomendado') === (string) ($itemData['grupo'] ?? 'recomendado'),
            ];

            $vuExisting = round((float) $existing->valor_unitario, 2);
            $vuIncoming = round((float) ($itemData['valor_unitario'] ?? 0), 2);
            $checks['valor_unitario'] = abs($vuExisting - $vuIncoming) < 0.01;

            foreach ($checks as $ok) {
                if (! $ok) {
                    throw ValidationException::withMessages([
                        "itens.{$idx}" => ['Esta linha contém produto descontinuado e não pode ser modificada.'],
                    ]);
                }
            }
        }
    }
}
