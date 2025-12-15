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
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->string('nome');
            $table->date('data_nascimento')->nullable();
            $table->string('sexo', 20)->nullable(); // Masculino, Feminino
            $table->string('fototipo', 50)->nullable(); // Tipo de pele
            $table->string('cpf', 14)->nullable();
            $table->string('rg', 20)->nullable();
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
            $table->string('indicado_por')->nullable();
            $table->text('anotacoes')->nullable();
            $table->foreignId('medico_id')->nullable()->constrained('medicos')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};




