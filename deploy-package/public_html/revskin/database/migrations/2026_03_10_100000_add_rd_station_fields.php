<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->string('rd_deal_id')->nullable()->after('tiny_pedido_id');
        });

        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('rd_organization_id')->nullable()->after('tiny_updated_at');
            $table->string('rd_contact_id')->nullable()->after('rd_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->dropColumn('rd_deal_id');
        });

        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn(['rd_organization_id', 'rd_contact_id']);
        });
    }
};
