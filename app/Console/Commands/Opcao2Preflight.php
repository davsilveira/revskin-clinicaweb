<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Opção 2 — checagem pré-backfill (somente leitura).
 *
 * Sai com código ≠ 0 se houver colisão de CPF (que bloquearia o unique global),
 * para poder travar o pipeline de deploy.
 */
class Opcao2Preflight extends Command
{
    protected $signature = 'pacientes:opcao2-preflight';

    protected $description = 'Opção 2: relatório pré-backfill (CPF duplicado, pacientes sem médico, homônimos, linhas a criar).';

    public function handle(): int
    {
        $total = DB::table('pacientes')->count();
        $comMedico = DB::table('pacientes')->whereNotNull('medico_id')->count();
        $semMedico = $total - $comMedico;

        $this->info('== Opção 2 · Preflight ==');
        $this->line("Pacientes: {$total}  (com medico_id: {$comMedico} · sem: {$semMedico})");
        $this->line('Linhas que o backfill criaria no pivot: '.$comMedico);

        // Colisões de CPF (bloqueiam o unique global de pacientes.cpf)
        $cpfDups = DB::table('pacientes')
            ->whereNotNull('cpf')->where('cpf', '!=', '')
            ->select('cpf', DB::raw('COUNT(*) as c'))
            ->groupBy('cpf')->having('c', '>', 1)->get();

        // Homônimos entre médicos diferentes (revisão manual — não bloqueia)
        $nomeDups = DB::table('pacientes')
            ->select(DB::raw('LOWER(TRIM(nome)) as n'), DB::raw('COUNT(*) as c'))
            ->groupBy('n')->having('c', '>', 1)->get();

        $this->line('Grupos de nome duplicado (possíveis homônimos): '.$nomeDups->count());

        if ($cpfDups->isNotEmpty()) {
            $this->error('COLISÃO DE CPF: '.$cpfDups->count().' CPF(s) em mais de um paciente. Resolva antes do unique global.');
            foreach ($cpfDups as $d) {
                $this->line("  cpf={$d->cpf} → {$d->c} registros");
            }

            return self::FAILURE;
        }

        $this->info('OK: nenhuma colisão de CPF. Seguro para backfill.');

        return self::SUCCESS;
    }
}
