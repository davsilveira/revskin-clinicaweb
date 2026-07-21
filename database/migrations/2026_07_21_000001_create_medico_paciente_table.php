<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opção 2 (cadastro único de paciente): vínculo N:N médico↔paciente.
 *
 * Cada linha é o vínculo de UM médico com UM paciente e carrega os campos que são
 * PRIVADOS por médico (conforme anexo): Observações, Nº Registro e Indicado por.
 * Os dados principais do paciente continuam compartilhados na tabela `pacientes`.
 *
 * Fase A: cria a tabela vazia. As colunas antigas em `pacientes`
 * (anotacoes/codigo/indicado_por/medico_id) continuam existindo e são preenchidas no
 * pivot pelo comando `pacientes:backfill-vinculos`. O drop delas fica para a Fase B.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('medico_paciente')) {
            return;
        }
        Schema::create('medico_paciente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();

            // Campos PRIVADOS por médico (saem de `pacientes` na Fase B).
            $table->text('anotacoes')->nullable();       // Observações
            $table->string('codigo')->nullable();        // Nº Registro (único por médico)
            $table->string('indicado_por')->nullable();  // Indicado por

            // Arquivar por vínculo (decisão do cliente): um médico "some" com o paciente
            // sem afetar os outros. `pacientes.ativo` global continua sendo ação de admin.
            $table->boolean('ativo')->default(true);

            // Auditoria de como o vínculo nasceu: form | receita | assistente | callcenter | import
            $table->string('origem')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['medico_id', 'paciente_id']);
            // Nº Registro único POR médico (NULLs são distintos, então vários sem código são OK).
            $table->unique(['medico_id', 'codigo']);
            $table->index('paciente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medico_paciente');
    }
};
