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
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE bot_triggers DROP CONSTRAINT IF EXISTS bot_triggers_response_type_check');
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE bot_triggers ALTER COLUMN response_type TYPE varchar(255)");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE bot_triggers ADD CONSTRAINT bot_triggers_response_type_check CHECK (response_type IN ('text', 'image', 'document', 'flow', 'interactive'))");
        } else {
            Schema::table('bot_triggers', function (Blueprint $table) {
                $table->string('response_type')->default('text')->change();
            });
        }
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
