<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Opção 2 — verificação pós-backfill (somente leitura). Sai ≠ 0 se algo divergir.
 */
class Opcao2Verify extends Command
{
    protected $signature = 'pacientes:opcao2-verify';

    protected $description = 'Opção 2: confere paridade pacientes.medico_id ↔ pivot medico_paciente e ausência de órfãos.';

    public function handle(): int
    {
        $ok = true;

        // Todo paciente com medico_id deve ter a linha correspondente no pivot.
        $faltando = DB::table('pacientes as p')
            ->whereNotNull('p.medico_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('medico_paciente as mp')
                    ->whereColumn('mp.paciente_id', 'p.id')
                    ->whereColumn('mp.medico_id', 'p.medico_id');
            })->count();

        $this->info('== Opção 2 · Verify ==');
        $this->line('Pacientes com medico_id sem vínculo equivalente no pivot: '.$faltando);
        if ($faltando > 0) {
            $ok = false;
        }

        // Vínculos órfãos (paciente ou médico inexistente) — FK deveria impedir, mas conferimos.
        $orfaosPac = DB::table('medico_paciente as mp')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('pacientes as p')->whereColumn('p.id', 'mp.paciente_id'))
            ->count();
        $orfaosMed = DB::table('medico_paciente as mp')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('medicos as m')->whereColumn('m.id', 'mp.medico_id'))
            ->count();
        $this->line("Vínculos órfãos → paciente: {$orfaosPac} · médico: {$orfaosMed}");
        if ($orfaosPac > 0 || $orfaosMed > 0) {
            $ok = false;
        }

        // CPF duplicado (não deve haver)
        $cpfDup = DB::table('pacientes')->whereNotNull('cpf')->where('cpf', '!=', '')
            ->select('cpf', DB::raw('COUNT(*) as c'))->groupBy('cpf')->having('c', '>', 1)->count();
        $this->line('CPFs duplicados: '.$cpfDup);
        if ($cpfDup > 0) {
            $ok = false;
        }

        $totalPivot = DB::table('medico_paciente')->count();
        $this->line('Total de vínculos no pivot: '.$totalPivot);

        if ($ok) {
            $this->info('OK: verificação passou.');

            return self::SUCCESS;
        }

        $this->error('FALHOU: há divergências (ver acima).');

        return self::FAILURE;
    }
}
