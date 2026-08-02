<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class MiddlewareLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_dashboard_loads_without_500()
    {
        $tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active'
        ]);
        
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->tenant_id = $tenant->id;
        $user->save();
        
        $response = $this->actingAs($user)->get('/dashboard');
        
        $response->assertStatus(200);
        $this->assertStringContainsString('inertia', $response->content());
    }

    public function test_missing_impersonated_tenant_does_not_crash()
    {
        $tenant = \App\Models\Tenant::create([
            'name' => 'Valid Tenant',
            'slug' => 'valid-tenant',
            'status' => 'active'
        ]);
        
        $user = new User([
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->tenant_id = $tenant->id;
        $user->save();
        
        // Inject an impersonation session for a tenant ID that definitely does not exist
        $response = $this->actingAs($user)
                         ->withSession(['impersonating_tenant_id' => 999999])
                         ->get('/dashboard');
        
        $response->assertStatus(200);
        $this->assertStringContainsString('inertia', $response->content());
    }
}
