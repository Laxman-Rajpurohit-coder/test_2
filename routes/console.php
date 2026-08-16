<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('verify:tasks {conv_id=1185}', function ($convId) {
    $this->info("=========================================");
    $this->info("1. SCHEMA TYPES IN POSTGRES");
    $tasksColType = \Illuminate\Support\Facades\Schema::getColumnType('customer_tasks', 'conversation_id');
    $convColType = \Illuminate\Support\Facades\Schema::getColumnType('conversations', 'id');
    $tasksContactType = \Illuminate\Support\Facades\Schema::getColumnType('customer_tasks', 'contact_id');

    $this->info("customer_tasks.conversation_id: {$tasksColType}");
    $this->info("conversations.id: {$convColType}");
    $this->info("customer_tasks.contact_id: {$tasksContactType}");

    $this->info("=========================================");
    $this->info("2. CONVERSATION CHECK: {$convId}");
    $conv = \App\Models\Conversation::withoutGlobalScopes()->find($convId);
    if ($conv) {
        $this->info("Conversation {$convId} found: tenant_id={$conv->tenant_id}, customer_number={$conv->customer_number}, name={$conv->customer_name}");
    } else {
        $this->warn("Conversation {$convId} not found. Taking first available conversation:");
        $conv = \App\Models\Conversation::withoutGlobalScopes()->first();
        if ($conv) {
            $convId = $conv->id;
            $this->info("Using fallback conversation ID: {$convId}");
        }
    }

    $this->info("=========================================");
    $this->info("3. CONTROLLER TEST: GET /api/conversations/{$convId}/tasks");
    try {
        $controller = app(\App\Http\Controllers\CustomerTaskController::class);
        $res = $controller->index($convId);
        $this->info("GET Status: " . $res->getStatusCode());
        $this->info("GET Body: " . json_encode($res->getData()));
    } catch (\Throwable $e) {
        $this->error("GET Error: " . $e->getMessage());
    }

    $this->info("=========================================");
    $this->info("4. CONTROLLER TEST: POST /tasks (creating test task)");
    try {
        $user = \App\Models\User::first();
        if ($user) {
            auth()->login($user);
        }
        $resolver = app(\App\Services\TenantResolverService::class);
        $request = \Illuminate\Http\Request::create('/tasks', 'POST', [
            'conversation_id' => $convId,
            'title' => 'Live test task ' . now()->format('H:i:s'),
            'description' => 'Verifying bigint FK and contact resolution',
            'type' => 'task',
            'status' => 'open',
        ]);
        $request->headers->set('Accept', 'application/json');
        $postRes = $controller->store($request, $resolver);
        if ($postRes instanceof \Illuminate\Http\JsonResponse) {
            $this->info("POST Status: " . $postRes->getStatusCode());
            $this->info("POST Body: " . json_encode($postRes->getData()));
        } else {
            $this->info("POST Result: " . get_class($postRes));
        }
    } catch (\Throwable $e) {
        $this->error("POST Error: " . $e->getMessage());
    }

    $this->info("=========================================");
    $this->info("5. FINAL ROW COUNT AND ROWS IN customer_tasks");
    $all = \Illuminate\Support\Facades\DB::table('customer_tasks')->get();
    $this->info("Total customer_tasks rows: " . $all->count());
    foreach ($all as $item) {
        $this->info(" - ID: {$item->id} | Title: {$item->title} | ConvID: {$item->conversation_id} | ContactID: " . ($item->contact_id ?? 'NULL'));
    }
});

use Illuminate\Support\Facades\Schedule;
Schedule::command('tasks:send-reminders')->everyMinute();
