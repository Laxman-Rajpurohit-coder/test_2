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
            if (!Schema::hasColumn('contacts', 'is_subscribed')) {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->boolean('is_subscribed')->default(true)->after('tenant_id');
                });
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Migration is_subscribed failed: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('is_subscribed');
        });
    }
};
