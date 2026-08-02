<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a default tenant for testing
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
        ]);
    }

    public function test_registration_assigns_tenant_and_marks_invite_accepted()
    {
        $invite = TenantInvite::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'token' => TenantInvite::generateToken(),
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->post('/register', [
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'token' => $invite->token,
        ]);

        $response->assertRedirect('/dashboard');

        // Verify User was created correctly
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->tenant->id, $user->tenant_id);

        // Verify Invite was marked as accepted
        $invite->refresh();
        $this->assertNotNull($invite->accepted_at);
    }

    public function test_cannot_register_with_already_accepted_invite()
    {
        $invite = TenantInvite::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'token' => TenantInvite::generateToken(),
            'accepted_at' => now(), // Already accepted
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->post('/register', [
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'token' => $invite->token,
        ]);

        $response->assertForbidden(); // 403
    }

    public function test_cannot_register_with_expired_invite()
    {
        $invite = TenantInvite::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'token' => TenantInvite::generateToken(),
            'expires_at' => now()->subDays(1) // Expired yesterday
        ]);

        $response = $this->post('/register', [
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'token' => $invite->token,
        ]);

        $response->assertForbidden(); // 403
    }
}
