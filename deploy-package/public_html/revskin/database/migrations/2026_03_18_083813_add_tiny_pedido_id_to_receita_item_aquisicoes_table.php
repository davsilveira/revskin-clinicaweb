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
        Schema::table('receita_item_aquisicoes', function (Blueprint $table) {
            $table->string('tiny_pedido_id')->nullable()->after('receita_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receita_item_aquisicoes', function (Blueprint $table) {
            $table->dropColumn('tiny_pedido_id');
        });
    }
};
