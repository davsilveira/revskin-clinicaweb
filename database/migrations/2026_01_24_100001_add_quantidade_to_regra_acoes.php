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
        Schema::table('assistente_regra_acoes', function (Blueprint $table) {
            // Quantidade para ação de modificar_quantidade
            $table->integer('quantidade')->nullable()->after('marcar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistente_regra_acoes', function (Blueprint $table) {
            $table->dropColumn('quantidade');
        });
    }
};
