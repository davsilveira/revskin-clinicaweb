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
        Schema::table('assistente_regras_condicionais', function (Blueprint $table) {
            // Tipo de regra: selecao_tabela ou modificacao_tabela
            $table->enum('tipo', ['selecao_tabela', 'modificacao_tabela'])
                ->default('selecao_tabela')
                ->after('descricao');
            
            // Tabela alvo (obrigatório para regras de modificação)
            $table->foreignId('tabela_karnaugh_id')
                ->nullable()
                ->after('tipo')
                ->constrained('tabelas_karnaugh')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistente_regras_condicionais', function (Blueprint $table) {
            $table->dropForeign(['tabela_karnaugh_id']);
            $table->dropColumn(['tipo', 'tabela_karnaugh_id']);
        });
    }
};
