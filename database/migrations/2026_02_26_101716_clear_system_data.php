<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Limpa dados de receitas, atendimentos call center, pacientes, médicos e regras condicionais.
     * Ordem respeita chaves estrangeiras.
     */
    public function up(): void
    {
        // 1. Receitas e atendimentos (referenciam pacientes)
        DB::table('receita_item_aquisicoes')->delete();
        DB::table('acompanhamentos_callcenter')->delete();
        DB::table('atendimentos_callcenter')->delete();
        DB::table('receita_itens')->delete();
        DB::table('receitas')->delete();

        // 2. Pacientes
        DB::table('pacientes')->delete();

        // 3. Médicos - limpar FKs que apontam para medicos
        DB::table('users')->update(['medico_id' => null]);
        DB::table('user_medico')->delete();
        DB::table('clinica_medico')->delete();
        DB::table('medico_enderecos')->delete();
        DB::table('medicos')->delete();

        // 4. Regras condicionais
        DB::table('assistente_regra_condicoes')->delete();
        DB::table('assistente_regra_acoes')->delete();
        DB::table('assistente_regras_condicionais')->delete();
    }

    /**
     * Migration irreversível - não recria dados.
     */
    public function down(): void
    {
        // Intencionalmente vazio
    }
};
