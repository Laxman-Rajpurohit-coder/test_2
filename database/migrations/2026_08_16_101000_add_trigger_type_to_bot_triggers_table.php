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
        Schema::table('bot_triggers', function (Blueprint $table) {
            $table->string('trigger_type')->default('keyword')->after('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_triggers', function (Blueprint $table) {
            $table->dropColumn('trigger_type');
        });
    }
};
