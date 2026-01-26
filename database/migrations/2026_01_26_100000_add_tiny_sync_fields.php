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
        Schema::table('pacientes', function (Blueprint $table) {
            $table->timestamp('tiny_sync_at')->nullable()->after('tiny_id');
            $table->timestamp('tiny_updated_at')->nullable()->after('tiny_sync_at');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->timestamp('tiny_sync_at')->nullable()->after('tiny_id');
        });

        Schema::table('atendimentos_callcenter', function (Blueprint $table) {
            $table->string('tiny_pedido_id')->nullable()->after('ativo');
            $table->timestamp('tiny_sync_at')->nullable()->after('tiny_pedido_id');
            $table->string('tiny_situacao')->nullable()->after('tiny_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn(['tiny_sync_at', 'tiny_updated_at']);
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('tiny_sync_at');
        });

        Schema::table('atendimentos_callcenter', function (Blueprint $table) {
            $table->dropColumn(['tiny_pedido_id', 'tiny_sync_at', 'tiny_situacao']);
        });
    }
};
