<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_export_requests', function (Blueprint $table) {
            $table->json('extra_emails')->nullable()->after('search');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_export_requests', function (Blueprint $table) {
            $table->dropColumn('extra_emails');
        });
    }
};
