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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('message_type', ['text', 'template']);
            $table->string('template_name')->nullable();
            $table->string('template_language')->nullable();
            $table->text('text_content')->nullable();
            $table->enum('status', ['draft', 'queued', 'sending', 'completed', 'failed'])->default('draft');
            $table->enum('target_type', ['all', 'group', 'tag']);
            $table->uuid('target_id')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
