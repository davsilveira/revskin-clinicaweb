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
        if (Schema::hasColumn('receitas', 'receita_origem_id')) {
            return;
        }
        Schema::table('receitas', function (Blueprint $table) {
            $table->foreignId('receita_origem_id')
                ->nullable()
                ->after('medico_id')
                ->constrained('receitas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->dropForeign(['receita_origem_id']);
        });
    }
};
