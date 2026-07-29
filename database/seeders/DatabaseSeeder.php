<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant1 = Tenant::firstOrCreate(['id' => 1], ['name' => 'MTech Systems', 'slug' => 'mtech-systems']);
        $tenant2 = Tenant::firstOrCreate(['id' => 2], ['name' => 'Global Logistics', 'slug' => 'global-logistics']);

        // Master Admin User (Tenant 1)
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'      => 'Master Admin',
                'tenant_id' => $tenant1->id,
                'password'  => Hash::make('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'user_tenant1@example.com'],
            [
                'name'      => 'Tenant 1 Admin',
                'tenant_id' => $tenant1->id,
                'password'  => Hash::make('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'user_tenant2@example.com'],
            [
                'name'      => 'Tenant 2 Admin',
                'tenant_id' => $tenant2->id,
                'password'  => Hash::make('password'),
            ]
        );
    }
}
