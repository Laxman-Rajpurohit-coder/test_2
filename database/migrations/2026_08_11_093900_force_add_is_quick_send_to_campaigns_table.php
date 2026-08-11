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
        try {
            if (!Schema::hasColumn('campaigns', 'is_quick_send')) {
                Schema::table('campaigns', function (Blueprint $table) {
                    $table->boolean('is_quick_send')->default(false);
                });
            }
            if (!Schema::hasColumn('contacts', 'is_subscribed')) {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->boolean('is_subscribed')->default(true);
                });
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Force migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 
    }
};
