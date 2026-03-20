<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Limpa dados de pacientes.
     */
    public function up(): void
    {
        DB::table('pacientes')->delete();
    }

    public function down(): void
    {
        //
    }
};
