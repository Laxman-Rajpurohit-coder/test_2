<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_settings')) {
            Schema::table('tenant_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('tenant_settings', 'facebook_page_id')) {
                    $table->string('facebook_page_id')->nullable()->after('meta_waba_id');
                }
                if (!Schema::hasColumn('tenant_settings', 'instagram_account_id')) {
                    $table->string('instagram_account_id')->nullable()->after('facebook_page_id');
                }
                if (!Schema::hasColumn('tenant_settings', 'meta_app_secret')) {
                    $table->text('meta_app_secret')->nullable()->after('instagram_account_id');
                }
                if (!Schema::hasColumn('tenant_settings', 'meta_webhook_verify_token')) {
                    $table->string('meta_webhook_verify_token')->nullable()->after('meta_app_secret');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_settings')) {
            Schema::table('tenant_settings', function (Blueprint $table) {
                $columns = ['facebook_page_id', 'instagram_account_id', 'meta_app_secret', 'meta_webhook_verify_token'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('tenant_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
