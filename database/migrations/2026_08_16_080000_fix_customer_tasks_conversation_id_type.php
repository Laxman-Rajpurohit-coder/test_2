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
        if (!Schema::hasTable('customer_tasks')) {
            return;
        }

        // 1️⃣ Drop foreign key constraint (fallback if doctrine/dbal not installed)
        Schema::table('customer_tasks', function (Blueprint $table) {
            try {
                $table->dropForeign(['conversation_id']);
            } catch (\Exception $e) {
                // If the foreign key does not exist or DBAL not present, ignore
            }
        });

        // 2️⃣ Drop the existing column (uuid)
        Schema::table('customer_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('customer_tasks', 'conversation_id')) {
                $table->dropColumn('conversation_id');
            }
        });

        // 3️⃣ Re‑add the column as a foreignId (bigint) referencing conversations.id
        Schema::table('customer_tasks', function (Blueprint $table) {
            $table->foreignId('conversation_id')
                  ->nullable()
                  ->after('contact_id')
                  ->constrained('conversations')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('customer_tasks')) {
            return;
        }

        // Drop the new foreign key and column
        Schema::table('customer_tasks', function (Blueprint $table) {
            try {
                $table->dropConstrainedForeignId('conversation_id');
            } catch (\Exception $e) {
                // ignore if not present
            }
        });

        // Re‑create the original uuid column
        Schema::table('customer_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_tasks', 'conversation_id')) {
                $table->uuid('conversation_id')->nullable();
            }
        });
    }
};
