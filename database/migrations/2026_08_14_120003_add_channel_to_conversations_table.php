<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->enum('channel', ['whatsapp', 'facebook', 'instagram'])
                ->default('whatsapp')
                ->after('tenant_number_id')
                ->index();

            // Page-Scoped User ID for Facebook/Instagram senders (null for WhatsApp)
            $table->string('channel_psid')->nullable()->after('channel')->index();

            // Updated when customer sends an inbound message — used for display only.
            // The 24-hour enforcement gate uses Cache, not this column.
            $table->timestamp('last_customer_message_at')->nullable()->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropIndex(['channel_psid']);
            $table->dropColumn(['channel', 'channel_psid', 'last_customer_message_at']);
        });
    }
};
