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
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('codigo_cq')->nullable(); // Código CQ
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->text('anotacoes')->nullable();
            $table->string('local_uso')->nullable(); // Local de uso (face, corpo, etc)
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Price table items - links produtos to tabelas_preco with price
        Schema::create('tabela_preco_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tabela_preco_id')->constrained('tabelas_preco')->onDelete('cascade');
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->decimal('preco', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['tabela_preco_id', 'produto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tabela_preco_itens');
        Schema::dropIfExists('produtos');
    }
};

