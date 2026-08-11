<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AdminUser;
use App\Models\Tenant;
use App\Models\TenantSetting;

class TenantCredentialIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $tenantUser;
    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
        ]);

        $this->tenantUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
        ]);

        $this->adminUser = AdminUser::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        TenantSetting::create([
            'tenant_id' => $this->tenant->id,
            'msg91_auth_key' => 'secret_msg91_key',
            'ai_system_prompt' => 'You are a helpful assistant.',
        ]);
    }

    public function test_tenant_settings_response_excludes_api_credentials()
    {
        $response = $this->actingAs($this->tenantUser)->get('/settings/tenant');

        $response->assertStatus(200);

        // Assert Inertia props do not contain msg91_auth_key
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Tenant')
            ->has('settings.ai_system_prompt')
            ->missing('settings.msg91_auth_key')
            ->missing('settings.openai_api_key')
        );
    }

    public function test_tenant_settings_update_ignores_tampered_credentials()
    {
        $payload = [
            'ai_system_prompt' => 'Updated prompt',
            'msg91_auth_key' => 'hacked_key', // Tampered credential
        ];

        $response = $this->actingAs($this->tenantUser)->post('/settings/tenant', $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $setting = TenantSetting::where('tenant_id', $this->tenant->id)->first();
        
        $this->assertEquals('Updated prompt', $setting->ai_system_prompt);
        $this->assertEquals('secret_msg91_key', $setting->msg91_auth_key); // Must not change
    }

    public function test_admin_credentials_response_excludes_tenant_fields()
    {
        $response = $this->actingAs($this->adminUser, 'admin')->get("/admin/tenants/{$this->tenant->id}/credentials");

        $response->assertStatus(200);

        // Assert JSON response does not contain ai_system_prompt
        $response->assertJson([
            'msg91_auth_key' => '••••••••_key',
        ]);
        
        $response->assertJsonMissing([
            'ai_system_prompt' => 'You are a helpful assistant.',
        ]);
    }

    public function test_admin_credentials_update_ignores_tampered_tenant_fields()
    {
        $payload = [
            'msg91_auth_key' => 'new_admin_key',
            'ai_system_prompt' => 'Hacked by admin', // Tampered field
        ];

        $response = $this->actingAs($this->adminUser, 'admin')->patch("/admin/tenants/{$this->tenant->id}/credentials", $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $setting = TenantSetting::where('tenant_id', $this->tenant->id)->first();
        
        $this->assertEquals('new_admin_key', $setting->msg91_auth_key);
        $this->assertEquals('You are a helpful assistant.', $setting->ai_system_prompt); // Must not change
    }
}
