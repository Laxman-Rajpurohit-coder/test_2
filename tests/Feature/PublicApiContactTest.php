<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['slug' => 'api-tenant']);
        $this->rawKey = 'api_key_test_token_123456789012345678901234567890';
        $this->setting = TenantSetting::create([
            'tenant_id' => $this->tenant->id,
            'public_api_key' => hash('sha256', $this->rawKey),
            'public_api_key_last_four' => substr($this->rawKey, -4),
        ]);
    }

    public function test_public_api_rejects_missing_or_invalid_bearer_token()
    {
        // Missing token
        $response = $this->postJson('/api/v1/contacts', [
            'phone_number' => '919876543210',
            'name' => 'John Doe'
        ]);
        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorized. Missing Bearer Token.']);

        // Invalid token
        $response = $this->withHeader('Authorization', 'Bearer api_key_wrongkey')
                         ->postJson('/api/v1/contacts', [
                             'phone_number' => '919876543210',
                             'name' => 'John Doe'
                         ]);
        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorized. Invalid API Key.']);
    }

    public function test_public_api_creates_contact_with_default_consent_true()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
                         ->postJson('/api/v1/contacts', [
                             'phone_number' => '919876543210',
                             'name' => 'John Website Lead',
                             'email' => 'lead@example.com',
                             'custom_fields' => ['Source' => 'Website Contact Form']
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('contact.name', 'John Website Lead');

        $contact = Contact::where('tenant_id', $this->tenant->id)
            ->where('phone_number', '919876543210')
            ->first();

        $this->assertNotNull($contact);
        $this->assertEquals('John Website Lead', $contact->name);
        $this->assertTrue($contact->is_subscribed, 'Consent must default to true.');
        $this->assertEquals('Website Contact Form', $contact->custom_fields['Source']);
    }

    public function test_public_api_respects_explicit_consent_false()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
                         ->postJson('/api/v1/contacts', [
                             'phone_number' => '919876543211',
                             'name' => 'Opted-Out Lead',
                             'whatsapp_consent' => false
                         ]);

        $response->assertStatus(201);

        $contact = Contact::where('tenant_id', $this->tenant->id)
            ->where('phone_number', '919876543211')
            ->first();

        $this->assertNotNull($contact);
        $this->assertFalse($contact->is_subscribed);
    }

    public function test_it_never_downgrades_consent_via_api()
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'phone_number' => '919876543999',
            'name' => 'Existing Opted-In User',
            'is_subscribed' => true,
        ]);

        // Call API without whatsapp_consent flag (defaults to false)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
                         ->postJson('/api/v1/contacts', [
                             'phone_number' => '919876543999',
                             'name' => 'Updated User Name',
                         ]);

        $response->assertStatus(200);

        $contact->refresh();
        $this->assertEquals('Updated User Name', $contact->name);
        $this->assertTrue($contact->is_subscribed, 'API update must never downgrade consent from true to false.');
    }

    public function test_it_prevents_rate_limit_bypass_via_rotating_invalid_tokens()
    {
        // Send 30 requests with random fake tokens
        for ($i = 0; $i < 30; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer sk_fake_random_' . $i)
                             ->postJson('/api/v1/contacts', ['phone_number' => '919000000000']);
            $response->assertStatus(401);
        }

        // The 31st request from the same IP with a new fake token should hit 429 Too Many Requests
        $response = $this->withHeader('Authorization', 'Bearer sk_fake_random_31')
                         ->postJson('/api/v1/contacts', ['phone_number' => '919000000000']);
        $response->assertStatus(429);
    }
}
