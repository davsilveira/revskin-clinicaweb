<?php

namespace App\Console\Commands;

use App\Models\Produto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArquivarProdutosLegadoOrfaos extends Command
{
    protected $signature = 'produtos:arquivar-legado-orfao
                            {--fix : Desativa produtos legado sem linhas em receita_itens}
                            {--purge : Com --fix, apaga em vez de só desativar (sem referência em receita_itens)}
                            {--reativar : Reverte (ativo=1) legados órfãos — uso excepcional}';

    protected $description = 'Desativa ou remove produtos legado_somente_leitura órfãos (após relink)';

    public function handle(): int
    {
        $fix = $this->option('fix');
        $reativar = $this->option('reativar');

        $query = Produto::query()->where('legado_somente_leitura', true);

        if ($reativar) {
            if (! $fix) {
                $this->warn('Use --fix com --reativar para aplicar.');

                return 1;
            }
            $n = $query->where('ativo', false)->update(['ativo' => true]);
            $this->info("Reativados {$n} produtos legado.");

            return 0;
        }

        $orfaos = $query
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('receita_itens')
                    ->whereColumn('receita_itens.produto_id', 'produtos.id');
            })
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome', 'ativo']);

        if ($orfaos->isEmpty()) {
            $this->info('Nenhum produto legado órfão (todos ainda têm linhas em receita_itens).');

            return 0;
        }

        foreach ($orfaos as $p) {
            $this->line("  {$p->codigo} (ativo=".($p->ativo ? 'sim' : 'não').')');
        }

        $this->newLine();
        $this->info('Órfãos: '.$orfaos->count().' (sem uso em receita_itens)');

        if (! $fix) {
            $this->line('Dry-run. Use --fix para definir ativo=0 nestes produtos.');

            return 0;
        }

        $ids = $orfaos->pluck('id');

        if ($this->option('purge')) {
            $n = Produto::query()->whereIn('id', $ids)->delete();
            $this->info("Removidos do catálogo: {$n} produtos legado órfãos.");

            return 0;
        }

        $n = Produto::query()->whereIn('id', $ids)->update(['ativo' => false]);
        $this->info("Arquivados (ativo=0): {$n}. Não aparecem em «Descontinuados (pendentes)».");

        return 0;
    }
}
