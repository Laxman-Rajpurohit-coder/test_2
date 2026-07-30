<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-super {--name=} {--email=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new super admin account';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->option('name') ?? $this->ask('Name');
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        if (empty($password) || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return Command::FAILURE;
        }

        if (\App\Models\AdminUser::where('email', $email)->exists()) {
            $this->error("Admin with email {$email} already exists.");
            return Command::FAILURE;
        }

        \App\Models\AdminUser::create([
            'name' => $name,
            'email' => $email,
            'password' => $password, // Auto-hashed by Model cast
        ]);

        $this->info("Super admin {$email} created successfully.");
        return Command::SUCCESS;
    }
}
