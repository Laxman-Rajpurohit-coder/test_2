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

        // Drop column and any associated constraints in Postgres/SQLite safely
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE customer_tasks DROP COLUMN IF EXISTS conversation_id CASCADE');

        // Re-add as foreignId referencing conversations(id)
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
