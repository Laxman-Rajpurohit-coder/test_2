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
        if (Schema::hasTable('customer_tasks')) {
            Schema::table('customer_tasks', function (Blueprint $table) {
                // In Postgres/initial migration, conversation_id was created as uuid instead of foreignId (bigint)
                // Drop and recreate as foreignId referencing conversations(id)
                if (Schema::hasColumn('customer_tasks', 'conversation_id')) {
                    $table->dropColumn('conversation_id');
                }
            });

            Schema::table('customer_tasks', function (Blueprint $table) {
                $table->foreignId('conversation_id')->nullable()->after('contact_id')->constrained('conversations')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customer_tasks')) {
            Schema::table('customer_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('customer_tasks', 'conversation_id')) {
                    $table->dropConstrainedForeignId('conversation_id');
                }
                $table->uuid('conversation_id')->nullable();
            });
        }
    }
};
