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
        // Casos clínicos do assistente (combinações de respostas)
        Schema::create('assistente_casos_clinicos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('tipo_pele')->nullable(); // Normal, Oleosa, Seca, Mista
            $table->string('manchas')->nullable(); // Sim, Não, Leve, Moderado, Intenso
            $table->string('rugas')->nullable(); // Sim, Não, Leve, Moderado, Intenso
            $table->string('acne')->nullable(); // Sim, Não, Leve, Moderado, Intenso
            $table->string('flacidez')->nullable(); // Sim, Não, Leve, Moderado, Intenso
            $table->string('faixa_etaria')->nullable();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Tratamentos sugeridos para cada caso clínico
        Schema::create('assistente_tratamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_clinico_id')->constrained('assistente_casos_clinicos')->onDelete('cascade');
            $table->string('codigo')->nullable();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Itens do tratamento (produtos sugeridos)
        Schema::create('assistente_tratamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tratamento_id')->constrained('assistente_tratamentos')->onDelete('cascade');
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->string('local_uso')->nullable();
            $table->text('anotacoes')->nullable();
            $table->integer('quantidade')->default(1);
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Regras do assistente (tabela Karnaugh - spreadsheet data)
        Schema::create('assistente_regras', function (Blueprint $table) {
            $table->id();
            $table->string('linha_id')->nullable(); // ID da linha na planilha
            $table->json('condicoes'); // JSON com as condições (tipo_pele, manchas, etc)
            $table->json('produtos'); // JSON com os produtos sugeridos
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistente_regras');
        Schema::dropIfExists('assistente_tratamento_itens');
        Schema::dropIfExists('assistente_tratamentos');
        Schema::dropIfExists('assistente_casos_clinicos');
    }
};










