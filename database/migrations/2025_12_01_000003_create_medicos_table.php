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
        Schema::create('medicos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('apelido')->nullable();
            $table->string('crm', 20)->nullable();
            $table->string('cpf', 14)->nullable();
            $table->string('rg', 20)->nullable();
            $table->string('especialidade')->nullable();
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
            $table->foreignId('tabela_preco_id')->nullable()->constrained('tabelas_preco')->nullOnDelete();
            $table->string('telefone1', 20)->nullable();
            $table->string('telefone2', 20)->nullable();
            $table->string('telefone3', 20)->nullable();
            $table->string('email1')->nullable();
            $table->string('email2')->nullable();
            $table->string('tipo_endereco')->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->text('rodape_receita')->nullable();
            $table->string('assinatura_path')->nullable(); // Path to signature image
            $table->text('anotacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Pivot table for user-medico relationship (a user can be linked to a medico)
        Schema::create('user_medico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('medico_id')->constrained('medicos')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'medico_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_medico');
        Schema::dropIfExists('medicos');
    }
};

