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
        // Regras condicionais do assistente
        Schema::create('assistente_regras_condicionais', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->integer('ordem')->default(0); // ordem de avaliação (menor = primeiro)
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            
            $table->index('ordem');
        });

        // Condições de cada regra
        Schema::create('assistente_regra_condicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regra_id')
                ->constrained('assistente_regras_condicionais')
                ->onDelete('cascade');
            $table->string('campo'); // gravidez, rosacea, fototipo, tipo_pele, manchas, rugas, acne, flacidez
            $table->string('operador')->default('igual'); // igual, diferente, qualquer
            $table->string('valor')->nullable(); // valor esperado ou null para "qualquer"
            $table->timestamps();
            
            $table->index(['regra_id', 'campo']);
        });

        // Ações de cada regra
        Schema::create('assistente_regra_acoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regra_id')
                ->constrained('assistente_regras_condicionais')
                ->onDelete('cascade');
            $table->string('tipo_acao'); // usar_tabela, adicionar_item, remover_item
            $table->foreignId('tabela_karnaugh_id')
                ->nullable()
                ->constrained('tabelas_karnaugh')
                ->onDelete('set null'); // para ação "usar_tabela"
            $table->foreignId('produto_id')
                ->nullable()
                ->constrained('produtos')
                ->onDelete('cascade'); // para ações adicionar/remover item
            $table->boolean('marcar')->default(true); // se deve vir marcado (para adicionar_item)
            $table->string('categoria')->nullable(); // categoria/local de uso do produto
            $table->integer('ordem')->default(0);
            $table->timestamps();
            
            $table->index(['regra_id', 'tipo_acao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistente_regra_acoes');
        Schema::dropIfExists('assistente_regra_condicoes');
        Schema::dropIfExists('assistente_regras_condicionais');
    }
};
