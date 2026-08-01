<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Bootstrap order (fresh DB only, run once):
     *   1. php artisan migrate
     *   2. php artisan admin:create-super   — creates AdminUser (platform operator login)
     *   3. php artisan db:seed              — creates demo Tenants + Users (tenant-side logins)
     *
     * This seeder is the ONLY path to create User rows. No UI path exists yet.
     * firstOrCreate means re-seeding an existing DB is safe — existing rows are not overwritten.
     * Passwords are randomised per-seed run and printed below — never a predictable literal.
     */
    public function run(): void
    {
        $tenant1 = Tenant::firstOrCreate(['id' => 1], ['name' => 'MTech Systems', 'slug' => 'mtech-systems']);
        $tenant2 = Tenant::firstOrCreate(['id' => 2], ['name' => 'Global Logistics', 'slug' => 'global-logistics']);

        $accounts = [
            ['email' => 'admin@example.com',        'name' => 'Master Admin',   'tenant_id' => $tenant1->id],
            ['email' => 'user_tenant1@example.com', 'name' => 'Tenant 1 Admin', 'tenant_id' => $tenant1->id],
            ['email' => 'user_tenant2@example.com', 'name' => 'Tenant 2 Admin', 'tenant_id' => $tenant2->id],
        ];

        foreach ($accounts as $account) {
            $plain = Str::random(16);
            $created = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'      => $account['name'],
                    'tenant_id' => $account['tenant_id'],
                    'password'  => Hash::make($plain),
                ]
            );

            // Only print if the row was just created (wasRecentlyCreated = true on firstOrCreate insert)
            if ($created->wasRecentlyCreated) {
                $this->command->info("Created {$account['email']} — password: {$plain}");
            } else {
                $this->command->line("Skipped {$account['email']} (already exists)");
            }
        }
    }
}
