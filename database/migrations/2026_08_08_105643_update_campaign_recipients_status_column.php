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
        if (config('database.default') === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE campaign_recipients ALTER COLUMN status TYPE VARCHAR(255) USING status::varchar');
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE campaign_recipients ALTER COLUMN status SET DEFAULT 'pending'");
        } else {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                // Change enum to string to allow delivered, read, etc.
                $table->string('status')->default('pending')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            // Revert to enum (this might fail in SQLite, but we write it for completeness)
            // SQLite doesn't strictly enforce enums anyway.
        });
    }
};
