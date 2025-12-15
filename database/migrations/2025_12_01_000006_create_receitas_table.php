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
        Schema::create('receitas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable(); // Número da receita
            $table->date('data_receita');
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->foreignId('medico_id')->constrained('medicos')->onDelete('cascade');
            $table->text('anotacoes')->nullable();
            $table->text('anotacoes_paciente')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->decimal('desconto_valor', 10, 2)->default(0);
            $table->string('desconto_motivo')->nullable();
            $table->decimal('valor_caixa', 10, 2)->default(0);
            $table->decimal('valor_frete', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->string('status')->default('rascunho'); // rascunho, finalizada, cancelada
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('receita_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receita_id')->constrained('receitas')->onDelete('cascade');
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->string('local_uso')->nullable();
            $table->text('anotacoes')->nullable();
            $table->integer('quantidade')->default(1);
            $table->decimal('valor_unitario', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->date('data_aquisicao')->nullable();
            $table->boolean('imprimir')->default(true);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receita_itens');
        Schema::dropIfExists('receitas');
    }
};




