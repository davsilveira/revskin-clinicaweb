<?php

namespace App\Console\Commands;

use App\Models\Receita;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReceitaLegadoId extends Command
{
    protected $signature = 'migration:backfill-receita-legado-id
                            {--dry-run : Apenas conta o que seria atualizado}
                            {--force : Persiste as alterações}';

    protected $description = 'Popula receitas.legado_id / numero_origem / origem a partir da tag [legado:ID|num:N] em anotacoes';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! $this->option('force');

        $rows = Receita::query()
            ->whereNull('legado_id')
            ->where('anotacoes', 'like', '%[legado:%')
            ->get(['id', 'anotacoes', 'legado_id', 'numero_origem', 'origem']);

        $ok = 0;
        $skip = 0;
        $conflito = 0;

        foreach ($rows as $receita) {
            if (! preg_match('/\[legado:(\d+)\|num:([^\]]*)\]/', (string) $receita->anotacoes, $m)) {
                $skip++;

                continue;
            }

            $legadoId = (int) $m[1];
            $numeroOrigem = trim($m[2]) !== '' ? trim($m[2]) : null;

            $outro = Receita::where('legado_id', $legadoId)->where('id', '!=', $receita->id)->exists();
            if ($outro) {
                $conflito++;
                $this->warn("Conflito: legado_id={$legadoId} já usado; receita #{$receita->id} pulada");

                continue;
            }

            $ok++;
            if (! $dryRun) {
                DB::table('receitas')->where('id', $receita->id)->update([
                    'legado_id' => $legadoId,
                    'numero_origem' => $receita->numero_origem ?: $numeroOrigem,
                    'origem' => $receita->origem ?: 'clw2_importada',
                ]);
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Atualizáveis: {$ok} | sem tag parseável: {$skip} | conflitos: {$conflito}");
        if ($dryRun && $ok > 0) {
            $this->line('Rode com --force para persistir.');
        }

        return $conflito > 0 ? 1 : 0;
    }
}
