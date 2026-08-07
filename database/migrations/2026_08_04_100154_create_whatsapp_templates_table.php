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
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('language')->default('en');
            $table->enum('category', ['MARKETING', 'UTILITY', 'AUTHENTICATION']);
            $table->enum('status', ['approved', 'pending', 'rejected'])->default('pending');
            $table->json('components');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'name', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
