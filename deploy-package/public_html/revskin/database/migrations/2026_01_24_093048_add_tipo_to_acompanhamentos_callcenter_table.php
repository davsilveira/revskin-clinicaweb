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
        Schema::table('acompanhamentos_callcenter', function (Blueprint $table) {
            $table->string('tipo')->default('observacao')->after('usuario_id');
            // Tipos: ligacao, whatsapp, email, observacao
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acompanhamentos_callcenter', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
