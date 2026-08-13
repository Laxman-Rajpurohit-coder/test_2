<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Tenant;
use App\Services\ContactIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContactIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ContactIngestionService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContactIngestionService();
        $this->tenant = Tenant::factory()->create();
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($this->tenant->id);
    }

    public function test_ingest_defaults_whatsapp_consent_to_true_when_omitted()
    {
        $contact = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'Form Filler Without Consent Specified',
            // whatsapp_consent intentionally omitted!
        ]);

        $this->assertNotNull($contact);
        $this->assertTrue($contact->is_subscribed, 'Omitting whatsapp_consent must default to true when filling forms.');
    }

    public function test_ingestion_preserves_custom_field_value_zero()
    {
        $contact = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'Zero Custom Field User',
            'whatsapp_consent' => true,
            'custom_fields' => [
                'Score' => 0,
                'Code' => '0',
            ]
        ]);

        $this->assertEquals(0, $contact->custom_fields['Score']);
        $this->assertEquals('0', $contact->custom_fields['Code']);
    }

    public function test_ingestion_preserves_custom_field_value_false()
    {
        $contact = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'False Custom Field User',
            'whatsapp_consent' => true,
            'custom_fields' => [
                'IsVIP' => false,
            ]
        ]);

        $this->assertFalse($contact->custom_fields['IsVIP']);
    }

    public function test_existing_subscribed_contact_cannot_be_downgraded()
    {
        // First ingestion opts in
        $contact = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'Opted In User',
            'whatsapp_consent' => true,
        ]);
        $this->assertTrue($contact->is_subscribed);

        // Second ingestion attempts to opt out (ratchet prevents downgrade)
        $updated = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'Opted In User Updated',
            'whatsapp_consent' => false,
        ]);

        $this->assertTrue($updated->is_subscribed, 'Consent ratchet must prevent downgrading is_subscribed from true to false.');
    }

    public function test_existing_unsubscribed_contact_can_become_subscribed()
    {
        // First ingestion explicitly opts out
        $contact = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'Opted Out User',
            'whatsapp_consent' => false,
        ]);
        $this->assertFalse($contact->is_subscribed);

        // Second ingestion opts in
        $updated = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'Now Opted In User',
            'whatsapp_consent' => true,
        ]);
        $this->assertTrue($updated->is_subscribed, 'Unsubscribed contact must be upgradeable to subscribed when true is provided.');
    }

    public function test_ingestion_handles_string_false_boolean_validation()
    {
        $contact = $this->service->ingest($this->tenant->id, [
            'phone_number' => '919876543210',
            'name' => 'String False User',
            'whatsapp_consent' => 'false',
        ]);

        $this->assertFalse($contact->is_subscribed, 'String "false" must strictly evaluate to false boolean.');
    }
}
