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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('target_id');
            // Laravel 11 handles SQLite enum changes automatically by rebuilding the table
            $table->enum('status', ['draft', 'scheduled', 'queued', 'sending', 'completed', 'failed', 'cancelled'])
                  ->default('draft')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
            $table->enum('status', ['draft', 'queued', 'sending', 'completed', 'failed'])
                  ->default('draft')
                  ->change();
        });
    }
};
