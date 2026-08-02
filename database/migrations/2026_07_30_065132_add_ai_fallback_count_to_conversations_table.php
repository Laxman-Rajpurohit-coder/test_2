<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the AI fallback count column to the conversations table.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->integer('ai_fallback_count')->default(0)->after('is_human_escalated');
        });
    }

    /**
     * Removes the AI fallback count column from the conversations table.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('ai_fallback_count');
        });
    }
};
