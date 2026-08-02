<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Msg91KeyPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_msg91_key_masking_does_not_overwrite_real_key_on_save()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'status' => 'active']);
        $user = \App\Models\User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id]);
        $this->actingAs($user);

        // 1. Save a real key for the first time
        $realKey = 'REAL_SECRET_KEY_9999';
        $this->post('/settings/tenant', [
            'msg91_auth_key' => $realKey,
            'ai_provider' => 'openai',
        ]);

        $setting = TenantSetting::where('tenant_id', $tenant->id)->first();
        $this->assertEquals($realKey, $setting->msg91_auth_key);

        // 2. Load the edit page to see the masked key
        $response = $this->get('/settings/tenant');
        $maskedKey = $response->viewData('page')['props']['settings']['msg91_auth_key'];
        $this->assertEquals('••••••••9999', $maskedKey); // Masked properly

        // 3. User submits the form again WITHOUT changing the masked key
        $this->post('/settings/tenant', [
            'msg91_auth_key' => $maskedKey, // Sending the literal bullet string
            'ai_provider' => 'openai',
        ]);

        // 4. Verify the database STILL holds the real key, not the bullets
        $setting->refresh();
        $this->assertEquals($realKey, $setting->msg91_auth_key);
    }
}
