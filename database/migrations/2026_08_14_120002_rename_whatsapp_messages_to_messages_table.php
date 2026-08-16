<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('whatsapp_messages', 'messages');
    }

    public function down(): void
    {
        Schema::rename('messages', 'whatsapp_messages');
    }
};
