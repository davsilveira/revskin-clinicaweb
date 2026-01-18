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
        Schema::table('tabela_karnaugh_produtos', function (Blueprint $table) {
            $table->integer('sequencia_coluna')->default(0)->after('ordem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tabela_karnaugh_produtos', function (Blueprint $table) {
            $table->dropColumn('sequencia_coluna');
        });
    }
};
