<?php

namespace App\Console\Commands;

use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Correção pontual do race no webhook Tiny (job 152b8cd1):
 * receita 27796 / pedido 981442812 ficou com 2 linhas verdes de DESINFLAM CLINDA 30G.
 *
 * Dry-run por padrão. Só remove se TODOS os predicados de segurança baterem.
 */
class FixDesinflamDuplicadoReceita27796 extends Command
{
    private const RECEITA_ID = 27796;

    private const RECEITA_NUMERO = '17483-0002';

    private const TINY_PEDIDO_ID = '981442812';

    private const PRODUTO_CODIGO = 'DESINFLAM CLINDA 30G';

    private const PRODUTO_TINY_ID = '964350909';

    protected $signature = 'tiny:fix-desinflam-duplicado-27796
                            {--force : Aplica a remoção (sem esta flag, só simula)}';

    protected $description = 'Remove a linha duplicada de DESINFLAM CLINDA 30G na receita 27796 (pedido Tiny 981442812). Dry-run por padrão.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->info('Correção pontual: DESINFLAM duplicado (receita 27796 / pedido 981442812)');
        $this->line('Modo: '.($force ? 'FORCE (vai gravar)' : 'DRY-RUN (nada é gravado)'));

        $receita = Receita::with(['itens.produto', 'itens.aquisicoes'])->find(self::RECEITA_ID);
        if (! $receita) {
            $this->error('Receita '.self::RECEITA_ID.' não encontrada.');

            return 1;
        }

        if ($receita->numero !== self::RECEITA_NUMERO) {
            $this->error("Número da receita diverge: esperado ".self::RECEITA_NUMERO.", achou {$receita->numero}.");

            return 1;
        }

        if ((string) $receita->tiny_pedido_id !== self::TINY_PEDIDO_ID) {
            $this->error("tiny_pedido_id diverge: esperado ".self::TINY_PEDIDO_ID.", achou ".var_export($receita->tiny_pedido_id, true).".");

            return 1;
        }

        $duplicadas = $receita->itens
            ->filter(function (ReceitaItem $item) {
                $produto = $item->produto;
                if (! $produto) {
                    return false;
                }

                return $produto->codigo === self::PRODUTO_CODIGO
                    && (string) $produto->tiny_id === self::PRODUTO_TINY_ID;
            })
            ->sortBy('id')
            ->values();

        $this->line('Linhas DESINFLAM encontradas: '.$duplicadas->count());
        foreach ($duplicadas as $item) {
            $aq = $item->aquisicoes->map(fn ($a) => "aq#{$a->id}/tp={$a->tiny_pedido_id}")->implode(', ');
            $this->line("  - item#{$item->id} qtd={$item->quantidade} vu={$item->valor_unitario} vendido=".($item->vendido ? '1' : '0')." aq=[{$aq}]");
        }

        if ($duplicadas->count() !== 2) {
            $this->error('Esperado exatamente 2 linhas DESINFLAM; abortando (não há o que corrigir ou estado inesperado).');

            return 1;
        }

        $manter = $duplicadas->first();
        $remover = $duplicadas->last();

        if ((int) $manter->id >= (int) $remover->id) {
            $this->error('Ordenação inesperada dos IDs; abortando.');

            return 1;
        }

        foreach ([$manter, $remover] as $item) {
            if (! $item->vendido) {
                $this->error("Item #{$item->id} não está vendido; abortando.");

                return 1;
            }
            $aqPedido = $item->aquisicoes->where('tiny_pedido_id', self::TINY_PEDIDO_ID);
            if ($aqPedido->count() < 1) {
                $this->error("Item #{$item->id} sem aquisição do pedido ".self::TINY_PEDIDO_ID.'; abortando.');

                return 1;
            }
        }

        $aqIds = $remover->aquisicoes->pluck('id')->all();
        $this->warn("Manterá item #{$manter->id}; removerá item #{$remover->id} e aquisições: ".implode(', ', $aqIds));

        if (! $force) {
            $this->info('Dry-run OK. Rode com --force para aplicar.');

            return 0;
        }

        DB::transaction(function () use ($receita, $remover) {
            ReceitaItemAquisicao::where('receita_item_id', $remover->id)->delete();
            $remover->delete();
            $receita->refresh();
            $receita->calcularTotais();
        });

        $restantes = ReceitaItem::where('receita_id', self::RECEITA_ID)
            ->whereHas('produto', fn ($q) => $q->where('codigo', self::PRODUTO_CODIGO))
            ->count();

        $this->info("Aplicado. Linhas DESINFLAM restantes na receita: {$restantes}");
        if ($restantes !== 1) {
            $this->error('Atenção: após remoção o contador não é 1 — verificar manualmente.');

            return 1;
        }

        return 0;
    }
}
