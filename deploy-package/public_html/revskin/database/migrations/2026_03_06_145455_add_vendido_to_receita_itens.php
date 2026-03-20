<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receita_itens', function (Blueprint $table) {
            $table->boolean('vendido')->default(false)->after('data_aquisicao');
        });
    }

    public function down(): void
    {
        Schema::table('receita_itens', function (Blueprint $table) {
            $table->dropColumn('vendido');
        });
    }
};
