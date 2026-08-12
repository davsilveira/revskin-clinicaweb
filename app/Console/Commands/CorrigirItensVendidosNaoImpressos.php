<?php

namespace App\Console\Commands;

use App\Models\Receita;
use App\Models\ReceitaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Correção do bug do merge do webhook Tiny (job 51fdbc50):
 * item incluído no pedido do oList depois da receita finalizada ficava com
 * vendido=1 mas imprimir=0, e calcularTotais() só soma itens com imprimir=1 —
 * ou seja, o valor não entrava na receita.
 *
 * Só toca em itens com prova de compra: vendido=1 + aquisição registrada com o
 * mesmo tiny_pedido_id da receita. Dry-run por padrão.
 */
class CorrigirItensVendidosNaoImpressos extends Command
{
    protected $signature = 'tiny:corrigir-itens-vendidos-nao-impressos
                            {--force : Aplica as correções (sem esta flag, só simula)}
                            {--receita= : Restringe a uma receita (id numérico ou número, ex. 17401-0008)}';

    protected $description = 'Marca imprimir=1 em itens vendidos no pedido do oList que ficaram desmarcados, e recalcula os totais das receitas afetadas. Dry-run por padrão.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $filtro = $this->option('receita');

        $this->info('Itens vendidos no oList que ficaram com imprimir=0');
        $this->line('Modo: '.($force ? 'FORCE (vai gravar)' : 'DRY-RUN (nada é gravado)'));

        $query = ReceitaItem::query()
            ->where('imprimir', false)
            ->where('vendido', true)
            ->whereHas('receita', fn ($q) => $q->whereNotNull('tiny_pedido_id'))
            ->whereHas('aquisicoes', fn ($q) => $q->whereNotNull('tiny_pedido_id'))
            ->with(['receita', 'produto', 'aquisicoes']);

        if ($filtro) {
            $query->whereHas('receita', function ($q) use ($filtro) {
                $q->where('numero', $filtro);
                if (ctype_digit((string) $filtro)) {
                    $q->orWhere('id', (int) $filtro);
                }
            });
        }

        // A prova de compra é a aquisição do MESMO pedido da receita.
        $itens = $query->get()->filter(function (ReceitaItem $item) {
            $pedido = (string) ($item->receita->tiny_pedido_id ?? '');

            return $pedido !== ''
                && $item->aquisicoes->contains(fn ($a) => (string) $a->tiny_pedido_id === $pedido);
        })->values();

        if ($itens->isEmpty()) {
            $this->info('Nenhum item a corrigir.');

            return 0;
        }

        $porReceita = $itens->groupBy('receita_id');
        $this->line("Itens a corrigir: {$itens->count()} em {$porReceita->count()} receita(s)");

        foreach ($porReceita as $receitaId => $itensReceita) {
            /** @var Receita $receita */
            $receita = $itensReceita->first()->receita;
            $delta = $itensReceita->sum(fn (ReceitaItem $i) => (float) $i->valor_total);
            $this->line("- receita #{$receita->id} ({$receita->numero}, {$receita->status}) pedido={$receita->tiny_pedido_id} total_atual={$receita->valor_total} delta_previsto=+{$delta}");
            foreach ($itensReceita as $item) {
                $cod = $item->produto->codigo ?? '?';
                $this->line("    item#{$item->id} {$cod} qtd={$item->quantidade} vu={$item->valor_unitario} vt={$item->valor_total}");
            }
        }

        if (! $force) {
            $this->info('Dry-run OK. Rode com --force para aplicar.');

            return 0;
        }

        DB::transaction(function () use ($porReceita) {
            foreach ($porReceita as $itensReceita) {
                ReceitaItem::whereIn('id', $itensReceita->pluck('id'))->update(['imprimir' => true]);

                $receita = $itensReceita->first()->receita->refresh();
                $receita->calcularTotais();
            }
        });

        foreach ($porReceita as $itensReceita) {
            $receita = $itensReceita->first()->receita->refresh();
            $this->info("Aplicado: receita #{$receita->id} ({$receita->numero}) subtotal={$receita->subtotal} total={$receita->valor_total}");
        }

        return 0;
    }
}
