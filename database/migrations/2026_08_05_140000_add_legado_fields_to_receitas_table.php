<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->unsignedBigInteger('legado_id')->nullable()->after('id');
            $table->string('numero_origem')->nullable()->after('numero');
            $table->string('origem', 32)->nullable()->after('numero_origem');

            $table->unique('legado_id');
            $table->index('origem');
        });
    }

    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->dropUnique(['legado_id']);
            $table->dropIndex(['origem']);
            $table->dropColumn(['legado_id', 'numero_origem', 'origem']);
        });
    }
};
