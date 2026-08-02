<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStatsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_stats_bypasses_session_and_isolates_by_tenant_parameter()
    {
        // 1. Create two separate tenants
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // 2. Insert messages for Tenant A
        $convA = Conversation::create([
            'tenant_id' => $tenantA->id,
            'customer_number' => '1234567890',
        ]);
        
        WhatsappMessage::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => $tenantA->id,
            'conversation_id' => $convA->id,
            'direction' => 'inbound',
            'status' => 'received',
            'content' => json_encode(['type' => 'text', 'text' => 'Hello Tenant A']),
        ]);

        WhatsappMessage::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => $tenantA->id,
            'conversation_id' => $convA->id,
            'direction' => 'inbound',
            'status' => 'received',
            'content' => json_encode(['type' => 'text', 'text' => 'Another message for A']),
        ]);

        // 3. Create an admin user
        $admin = AdminUser::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        // 4. Force the active tenant in session to be Tenant A (to simulate active impersonation or session bleed)
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenantA->id);

        // 5. As the admin, hit the stats endpoint for Tenant B
        $response = $this->actingAs($admin, 'admin')
                         ->get("/admin/tenants/{$tenantB->id}/stats");

        $response->assertStatus(200);

        // Assert that we don't see any of Tenant A's data, despite Tenant A being active in session
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tenants/Stats')
            ->where('metrics.totals.inbound_messages', 0) // Should be 0, not 2
            ->where('metrics.totals.total_messages', 0)
        );
        
        echo "\n[Test] Admin viewing Tenant B stats correctly saw 0 messages, ignoring Tenant A's session bleed.\n";
    }

    public function test_regular_tenant_user_is_forbidden_from_admin_stats()
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // Attempt to hit the admin endpoint as a regular user
        $response = $this->actingAs($user, 'web') // explicitly use web guard
                         ->get("/admin/tenants/{$tenant->id}/stats");

        // The admin routes are protected by 'admin' middleware which checks auth()->guard('admin')->check()
        // If false, it returns a redirect()->route('admin.login').
        // We assert a strict 302 redirect to the login route.
        $response->assertRedirect(route('admin.login'));
        
        echo "[Test] Regular user correctly prevented from accessing Admin stats (Redirected to admin.login).\n";
    }
}
