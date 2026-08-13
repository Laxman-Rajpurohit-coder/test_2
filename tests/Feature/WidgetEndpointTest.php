<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\TenantNumber;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['slug' => 'widget-tenant']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->number = TenantNumber::create([
            'tenant_id' => $this->tenant->id,
            'integrated_number' => '919876543210',
        ]);
        $this->setting = TenantSetting::create([
            'tenant_id' => $this->tenant->id,
            'widget_title' => 'Chat with us',
            'widget_welcome_msg' => 'Welcome!',
            'widget_color' => '#00a884',
            'widget_position' => 'bottom-right',
            'widget_auto_redirect_wa' => true,
            'widget_target_phone' => '919876543210',
        ]);
    }

    public function test_widget_script_returns_javascript_for_valid_tenant()
    {
        $response = $this->get('/widget/v1/' . $this->tenant->id . '.js');

        $response->assertStatus(200)
                 ->assertHeader('Content-Type', 'application/javascript');

        $content = $response->getContent();
        $this->assertStringContainsString('Chat with us', $content);
        $this->assertStringContainsString('#00a884', $content);
    }

    public function test_widget_script_returns_404_for_invalid_tenant()
    {
        $response = $this->get('/widget/v1/999999.js');
        $response->assertStatus(404);
    }

    public function test_widget_submission_ingests_contact_with_consent_true()
    {
        $response = $this->postJson('/api/v1/widget/' . $this->tenant->id . '/submit', [
            'phone_number' => '919999911111',
            'name' => 'Widget Visitor',
            'message' => 'Need pricing information',
        ]);

        $response->assertStatus(201)
                 ->assertHeader('Access-Control-Allow-Origin', '*')
                 ->assertJsonPath('success', true)
                 ->assertJsonMissingPath('contact'); // Never leak Eloquent model!

        $contact = Contact::where('tenant_id', $this->tenant->id)
            ->where('phone_number', '919999911111')
            ->first();

        $this->assertNotNull($contact);
        $this->assertTrue($contact->is_subscribed, 'Widget lead ingestion must default consent to true.');
        $this->assertEquals('Website WhatsApp Widget', $contact->custom_fields['Source']);
        $this->assertEquals('Need pricing information', $contact->custom_fields['Message']);
    }

    public function test_widget_submission_handles_cors_preflight()
    {
        $response = $this->options('/api/v1/widget/' . $this->tenant->id . '/submit');

        $response->assertStatus(200)
                 ->assertHeader('Access-Control-Allow-Origin', '*')
                 ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    }

    public function test_widget_target_phone_must_belong_to_tenant()
    {
        $otherTenant = Tenant::factory()->create();
        $otherNumber = TenantNumber::create([
            'tenant_id' => $otherTenant->id,
            'integrated_number' => '919999999999',
        ]);

        $this->actingAs($this->user);

        // Attempting to configure target phone belonging to another tenant
        $response = $this->post('/settings/tenant', [
            'widget_target_phone' => '919999999999', // belongs to $otherTenant!
        ]);

        $response->assertSessionHasErrors('widget_target_phone');
    }

    public function test_hex_color_validation_rejects_invalid_css()
    {
        $this->actingAs($this->user);

        $response = $this->post('/settings/tenant', [
            'widget_color' => 'invalid-color-string-xss',
        ]);

        $response->assertSessionHasErrors('widget_color');
    }

    public function test_widget_script_prevents_xss_injection()
    {
        $this->setting->update([
            'widget_title' => 'Title</script><script>alert("xss")</script>',
        ]);

        $response = $this->get('/widget/v1/' . $this->tenant->id . '.js');
        $response->assertStatus(200);

        $content = $response->getContent();
        // Verifying json_encode HEX_TAG escaping prevented raw unescaped script tag injection
        $this->assertStringNotContainsString('Title</script><script>', $content);
        $this->assertStringContainsString('\u003C', $content);
    }

    public function test_route_parameter_is_authoritative_over_body_tenant_id()
    {
        $otherTenant = Tenant::factory()->create();

        // Send payload containing tenant_id = $otherTenant->id inside JSON body
        $response = $this->postJson('/api/v1/widget/' . $this->tenant->id . '/submit', [
            'phone_number' => '919888877777',
            'name' => 'Attacker Lead',
            'tenant_id' => $otherTenant->id, // Malicious body tamper attempt!
        ]);

        $response->assertStatus(201);

        // Contact MUST belong to $this->tenant->id (route param), NOT $otherTenant->id
        $contact = Contact::where('phone_number', '919888877777')->first();
        $this->assertNotNull($contact);
        $this->assertEquals($this->tenant->id, $contact->tenant_id, 'Contact tenant_id must strictly match the route parameter, ignoring body payload.');
    }

    public function test_cross_tenant_url_isolation()
    {
        $otherTenant = Tenant::factory()->create();

        $this->postJson('/api/v1/widget/' . $otherTenant->id . '/submit', [
            'phone_number' => '919555544444',
            'name' => 'Tenant B Visitor',
        ])->assertStatus(201);

        $contact = Contact::where('phone_number', '919555544444')->first();
        $this->assertEquals($otherTenant->id, $contact->tenant_id);
    }

    public function test_widget_submission_returns_cors_headers_on_validation_failure()
    {
        $response = $this->postJson('/api/v1/widget/' . $this->tenant->id . '/submit', [
            'phone_number' => 'invalid-phone-string',
        ]);

        $response->assertStatus(422)
                 ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_widget_js_emits_valid_wa_me_url()
    {
        $response = $this->get('/widget/v1/' . $this->tenant->id . '.js');
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('https://wa.me/', $content);
        $this->assertStringContainsString('window.open', $content);
    }

    public function test_widget_script_revalidates_deleted_target_phone()
    {
        // Delete the integrated number that was set as target_phone
        $this->number->delete();

        $response = $this->get('/widget/v1/' . $this->tenant->id . '.js');
        $response->assertStatus(200);

        $content = $response->getContent();
        // Should gracefully fall back to empty targetPhone rather than emitting deleted phone
        $this->assertStringContainsString('"targetPhone":""', $content);
    }
}
