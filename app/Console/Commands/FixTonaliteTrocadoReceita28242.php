<?php

namespace App\Console\Commands;

use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Correção pontual (job 652dea09): receita 18008-0001 / pedido 991291713.
 * O pedido foi faturado com TONALITE 4,5, reaberto, trocado por TONALITE 2 e faturado
 * de novo; o webhook criou uma linha nova para o Tonalite 2 e manteve o 4,5 vendido.
 *
 * Troca o produto da linha original (mantém a posição na prescrição e a aquisição do
 * pedido) e remove a linha duplicada. Dry-run por padrão; só grava se todos os
 * predicados de segurança baterem.
 */
class FixTonaliteTrocadoReceita28242 extends Command
{
    private const RECEITA_ID = 28242;

    private const RECEITA_NUMERO = '18008-0001';

    private const TINY_PEDIDO_ID = '991291713';

    private const ITEM_ERRADO_ID = 349612;

    private const ITEM_ERRADO_TINY_ID = '889822348'; // TONALITE 4,5 30G

    private const ITEM_DUPLICADO_ID = 349795;

    private const ITEM_CERTO_TINY_ID = '889822324'; // TONALITE 2 30G

    private const VALOR_TOTAL_ESPERADO = '1527.00';

    protected $signature = 'tiny:fix-tonalite-trocado-28242
                            {--force : Aplica a correção (sem esta flag, só simula)}';

    protected $description = 'Troca TONALITE 4,5 por TONALITE 2 na receita 28242 e remove a linha duplicada (pedido Tiny 991291713). Dry-run por padrão.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->info('Correção pontual: Tonalite trocado (receita 28242 / pedido 991291713)');
        $this->line('Modo: '.($force ? 'FORCE (vai gravar)' : 'DRY-RUN (nada é gravado)'));

        $receita = Receita::find(self::RECEITA_ID);
        if (! $receita) {
            $this->error('Receita '.self::RECEITA_ID.' não encontrada.');

            return 1;
        }

        if ($receita->numero !== self::RECEITA_NUMERO) {
            $this->error('Número da receita diverge: esperado '.self::RECEITA_NUMERO.", achou {$receita->numero}.");

            return 1;
        }

        if ((string) $receita->tiny_pedido_id !== self::TINY_PEDIDO_ID) {
            $this->error('tiny_pedido_id diverge: esperado '.self::TINY_PEDIDO_ID.', achou '.var_export($receita->tiny_pedido_id, true).'.');

            return 1;
        }

        $errado = ReceitaItem::with(['produto', 'aquisicoes'])->find(self::ITEM_ERRADO_ID);
        $duplicado = ReceitaItem::with(['produto', 'aquisicoes'])->find(self::ITEM_DUPLICADO_ID);

        foreach ([[$errado, self::ITEM_ERRADO_ID, self::ITEM_ERRADO_TINY_ID], [$duplicado, self::ITEM_DUPLICADO_ID, self::ITEM_CERTO_TINY_ID]] as [$item, $id, $tinyId]) {
            if (! $item || (int) $item->receita_id !== self::RECEITA_ID) {
                $this->error("Item #{$id} não encontrado nesta receita; abortando (já corrigido?).");

                return 1;
            }
            if ((string) $item->produto?->tiny_id !== $tinyId) {
                $this->error("Item #{$id} não é o produto esperado (tiny_id {$tinyId}); abortando.");

                return 1;
            }
            if (! $item->vendido || $item->aquisicoes->where('tiny_pedido_id', self::TINY_PEDIDO_ID)->count() !== 1) {
                $this->error("Item #{$id} não está vendido com 1 aquisição do pedido; abortando.");

                return 1;
            }
            $aq = $item->aquisicoes->map(fn ($a) => "aq#{$a->id}/tp={$a->tiny_pedido_id}")->implode(', ');
            $this->line("  - item#{$item->id} {$item->produto->codigo} qtd={$item->quantidade} vu={$item->valor_unitario} ordem={$item->ordem} aq=[{$aq}]");
        }

        $this->line("Total atual: {$receita->valor_total}");
        $this->warn('Item #'.self::ITEM_ERRADO_ID.' passa a ser '.$duplicado->produto->codigo.'; item #'.self::ITEM_DUPLICADO_ID.' e aquisições '
            .$duplicado->aquisicoes->pluck('id')->implode(', ').' serão removidos.');

        if (! $force) {
            $this->info('Dry-run OK. Rode com --force para aplicar.');

            return 0;
        }

        DB::transaction(function () use ($receita, $errado, $duplicado) {
            $errado->update([
                'produto_id' => $duplicado->produto_id,
                'local_uso' => $duplicado->local_uso,
                'quantidade' => $duplicado->quantidade,
                'valor_unitario' => $duplicado->valor_unitario,
                'valor_total' => $duplicado->valor_total,
            ]);
            ReceitaItemAquisicao::where('receita_item_id', $duplicado->id)->delete();
            $duplicado->delete();
            $receita->refresh();
            $receita->calcularTotais();
        });

        $receita->refresh();
        $this->info("Aplicado. Total da receita: {$receita->valor_total}");
        if ((string) $receita->valor_total !== self::VALOR_TOTAL_ESPERADO) {
            $this->error('Atenção: total diferente do esperado ('.self::VALOR_TOTAL_ESPERADO.') — verificar manualmente.');

            return 1;
        }

        return 0;
    }
}
