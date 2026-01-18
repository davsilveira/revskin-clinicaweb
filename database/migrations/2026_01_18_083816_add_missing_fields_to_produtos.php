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
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('categoria')->nullable()->after('local_uso');
            $table->decimal('preco_custo', 10, 2)->nullable()->after('preco');
            $table->integer('estoque_minimo')->nullable()->after('preco_custo');
            $table->string('unidade', 20)->default('UN')->after('estoque_minimo');
            $table->string('tiny_id')->nullable()->after('ativo');
            $table->string('modo_uso')->nullable()->after('categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'preco_custo', 'estoque_minimo', 'unidade', 'tiny_id', 'modo_uso']);
        });
    }
};
