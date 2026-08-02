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
        Schema::create('bot_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->enum('match_type', ['exact', 'contains', 'starts_with'])->default('contains');
            $table->enum('response_type', ['text', 'image', 'document', 'flow'])->default('text');
            $table->jsonb('response_payload');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            // Indexes for fast matching and priority ordering
            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_triggers');
    }
};
