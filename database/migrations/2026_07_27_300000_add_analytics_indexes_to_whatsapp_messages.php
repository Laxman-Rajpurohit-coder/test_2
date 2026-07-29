<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(['created_at', 'direction'], 'idx_messages_created_direction');
            $table->index(['created_at', 'status'], 'idx_messages_created_status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_created_direction');
            $table->dropIndex('idx_messages_created_status');
        });
    }
};
