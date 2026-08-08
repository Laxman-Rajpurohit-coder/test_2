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
        Schema::table('bot_triggers', function (Blueprint $table) {
            // First we drop the Postgres constraint if we are on pgsql, because Laravel's change() 
            // sometimes struggles with existing check constraints on ENUMs in Postgres.
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE bot_triggers DROP CONSTRAINT IF EXISTS bot_triggers_response_type_check');
            }
        });

        Schema::table('bot_triggers', function (Blueprint $table) {
            $table->enum('response_type', ['text', 'image', 'document', 'flow', 'interactive'])->default('text')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_triggers', function (Blueprint $table) {
            //
        });
    }
};
