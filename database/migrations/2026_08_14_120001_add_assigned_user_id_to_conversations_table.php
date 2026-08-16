<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations') && !Schema::hasColumn('conversations', 'assigned_user_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreignId('assigned_user_id')
                    ->nullable()
                    ->after('is_human_escalated')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'assigned_user_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropForeign(['assigned_user_id']);
                $table->dropColumn('assigned_user_id');
            });
        }
    }
};
