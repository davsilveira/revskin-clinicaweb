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
        Schema::create('atendimentos_callcenter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receita_id')->constrained('receitas')->onDelete('cascade');
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->foreignId('medico_id')->constrained('medicos')->onDelete('cascade');
            $table->string('status')->default('entrar_em_contato'); // entrar_em_contato, aguardando_retorno, em_producao, finalizado, cancelado
            $table->datetime('data_abertura');
            $table->datetime('data_alteracao')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('usuario_alteracao_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('acompanhamentos_callcenter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos_callcenter')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->text('descricao');
            $table->datetime('data_registro');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acompanhamentos_callcenter');
        Schema::dropIfExists('atendimentos_callcenter');
    }
};

