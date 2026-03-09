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
        // Add pais field to pacientes
        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('pais', 100)->default('Brasil')->after('uf');
        });

        // Create paciente_telefones table for multiple phones
        Schema::create('paciente_telefones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->string('numero', 30);
            $table->string('tipo', 50)->default('Celular'); // Residencial, Comercial, Celular, WhatsApp, Outro
            $table->boolean('principal')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paciente_telefones');

        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn('pais');
        });
    }
};
