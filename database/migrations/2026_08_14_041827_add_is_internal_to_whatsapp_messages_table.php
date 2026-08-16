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
        $targetTable = Schema::hasTable('messages') ? 'messages' : (Schema::hasTable('whatsapp_messages') ? 'whatsapp_messages' : null);
        if ($targetTable && !Schema::hasColumn($targetTable, 'is_internal')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->boolean('is_internal')->default(false)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $targetTable = Schema::hasTable('messages') ? 'messages' : (Schema::hasTable('whatsapp_messages') ? 'whatsapp_messages' : null);
        if ($targetTable && Schema::hasColumn($targetTable, 'is_internal')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->dropColumn('is_internal');
            });
        }
    }
};
