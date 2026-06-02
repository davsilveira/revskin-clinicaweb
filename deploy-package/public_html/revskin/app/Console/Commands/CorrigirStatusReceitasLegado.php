<?php

namespace App\Console\Commands;

use App\Models\Receita;
use Illuminate\Console\Command;

class CorrigirStatusReceitasLegado extends Command
{
    protected $signature = 'migration:corrigir-status-receitas-legado
                            {--source=docs/migration : Diretório com receitas.json}
                            {--fix : Aplica UPDATE em receitas.status}';

    protected $description = 'Atualiza status de receitas importadas conforme receitas.json (ex.: aberta vs finalizada)';

    public function handle(): int
    {
        $fix = $this->option('fix');
        $jsonPath = base_path(rtrim($this->option('source'), '/').'/receitas.json');

        if (! is_file($jsonPath)) {
            $this->error("Arquivo não encontrado: {$jsonPath}");

            return 1;
        }

        $receitasJson = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($receitasJson)) {
            $this->error('receitas.json inválido.');

            return 1;
        }

        $statusByLegado = collect($receitasJson)->mapWithKeys(fn ($r) => [
            (int) ($r['legado_id'] ?? 0) => $r['status'] ?? 'finalizada',
        ]);

        $atualizados = 0;
        $porStatus = ['aberta' => 0, 'finalizada' => 0, 'cancelada' => 0];

        $receitas = Receita::query()
            ->where('anotacoes', 'like', '%[legado:%')
            ->get();

        foreach ($receitas as $receita) {
            if (! preg_match('/\[legado:(\d+)\|/', (string) $receita->anotacoes, $m)) {
                continue;
            }
            $legadoId = (int) $m[1];
            $novoStatus = $statusByLegado->get($legadoId);
            if (! $novoStatus || $receita->status === $novoStatus) {
                continue;
            }

            $this->line("  Receita #{$receita->id}: {$receita->status} → {$novoStatus}");
            $porStatus[$novoStatus] = ($porStatus[$novoStatus] ?? 0) + 1;

            if ($fix) {
                $receita->update(['status' => $novoStatus]);
            }
            $atualizados++;
        }

        $this->info(($fix ? 'Atualizadas' : 'Seriam atualizadas').": {$atualizados}");
        foreach ($porStatus as $st => $n) {
            if ($n > 0) {
                $this->line("  → {$st}: {$n}");
            }
        }

        return 0;
    }
}
