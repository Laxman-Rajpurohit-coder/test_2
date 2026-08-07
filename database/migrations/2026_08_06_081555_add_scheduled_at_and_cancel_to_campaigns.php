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
            if (!Schema::hasColumn('campaigns', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('target_id');
            }
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_status_check");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_status_check CHECK (status IN ('draft', 'scheduled', 'queued', 'sending', 'completed', 'failed', 'cancelled'))");
        } else {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->string('status')->default('draft')->change();
            });
        }
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
