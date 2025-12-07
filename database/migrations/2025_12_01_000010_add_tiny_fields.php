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
            $table->string('tiny_id')->nullable()->after('ativo');
        });

        Schema::table('receitas', function (Blueprint $table) {
            $table->string('tiny_pedido_id')->nullable()->after('ativo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn('tiny_id');
        });

        Schema::table('receitas', function (Blueprint $table) {
            $table->dropColumn('tiny_pedido_id');
        });
    }
};

