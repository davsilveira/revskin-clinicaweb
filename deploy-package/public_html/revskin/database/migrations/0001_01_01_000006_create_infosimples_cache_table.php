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
        Schema::create('infosimples_cache', function (Blueprint $table) {
            $table->id();
            $table->string('service', 190);
            $table->string('key_value', 190);
            $table->json('response');
            $table->integer('code');
            $table->timestamps();

            $table->unique(['service', 'key_value'], 'infosimples_cache_service_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infosimples_cache');
    }
};

