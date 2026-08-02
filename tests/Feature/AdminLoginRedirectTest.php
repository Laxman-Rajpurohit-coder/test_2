<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_ignores_web_guard_intended_url()
    {
        $admin = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // 1. Simulate the admin (as a guest) trying to hit a web-protected route, which sets url.intended in the session
        $this->get('/dashboard')->assertRedirect('/login');

        // 2. Admin logs in via the admin login form
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        // 3. Admin MUST be redirected to the admin dashboard, NOT bounced to /dashboard or /login
        $response->assertRedirect('/admin/tenants');
        
        $this->assertAuthenticatedAs($admin, 'admin');
    }
    
    public function test_unauthenticated_admin_route_redirects_to_admin_login()
    {
        $response = $this->get('/admin/tenants');
        
        $response->assertRedirect('/admin/login');
    }
}
