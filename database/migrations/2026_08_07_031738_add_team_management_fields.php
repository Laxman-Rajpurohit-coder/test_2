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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('owner')->after('tenant_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
        });

        Schema::dropIfExists('tenant_invites');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('tenant_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token')->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn('assigned_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
