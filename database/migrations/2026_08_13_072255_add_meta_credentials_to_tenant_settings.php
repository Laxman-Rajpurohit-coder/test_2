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
            $table->string('meta_phone_number_id')->nullable()->after('public_api_key_last_four');
            $table->text('meta_access_token')->nullable()->after('meta_phone_number_id');
            $table->string('meta_waba_id')->nullable()->after('meta_access_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['meta_phone_number_id', 'meta_access_token', 'meta_waba_id']);
        });
    }
};
