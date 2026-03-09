<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('receitas')
            ->where('status', 'rascunho')
            ->update(['status' => 'aberta']);

        Schema::table('receitas', function (Blueprint $table) {
            $table->string('status')->default('aberta')->change();
        });
    }

    public function down(): void
    {
        DB::table('receitas')
            ->where('status', 'aberta')
            ->update(['status' => 'rascunho']);

        Schema::table('receitas', function (Blueprint $table) {
            $table->string('status')->default('rascunho')->change();
        });
    }
};
