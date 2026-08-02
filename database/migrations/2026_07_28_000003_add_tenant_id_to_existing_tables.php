<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['users', 'conversations', 'whatsapp_messages', 'bot_triggers'];

        // STEP 1: Add tenant_id as NULLABLE foreign key across existing tables
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->index();
                });
            }
        }

        // STEP 2: Data Backfill - Explicitly set tenant_id = 1 on ALL legacy pre-multi-tenant rows
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => 1]);
            }
        }

        // STEP 3: Alter tenant_id column to NOT NULL to guarantee zero query landmines
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['users', 'conversations', 'whatsapp_messages', 'bot_triggers'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
