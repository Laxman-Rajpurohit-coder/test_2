<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE campaign_recipients DROP CONSTRAINT IF EXISTS campaign_recipients_status_check');
            }
        } catch (\Throwable $e) {
            Log::error('Drop campaign_recipients_status_check failed, ignoring: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank — re-adding the restrictive constraint is not desired.
    }
};
