<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the admin audit logs table.
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('action'); // e.g., 'impersonate_start', 'impersonate_stop', 'tenant_suspend'
            $table->string('target_type')->nullable(); // e.g., 'App\Models\Tenant'
            $table->unsignedBigInteger('target_id')->nullable(); // e.g., 4
            $table->jsonb('metadata')->nullable(); // Extra context, IP, user-agent, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
