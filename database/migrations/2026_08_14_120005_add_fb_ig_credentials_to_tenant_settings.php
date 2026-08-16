<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('facebook_page_id')->nullable()->after('meta_waba_id');
            $table->string('instagram_account_id')->nullable()->after('facebook_page_id');

            // Stored encrypted via hand-written accessors on TenantSetting model.
            // Used to verify X-Hub-Signature-256 on incoming Meta webhooks.
            $table->text('meta_app_secret')->nullable()->after('instagram_account_id');

            // Stored encrypted. Compared against hub.verify_token during Meta webhook registration.
            $table->string('meta_webhook_verify_token')->nullable()->after('meta_app_secret');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_page_id',
                'instagram_account_id',
                'meta_app_secret',
                'meta_webhook_verify_token',
            ]);
        });
    }
};
