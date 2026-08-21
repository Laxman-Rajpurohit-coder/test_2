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
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('unread_count');
            $table->index(['tenant_id', 'is_favorite', 'last_message_at'], 'conversations_tenant_fav_lastmsg_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_tenant_fav_lastmsg_idx');
            $table->dropColumn('is_favorite');
        });
    }
};
