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
        Schema::create('receita_item_aquisicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receita_item_id')->constrained('receita_itens')->onDelete('cascade');
            $table->date('data_aquisicao');
            $table->foreignId('atendimento_id')->nullable()->constrained('atendimentos_callcenter')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['receita_item_id', 'data_aquisicao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receita_item_aquisicoes');
    }
};
