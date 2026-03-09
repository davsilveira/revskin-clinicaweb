<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('receita_itens', function (Blueprint $table) {
            // Grupo do item: recomendado (marcado pelo sistema) ou opcional
            $table->string('grupo')->default('recomendado')->after('imprimir');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receita_itens', function (Blueprint $table) {
            $table->dropColumn('grupo');
        });
    }
};
