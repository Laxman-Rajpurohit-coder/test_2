<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $targetTable = Schema::hasTable('messages') ? 'messages' : (Schema::hasTable('whatsapp_messages') ? 'whatsapp_messages' : null);
        if ($targetTable && !Schema::hasColumn($targetTable, 'channel')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->string('channel', 50)
                    ->default('whatsapp')
                    ->after('conversation_id')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        $targetTable = Schema::hasTable('messages') ? 'messages' : (Schema::hasTable('whatsapp_messages') ? 'whatsapp_messages' : null);
        if ($targetTable && Schema::hasColumn($targetTable, 'channel')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->dropIndex(['channel']);
                $table->dropColumn('channel');
            });
        }
    }
};
