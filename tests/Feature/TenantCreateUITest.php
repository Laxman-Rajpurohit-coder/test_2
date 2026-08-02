<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantCreateUITest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_tenant_via_ui_and_count_increments()
    {
        $admin = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'admin');

        $initialCount = Tenant::count();

        $response = $this->post('/admin/tenants', [
            'name' => 'My New Workspace',
            'slug' => 'my-new-workspace',
        ]);

        $response->assertSessionHas('success', 'Tenant created.');
        $response->assertRedirect(); // Back

        $this->assertEquals($initialCount + 1, Tenant::count());
        $this->assertDatabaseHas('tenants', [
            'name' => 'My New Workspace',
            'slug' => 'my-new-workspace',
            'status' => 'active',
        ]);
    }

    public function test_duplicate_slug_returns_validation_error_not_500()
    {
        $admin = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'admin');

        Tenant::create([
            'name' => 'Existing',
            'slug' => 'existing-slug',
            'status' => 'active'
        ]);

        $response = $this->post('/admin/tenants', [
            'name' => 'Duplicate Attempt',
            'slug' => 'existing-slug',
        ]);

        // Expect a 302 redirect back with validation errors, NOT a 500 status code
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['slug']);
    }
}
