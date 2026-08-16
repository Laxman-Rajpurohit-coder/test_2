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
            \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS tenant_invites_email_pending_unique ON tenant_invites(email) WHERE accepted_at IS NULL');
        } catch (\Throwable $e) {
            // In case sqlite or unsupported driver
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS tenant_invites_email_pending_unique');
        } catch (\Throwable $e) {
            // Ignore gracefully
        }
    }
};
