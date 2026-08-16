<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            try {
                Schema::table('conversations', function (Blueprint $table) {
                    $table->string('customer_number', 255)->change();
                });
            } catch (\Throwable $e) {
                // If change() is not supported on some driver setups without dbal, ignore gracefully
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations')) {
            try {
                Schema::table('conversations', function (Blueprint $table) {
                    $table->string('customer_number', 20)->change();
                });
            } catch (\Throwable $e) {
                // Ignore gracefully
            }
        }
    }
};
