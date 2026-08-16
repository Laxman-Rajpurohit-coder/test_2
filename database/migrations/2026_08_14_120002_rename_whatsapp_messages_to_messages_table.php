<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_messages') && !Schema::hasTable('messages')) {
            Schema::rename('whatsapp_messages', 'messages');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages') && !Schema::hasTable('whatsapp_messages')) {
            Schema::rename('messages', 'whatsapp_messages');
        }
    }
};
