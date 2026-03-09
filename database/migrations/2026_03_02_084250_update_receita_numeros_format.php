<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $receitas = DB::table('receitas')
            ->orderBy('paciente_id')
            ->orderBy('id')
            ->get(['id', 'paciente_id']);

        $counters = [];

        foreach ($receitas as $receita) {
            $pid = $receita->paciente_id;
            $counters[$pid] = ($counters[$pid] ?? 0) + 1;

            $novoNumero = $pid . '-' . str_pad($counters[$pid], 4, '0', STR_PAD_LEFT);

            DB::table('receitas')
                ->where('id', $receita->id)
                ->update(['numero' => $novoNumero]);
        }
    }

    public function down(): void
    {
        // Cannot reliably restore old format
    }
};
