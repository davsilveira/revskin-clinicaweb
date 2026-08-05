<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documento livre (passaporte / ID estrangeiro hoje; RG ou outro no futuro).
     * A coluna `cpf` continua sendo só CPF — sem migração de dados.
     */
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('outro_documento', 50)->nullable()->after('cpf');
            $table->index('outro_documento');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropIndex(['outro_documento']);
            $table->dropColumn('outro_documento');
        });
    }
};
