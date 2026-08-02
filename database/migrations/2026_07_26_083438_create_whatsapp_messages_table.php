<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('request_id', 64)->nullable()->index();
            $table->string('meta_uuid', 255)->nullable()->index();
            $table->enum('direction', ['inbound', 'outbound'])->index();
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed', 'received'])->index();
            $table->jsonb('content')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('vendor_timestamp')->nullable();
            $table->timestamps();
            
            // Performance indexing as per architecture documentation
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
