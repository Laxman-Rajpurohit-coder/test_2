<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\ContactImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ContactImportService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContactImportService();
        $this->tenant = Tenant::factory()->create();
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($this->tenant->id);
    }

    public function test_import_header_matching_ignores_order_number_and_selects_phone()
    {
        $csvContent = "Order Number,Mobile Number,Name\n12345,919876543210,John Doe\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $result = $this->service->import($file, (string)$this->tenant->id);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, count($result['errors']));

        $contact = \App\Models\Contact::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals('919876543210', $contact->phone_number);
        $this->assertEquals('John Doe', $contact->name);
        $this->assertEquals('12345', $contact->custom_fields['Order Number']);
    }

    public function test_import_handles_utf8_bom_header()
    {
        // Prepend UTF-8 BOM bytes (\xEF\xBB\xBF) to header
        $bomCsv = "\xEF\xBB\xBFPhone,Name\n919111122222,BOM User\n";
        $file = UploadedFile::fake()->createWithContent('bom_contacts.csv', $bomCsv);

        $result = $this->service->import($file, (string)$this->tenant->id);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, count($result['errors']));

        $contact = \App\Models\Contact::where('phone_number', '919111122222')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('BOM User', $contact->name);
    }

    public function test_import_caps_errors_at_100()
    {
        $invalidRows = "Phone,Name\n";
        for ($i = 0; $i < 150; $i++) {
            $invalidRows .= "invalid_phone,User_{$i}\n";
        }
        $file = UploadedFile::fake()->createWithContent('bad_contacts.csv', $invalidRows);

        $result = $this->service->import($file, (string)$this->tenant->id);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(100, count($result['errors']), 'Errors array must be capped at 100 max to prevent memory exhaustion.');
    }
}
