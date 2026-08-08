<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:role';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test role middleware';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $adminUser = \App\Models\AdminUser::find(2); // Assuming ID 2 is the admin
        
        $this->info("Simulating IMPERSONATION request as Admin: {$adminUser->email} with NO web session");

        $request = \Illuminate\Http\Request::create('/templates', 'GET');
        
        // Setup authentication for admin ONLY
        auth('admin')->login($adminUser);
        
        // Simulate impersonation session state
        session()->put('impersonating_tenant_id', 7);

        // Run through the app HTTP kernel
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);

        $this->info("Response Status: " . $response->getStatusCode());
        
        // Dump the latest logs to see what RoleMiddleware saw
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $lines = file($logPath);
            $lastLines = array_slice($lines, -10);
            foreach ($lastLines as $line) {
                if (str_contains($line, 'RoleMiddleware')) {
                    $this->info(trim($line));
                }
            }
        }
    }
}
