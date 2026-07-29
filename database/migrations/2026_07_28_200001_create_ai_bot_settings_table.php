<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bot_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('provider')->default('openai'); // 'openai' or 'flowise'
            $table->text('api_key')->nullable(); // Encrypted
            $table->string('model_or_chatflow_id')->default('gpt-4o-mini');
            $table->text('system_prompt')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('human_escalation_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_settings');
    }
};
