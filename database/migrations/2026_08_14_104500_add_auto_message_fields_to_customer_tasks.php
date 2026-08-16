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
        Schema::table('customer_tasks', function (Blueprint $table) {
            $table->string('type')->default('task')->after('status'); // task, auto_message
            $table->string('template_name')->nullable()->after('type');
            $table->string('template_language')->nullable()->after('template_name');
            $table->json('template_components')->nullable()->after('template_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_tasks', function (Blueprint $table) {
            $table->dropColumn(['type', 'template_name', 'template_language', 'template_components']);
        });
    }
};
