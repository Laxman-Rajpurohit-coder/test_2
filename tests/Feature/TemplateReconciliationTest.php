<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantNumber;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemplateReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'features' => ['template_management' => true]
        ]);
        
        TenantSetting::create([
            'tenant_id' => $this->tenant->id,
            'msg91_auth_key' => 'mock_auth_key'
        ]);

        TenantNumber::create([
            'tenant_id' => $this->tenant->id,
            'integrated_number' => '919876543210',
        ]);

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_sync_upserts_new_and_existing_templates()
    {
        // One existing template to be updated to approved
        WhatsappTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'existing_promo',
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => 'pending', // will change to approved
            'components' => [],
        ]);

        // MSG91 API mock
        Http::fake([
            'control.msg91.com/api/v5/whatsapp/get-template-client/*' => Http::response([
                'data' => [
                    [
                        'name' => 'existing_promo',
                        'category' => 'MARKETING',
                        'languages' => [
                            [
                                'language' => 'en',
                                'status' => 'approved',
                                'code' => []
                            ]
                        ]
                    ],
                    [
                        'name' => 'new_promo',
                        'category' => 'UTILITY',
                        'languages' => [
                            [
                                'language' => 'hi',
                                'status' => 'pending',
                                'code' => []
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)->postJson(route('templates.sync'));
        
        $response->assertStatus(200);

        // Verify existing updated
        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'existing_promo',
            'language' => 'en',
            'status' => 'approved'
        ]);

        // Verify new inserted
        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'new_promo',
            'language' => 'hi',
            'status' => 'pending',
            'category' => 'UTILITY'
        ]);
    }

    public function test_sync_hard_deletes_orphaned_local_templates()
    {
        // This template is no longer on MSG91
        $orphaned = WhatsappTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'deleted_promo',
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => 'approved',
            'components' => [],
        ]);

        Http::fake([
            'control.msg91.com/api/v5/whatsapp/get-template-client/*' => Http::response([
                'data' => [
                    // deleted_promo is omitted
                    [
                        'name' => 'active_promo',
                        'category' => 'MARKETING',
                        'languages' => [
                            [
                                'language' => 'en',
                                'status' => 'approved',
                                'code' => []
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->actingAs($this->user)->postJson(route('templates.sync'));

        $this->assertDatabaseMissing('whatsapp_templates', [
            'id' => $orphaned->id
        ]);
        
        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'active_promo'
        ]);
    }

    public function test_sync_records_rejection_reason()
    {
        Http::fake([
            'control.msg91.com/api/v5/whatsapp/get-template-client/*' => Http::response([
                'data' => [
                    [
                        'name' => 'bad_promo',
                        'category' => 'MARKETING',
                        'languages' => [
                            [
                                'language' => 'en',
                                'status' => 'rejected',
                                'rejection_reason' => 'Violates commerce policy',
                                'code' => []
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->actingAs($this->user)->postJson(route('templates.sync'));

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'bad_promo',
            'status' => 'rejected',
            'rejection_reason' => 'Violates commerce policy'
        ]);
    }
}
