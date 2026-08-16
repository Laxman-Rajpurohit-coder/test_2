<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerTaskController;
use App\Services\TenantResolverService;

class TestTasksApi extends Command
{
    protected $signature = 'test:tasks-api {conversation_id=1185}';
    protected $description = 'Test tasks GET and POST endpoints directly with controllers';

    public function handle(TenantResolverService $resolver)
    {
        $convId = (int)$this->argument('conversation_id');
        $this->info("Testing with Conversation ID: {$convId}");

        $conversation = Conversation::find($convId);
        if (!$conversation) {
            $this->warn("Conversation {$convId} not found in DB. Fetching first available conversation...");
            $conversation = Conversation::first();
            if ($conversation) {
                $convId = $conversation->id;
                $this->info("Using fallback Conversation ID: {$convId}");
            }
        }

        $controller = app(CustomerTaskController::class);

        // 1. Test GET /api/conversations/{id}/tasks
        $this->info("--- Testing GET /api/conversations/{$convId}/tasks ---");
        try {
            $getResponse = $controller->index($convId);
            $this->info("GET Status: " . $getResponse->getStatusCode());
            $this->info("GET Body: " . json_encode($getResponse->getData()));
        } catch (\Exception $e) {
            $this->error("GET Failed: " . $e->getMessage());
        }

        // 2. Test POST /tasks
        $this->info("--- Testing POST /tasks ---");
        try {
            $user = User::first();
            if ($user) {
                auth()->login($user);
            }

            $request = Request::create('/tasks', 'POST', [
                'conversation_id' => $convId,
                'title' => 'Verification test task ' . now()->toIso8601String(),
                'description' => 'Automated test to verify bigint FK',
                'type' => 'task',
                'status' => 'open',
            ]);
            $request->headers->set('Accept', 'application/json');

            $postResponse = $controller->store($request, $resolver);
            if ($postResponse instanceof \Illuminate\Http\JsonResponse) {
                $this->info("POST Status: " . $postResponse->getStatusCode());
                $this->info("POST Body: " . json_encode($postResponse->getData()));
            } else {
                $this->info("POST Response (Redirect/View): " . get_class($postResponse));
            }
        } catch (\Exception $e) {
            $this->error("POST Failed: " . $e->getMessage());
        }

        // 3. Re-test GET to verify created task is returned
        $this->info("--- Testing GET again to confirm task persistence ---");
        try {
            $getResponse2 = $controller->index($convId);
            $this->info("GET Status: " . $getResponse2->getStatusCode());
            $this->info("GET Body: " . json_encode($getResponse2->getData()));
        } catch (\Exception $e) {
            $this->error("GET Failed: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
