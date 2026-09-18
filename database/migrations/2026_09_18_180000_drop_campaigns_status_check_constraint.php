<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("
                    DO \$\$ 
                    DECLARE r RECORD;
                    BEGIN
                        FOR r IN (
                            SELECT constraint_name 
                            FROM information_schema.table_constraints 
                            WHERE table_name = 'campaigns' 
                              AND constraint_type = 'CHECK'
                              AND constraint_name LIKE '%status%'
                        ) LOOP
                            EXECUTE 'ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS ' || quote_ident(r.constraint_name);
                        END LOOP;
                    END \$\$;
                ");
            }
        } catch (\Throwable $e) {
            Log::error('Drop campaigns status check constraint failed: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank
    }
};
