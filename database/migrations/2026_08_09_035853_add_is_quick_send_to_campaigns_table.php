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
                    $table->boolean('is_quick_send')->default(false)->after('status');
                });
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Migration is_quick_send failed: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('is_quick_send');
        });
    }
};
