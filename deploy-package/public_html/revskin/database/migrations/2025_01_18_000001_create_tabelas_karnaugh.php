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
        // Tabelas Karnaugh (múltiplas tabelas)
        Schema::create('tabelas_karnaugh', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('arquivo_original')->nullable(); // nome do arquivo CSV importado
            $table->boolean('ativo')->default(true);
            $table->boolean('padrao')->default(false); // tabela padrão a ser usada quando nenhuma regra aplicar
            $table->timestamps();
        });

        // Produtos de cada tabela Karnaugh
        Schema::create('tabela_karnaugh_produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tabela_karnaugh_id')->constrained('tabelas_karnaugh')->onDelete('cascade');
            $table->string('caso_clinico'); // código do caso clínico (ex: PSM1R1A1F1)
            $table->string('categoria'); // categoria/local de uso (ex: Limpeza, Diurno)
            $table->string('produto_codigo'); // código ou nome do produto
            $table->enum('grupo', ['primeiro', 'segundo'])->default('primeiro'); // primeiro = marcado, segundo = não marcado
            $table->boolean('marcar')->default(true); // se deve vir marcado na sugestão
            $table->integer('ordem')->default(0); // ordem de exibição
            $table->timestamps();
            
            // Índice para busca eficiente
            $table->index(['tabela_karnaugh_id', 'caso_clinico']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tabela_karnaugh_produtos');
        Schema::dropIfExists('tabelas_karnaugh');
    }
};
