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
            $table->string('ai_provider')->nullable()->default('openai');
            $table->string('ai_model')->nullable();
            $table->text('ai_system_prompt')->nullable();
            $table->boolean('ai_is_active')->default(false);
            $table->boolean('ai_human_escalation_enabled')->default(true);
            $table->decimal('ai_confidence_threshold', 3, 2)->default(0.70);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider',
                'ai_model',
                'ai_system_prompt',
                'ai_is_active',
                'ai_human_escalation_enabled',
                'ai_confidence_threshold'
            ]);
        });
    }
};
