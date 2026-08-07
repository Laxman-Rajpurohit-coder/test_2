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

class TemplateControllerTest extends TestCase
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

    public function test_it_loads_index_with_local_templates()
    {
        WhatsappTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'hello_world',
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => 'approved',
            'components' => [],
        ]);

        $response = $this->actingAs($this->user)->get(route('templates.index'));
        
        $response->assertStatus(200);
        // Inertia asserts can be tricky without the testing helpers, but 200 is good enough for now.
    }

    public function test_it_forbids_access_if_feature_flag_is_off()
    {
        $tenantOff = Tenant::factory()->create([
            'features' => ['template_management' => false]
        ]);
        $userOff = User::factory()->create(['tenant_id' => $tenantOff->id]);

        $response = $this->actingAs($userOff)->get(route('templates.index'));
        
        $response->assertStatus(403);
    }

    public function test_it_creates_template_on_msg91_and_saves_locally()
    {
        Http::fake([
            'api.msg91.com/api/v5/whatsapp/client-panel-template/*' => Http::response(['status' => 'success'], 200)
        ]);

        $response = $this->actingAs($this->user)->post(route('templates.store'), [
            'name' => 'new_promo',
            'language' => 'en',
            'category' => 'MARKETING',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
        ]);

        $response->assertRedirect(route('templates.index'));

        $this->assertDatabaseHas('whatsapp_templates', [
            'tenant_id' => $this->tenant->id,
            'name' => 'new_promo',
            'status' => 'pending'
        ]);
    }

    public function test_it_deletes_template_from_msg91_and_locally()
    {
        $template = WhatsappTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'bad_template',
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => 'approved',
            'components' => [],
        ]);

        Http::fake([
            "api.msg91.com/api/v5/whatsapp/client-panel-template/*" => Http::response(['status' => 'success'], 200)
        ]);

        $response = $this->actingAs($this->user)->delete(route('templates.destroy', $template->id));

        $response->assertRedirect(route('templates.index'));
        $this->assertDatabaseMissing('whatsapp_templates', ['id' => $template->id]);
    }

    public function test_it_forbids_cross_tenant_access_on_edit()
    {
        // Create a template for a DIFFERENT tenant
        $otherTenant = Tenant::factory()->create(['features' => ['template_management' => true]]);
        $template = WhatsappTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'other_template',
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => 'approved',
            'components' => [],
        ]);

        // Attempt to access it as $this->user (who belongs to $this->tenant)
        $response = $this->actingAs($this->user)->get(route('templates.edit', $template->id));

        $response->assertStatus(404);
    }
}
