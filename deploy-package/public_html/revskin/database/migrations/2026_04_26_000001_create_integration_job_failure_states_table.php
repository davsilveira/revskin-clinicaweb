<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_job_failure_states', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 128)->unique();
            $table->string('last_failed_job_uuid', 64);
            $table->dateTime('next_retry_at')->nullable();
            $table->unsignedTinyInteger('fast_retries_left')->default(3);
            $table->unsignedTinyInteger('delayed_retry_left')->default(1);
            $table->boolean('exhausted')->default(false);
            $table->boolean('in_flight')->default(false);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_job_failure_states');
    }
};
