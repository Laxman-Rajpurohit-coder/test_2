<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize any NULL or empty channel conversations to 'whatsapp'
        DB::table('conversations')
            ->whereNull('channel')
            ->orWhere('channel', '')
            ->update(['channel' => 'whatsapp']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
