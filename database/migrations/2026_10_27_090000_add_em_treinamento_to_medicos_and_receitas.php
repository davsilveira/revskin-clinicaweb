<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Médico em treinamento: as receitas que ele gera não vão para o oList nem para o RD Station.
 *
 * `medicos.em_treinamento` é o toggle do cadastro. `receitas.treinamento` guarda a marca na
 * própria receita (no momento em que ela nasce/é finalizada), para que tirar o médico do
 * treinamento depois não faça as receitas de teste "vazarem" num reenvio de job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicos', function (Blueprint $table) {
            $table->boolean('em_treinamento')->default(false)->after('ativo');
        });

        Schema::table('receitas', function (Blueprint $table) {
            $table->boolean('treinamento')->default(false)->after('cortesia');
        });
    }

    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->dropColumn('treinamento');
        });

        Schema::table('medicos', function (Blueprint $table) {
            $table->dropColumn('em_treinamento');
        });
    }
};
