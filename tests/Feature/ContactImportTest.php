<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Contact;
use App\Services\ContactImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_and_updates_contacts_from_csv()
    {
        $tenant = Tenant::factory()->create();
        $service = new ContactImportService();
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        // 1. Initial Import
        $csvContent = "phone_number,name,email,company\n+1 555-0100,John Doe,john@example.com,Acme Corp";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $result = $service->import($file, $tenant->id);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['updated']);
        $this->assertCount(1, Contact::where('tenant_id', $tenant->id)->get());

        $contact = Contact::where('tenant_id', $tenant->id)->first();
        $this->assertEquals('15550100', $contact->phone_number);
        $this->assertEquals('John Doe', $contact->name);
        $this->assertEquals('john@example.com', $contact->email);
        $this->assertEquals('Acme Corp', $contact->custom_fields['company']);

        // 2. Re-import same number with different formatting and updated data
        $csvContent2 = "phone_number,name,email,company,role\n0015550100,John Updated,new@example.com,Acme Corp,Manager";
        $file2 = UploadedFile::fake()->createWithContent('contacts_update.csv', $csvContent2);

        $result2 = $service->import($file2, $tenant->id);

        $this->assertEquals(0, $result2['imported']);
        $this->assertEquals(1, $result2['updated']);
        
        // Assert count did not increase
        $this->assertCount(1, Contact::where('tenant_id', $tenant->id)->get());

        $contact->refresh();
        $this->assertEquals('15550100', $contact->phone_number); // Unchanged normalized
        $this->assertEquals('John Updated', $contact->name); // Updated
        $this->assertEquals('new@example.com', $contact->email); // Updated
        $this->assertEquals('Manager', $contact->custom_fields['role']); // Added
    }

    public function test_it_handles_malformed_rows()
    {
        $tenant = Tenant::factory()->create();
        $service = new ContactImportService();
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $csvContent = "phone_number,name\n,No Phone\n+123,Valid";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $result = $service->import($file, $tenant->id);

        $this->assertEquals(1, $result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Row 2', $result['errors'][0]);
    }
}
