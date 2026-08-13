<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quando o pull leu a ficha COMPLETA do contato no oList, e não só a linha da lista.
 *
 * A lista de contatos do oList devolve nome, e-mail, telefone fixo e endereço — mas não data de
 * nascimento, celular nem sexo. Para saber quando vale gastar a chamada extra de `contato.obter`,
 * o pull precisa lembrar de quando foi a última leitura completa de cada contato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->timestamp('tiny_detalhe_sync_at')->nullable()->after('tiny_sync_at');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn('tiny_detalhe_sync_at');
        });
    }
};
