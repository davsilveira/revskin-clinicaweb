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
        // Remove foreign key constraint from medicos table
        Schema::table('medicos', function (Blueprint $table) {
            $table->dropForeign(['tabela_preco_id']);
            $table->dropColumn('tabela_preco_id');
        });

        // Drop tabela_preco_itens first (has foreign key to tabelas_preco)
        Schema::dropIfExists('tabela_preco_itens');
        
        // Then drop tabelas_preco
        Schema::dropIfExists('tabelas_preco');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate tabelas_preco
        Schema::create('tabelas_preco', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Recreate tabela_preco_itens
        Schema::create('tabela_preco_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tabela_preco_id')->constrained('tabelas_preco')->onDelete('cascade');
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->decimal('preco', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['tabela_preco_id', 'produto_id']);
        });

        // Add back column to medicos
        Schema::table('medicos', function (Blueprint $table) {
            $table->foreignId('tabela_preco_id')->nullable()->constrained('tabelas_preco')->nullOnDelete();
        });
    }
};
