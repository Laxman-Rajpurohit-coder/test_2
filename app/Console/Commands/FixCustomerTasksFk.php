<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\CustomerTask;
use App\Models\Conversation;
use App\Models\Contact;
use App\Http\Controllers\CustomerTaskController;

class FixCustomerTasksFk extends Command
{
    protected $signature = 'fix:customer-tasks-fk';
    protected $description = 'Fix and verify customer_tasks schema and operations';

    public function handle()
    {
        $this->info("=========================================");
        $this->info("1. SCHEMA VERIFICATION");
        $tasksColType = Schema::getColumnType('customer_tasks', 'conversation_id');
        $convColType = Schema::getColumnType('conversations', 'id');
        $tasksContactType = Schema::getColumnType('customer_tasks', 'contact_id');

        $this->info("customer_tasks.conversation_id type: {$tasksColType}");
        $this->info("conversations.id type: {$convColType}");
        $this->info("customer_tasks.contact_id type: {$tasksContactType}");

        $this->info("=========================================");
        $this->info("2. CONVERSATION 1185 CHECK");
        $conv = Conversation::withoutGlobalScopes()->find(1185);
        if ($conv) {
            $this->info("Conversation 1185 exists: tenant_id={$conv->tenant_id}, customer_number={$conv->customer_number}, name={$conv->customer_name}");
        } else {
            $this->warn("Conversation 1185 does not exist. Listing first 3 conversations:");
            foreach (Conversation::withoutGlobalScopes()->take(3)->get() as $c) {
                $this->info(" - Conversation ID: {$c->id}, customer_number={$c->customer_number}");
            }
        }

        $this->info("=========================================");
        $this->info("3. CONTROLLER GET /api/conversations/1185/tasks TEST");
        try {
            $controller = app(CustomerTaskController::class);
            $response = $controller->index(1185);
            $this->info("GET /api/conversations/1185/tasks HTTP Status: " . $response->getStatusCode());
            $this->info("GET Body: " . json_encode($response->getData()));
        } catch (\Throwable $e) {
            $this->error("GET Error: " . $e->getMessage());
        }

        $this->info("=========================================");
        $this->info("4. CURRENT customer_tasks ROWS");
        $rows = DB::table('customer_tasks')->get();
        $this->info("Total rows count: " . $rows->count());
        foreach ($rows as $r) {
            $this->info(" - ID: {$r->id} | Title: {$r->title} | ConvID: {$r->conversation_id} | ContactID: " . ($r->contact_id ?? 'NULL') . " | Status: {$r->status}");
        }

        return Command::SUCCESS;
    }
}
