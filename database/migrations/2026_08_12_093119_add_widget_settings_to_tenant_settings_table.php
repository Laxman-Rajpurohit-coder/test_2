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
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('widget_title')->default('Chat with us on WhatsApp')->after('public_api_key_last_four');
            $table->text('widget_welcome_msg')->nullable()->after('widget_title');
            $table->string('widget_color', 20)->default('#00a884')->after('widget_welcome_msg');
            $table->string('widget_position', 20)->default('bottom-right')->after('widget_color');
            $table->boolean('widget_auto_redirect_wa')->default(true)->after('widget_position');
            $table->string('widget_target_phone', 50)->nullable()->after('widget_auto_redirect_wa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'widget_title',
                'widget_welcome_msg',
                'widget_color',
                'widget_position',
                'widget_auto_redirect_wa',
                'widget_target_phone',
            ]);
        });
    }
};
