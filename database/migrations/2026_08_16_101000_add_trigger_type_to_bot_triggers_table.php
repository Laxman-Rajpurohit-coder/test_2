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
        if (Schema::hasTable('bot_triggers') && !Schema::hasColumn('bot_triggers', 'trigger_type')) {
            Schema::table('bot_triggers', function (Blueprint $table) {
                $table->string('trigger_type')->default('keyword')->after('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bot_triggers') && Schema::hasColumn('bot_triggers', 'trigger_type')) {
            Schema::table('bot_triggers', function (Blueprint $table) {
                $table->dropColumn('trigger_type');
            });
        }
    }
};
