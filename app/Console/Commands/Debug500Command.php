<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\TenantResolverService;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;

class Debug500Command extends Command
{
    protected $signature = 'debug:500';
    protected $description = 'Debug 500 error on api/conversations';

    public function handle()
    {
        $user = User::withoutGlobalScopes()->where('tenant_id', 7)->first();
        auth()->login($user);
        app(TenantResolverService::class)->setActiveTenantId(7);

        try {
            $request = Request::create('/api/conversations', 'GET', [
                'channel' => 'whatsapp',
                'limit' => 40
            ]);
            
            $controller = app(ChatController::class);
            $response = $controller->index($request);
            
            $this->info("HTTP CODE: " . $response->getStatusCode());
            $this->info("CONTENT: " . substr($response->getContent(), 0, 500));
        } catch (\Throwable $e) {
            $this->error("EXACT EXCEPTION: " . get_class($e) . ": " . $e->getMessage());
            $this->error("FILE: " . $e->getFile() . ":" . $e->getLine());
            $this->error("TRACE:\n" . $e->getTraceAsString());
        }
    }
}
