<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantInviteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_can_register_via_tenant_invite()
    {
        $tenant = Tenant::factory()->create();
        $token = Str::random(40);

        $invite = TenantInvite::create([
            'tenant_id' => $tenant->id,
            'email' => 'newuser@example.com',
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->post('/register', [
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'token' => $token,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'tenant_id' => $tenant->id,
        ]);

        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_existing_user_email_can_accept_invite_without_unique_constraint_error()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'tenant_id' => $tenant1->id,
            'password' => Hash::make('oldpassword'),
        ]);

        $token = Str::random(40);

        $invite = TenantInvite::create([
            'tenant_id' => $tenant2->id,
            'email' => 'existing@example.com',
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->post('/register', [
            'name' => 'Updated Existing User',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => $token,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existingUser->fresh());

        $this->assertEquals($tenant2->id, $existingUser->fresh()->tenant_id);
        $this->assertTrue(Hash::check('newpassword123', $existingUser->fresh()->password));
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_expired_invite_is_rejected()
    {
        $tenant = Tenant::factory()->create();
        $token = Str::random(40);

        TenantInvite::create([
            'tenant_id' => $tenant->id,
            'email' => 'expired@example.com',
            'token' => $token,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->post('/register', [
            'name' => 'Expired User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'token' => $token,
        ]);

        $response->assertStatus(403);
    }

    public function test_already_accepted_invite_is_rejected()
    {
        $tenant = Tenant::factory()->create();
        $token = Str::random(40);

        TenantInvite::create([
            'tenant_id' => $tenant->id,
            'email' => 'accepted@example.com',
            'token' => $token,
            'expires_at' => now()->addDays(7),
            'accepted_at' => now()->subHour(),
        ]);

        $response = $this->post('/register', [
            'name' => 'Accepted User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'token' => $token,
        ]);

        $response->assertStatus(403);
    }
}
