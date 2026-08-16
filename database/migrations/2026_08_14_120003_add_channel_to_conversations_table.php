<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                if (!Schema::hasColumn('conversations', 'channel')) {
                    $table->string('channel', 50)->default('whatsapp')->after('tenant_number_id')->index();
                }
                if (!Schema::hasColumn('conversations', 'channel_psid')) {
                    $table->string('channel_psid', 255)->nullable()->after('channel')->index();
                }
                if (!Schema::hasColumn('conversations', 'last_customer_message_at')) {
                    $table->timestamp('last_customer_message_at')->nullable()->after('last_message_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                if (Schema::hasColumn('conversations', 'channel')) {
                    $table->dropIndex(['channel']);
                    $table->dropColumn('channel');
                }
                if (Schema::hasColumn('conversations', 'channel_psid')) {
                    $table->dropIndex(['channel_psid']);
                    $table->dropColumn('channel_psid');
                }
                if (Schema::hasColumn('conversations', 'last_customer_message_at')) {
                    $table->dropColumn('last_customer_message_at');
                }
            });
        }
    }
};
