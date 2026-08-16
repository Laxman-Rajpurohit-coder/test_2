<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class FixCustomerTasksFk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:customer-tasks-fk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix customer_tasks conversation_id foreign key column type to bigint';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting customer_tasks fix...');

        // 1. Drop existing column safely in Postgres
        DB::statement('ALTER TABLE customer_tasks DROP COLUMN IF EXISTS conversation_id CASCADE');
        $this->info('Dropped existing conversation_id column if existed.');

        // 2. Add foreignId referencing conversations(id)
        Schema::table('customer_tasks', function (Blueprint $table) {
            $table->foreignId('conversation_id')
                  ->nullable()
                  ->after('contact_id')
                  ->constrained('conversations')
                  ->nullOnDelete();
        });
        $this->info('Added foreignId conversation_id constrained to conversations table.');

        // 3. Output verification types
        $tasksColType = Schema::getColumnType('customer_tasks', 'conversation_id');
        $convColType = Schema::getColumnType('conversations', 'id');

        $this->info("VERIFIED_TASKS_CONVERSATION_ID_TYPE: {$tasksColType}");
        $this->info("VERIFIED_CONVERSATIONS_ID_TYPE: {$convColType}");

        // 4. Test a dummy select query to ensure no syntax/type error
        $testCount = DB::table('customer_tasks')->where('conversation_id', 1185)->count();
        $this->info("TEST_QUERY_COUNT_CONVERSATION_1185: {$testCount}");

        return Command::SUCCESS;
    }
}
