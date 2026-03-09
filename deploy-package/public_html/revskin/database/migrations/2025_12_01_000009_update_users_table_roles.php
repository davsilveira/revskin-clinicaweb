<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update users table to support new roles: admin, medico, callcenter
     */
    public function up(): void
    {
        // The role column already exists, we just document the new values:
        // - admin: Full access
        // - medico: Doctor access (prescriptions, patients)
        // - callcenter: Call center access (queue, follow-ups)
        
        // Add medico_id to users for direct doctor linking
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('medico_id')->nullable()->after('role')->constrained('medicos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['medico_id']);
            $table->dropColumn('medico_id');
        });
    }
};










