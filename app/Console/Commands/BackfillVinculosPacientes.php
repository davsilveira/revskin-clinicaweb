<?php

namespace App\Console\Commands;

use App\Models\MedicoPaciente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Opção 2 — normalização: cria as linhas do pivot medico_paciente a partir do FK
 * legado `pacientes.medico_id`, movendo os campos privados (anotacoes/codigo/indicado_por).
 *
 * Idempotente: usa updateOrInsert por (medico_id, paciente_id); pode rodar N vezes.
 * Dry-run por padrão; aplica só com --force.
 */
class BackfillVinculosPacientes extends Command
{
    protected $signature = 'pacientes:backfill-vinculos {--force : aplica de fato (sem = dry-run)} {--chunk=500 : tamanho do lote}';

    protected $description = 'Opção 2: popula o pivot medico_paciente a partir de pacientes.medico_id (idempotente, dry-run por padrão).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $chunk = (int) $this->option('chunk');

        $q = DB::table('pacientes')->whereNotNull('medico_id');
        $total = $q->count();
        $this->info('== Backfill de vínculos ('.($force ? 'APLICANDO' : 'DRY-RUN').') ==');
        $this->line("Pacientes com medico_id: {$total}");

        $criados = 0;
        $atualizados = 0;
        $jaOk = 0;

        $q->orderBy('id')->chunkById($chunk, function ($rows) use ($force, &$criados, &$atualizados, &$jaOk) {
            foreach ($rows as $p) {
                $existente = MedicoPaciente::where('medico_id', $p->medico_id)
                    ->where('paciente_id', $p->id)->first();

                $dados = [
                    'anotacoes' => $p->anotacoes ?? null,
                    'codigo' => ($p->codigo ?? '') !== '' ? $p->codigo : null,
                    'indicado_por' => $p->indicado_por ?? null,
                    'ativo' => (bool) ($p->ativo ?? true),
                    'origem' => 'import',
                    'created_by_user_id' => $p->created_by_user_id ?? null,
                    'updated_by_user_id' => $p->updated_by_user_id ?? null,
                ];

                if (! $existente) {
                    $criados++;
                    if ($force) {
                        MedicoPaciente::create(array_merge([
                            'medico_id' => $p->medico_id,
                            'paciente_id' => $p->id,
                        ], $dados));
                    }
                } else {
                    // Já existe: não sobrescreve campos privados já editados; só conta.
                    $jaOk++;
                }
            }
        });

        $this->line("Vínculos a criar: {$criados} · já existentes (mantidos): {$jaOk}");
        if (! $force) {
            $this->warn('DRY-RUN: nada foi gravado. Rode com --force para aplicar.');
        } else {
            $this->info('Concluído. Vínculos criados: '.$criados);
        }

        return self::SUCCESS;
    }
}
