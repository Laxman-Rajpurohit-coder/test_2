<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContactImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
    }

    public function test_can_import_csv_with_many_custom_header_columns()
    {
        $csvContent = "phone_number,name,email,City,Plan,Grand Total,Kist Amount,Pending Kist,Pending Amount,Status\n"
                    . "919913844300,Sanjay,sanjay@example.com,Mumbai,Enterprise,5000,1000,5,4000.00,Active\n"
                    . "918461244495,Rahul,rahul@example.com,Delhi,Pro,9000,1000,5,8000.00,Pending\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tempFile, $csvContent);

        $file = new UploadedFile($tempFile, 'contacts.csv', 'text/csv', null, true);

        $service = new ContactImportService();
        $result = $service->import($file, $this->tenant->id);

        $this->assertEquals(2, $result['imported']);
        $this->assertEmpty($result['errors']);

        $contact = Contact::where('phone_number', '919913844300')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Sanjay', $contact->name);
        $this->assertEquals('sanjay@example.com', $contact->email);

        $customFields = $contact->custom_fields;
        $this->assertEquals('Mumbai', $customFields['City']);
        $this->assertEquals('Enterprise', $customFields['Plan']);
        $this->assertEquals('5000', $customFields['Grand Total']);
        $this->assertEquals('1000', $customFields['Kist Amount']);
        $this->assertEquals('5', $customFields['Pending Kist']);
        $this->assertEquals('4000.00', $customFields['Pending Amount']);
        $this->assertEquals('Active', $customFields['Status']);

        unlink($tempFile);
    }

    public function test_handles_utf8_bom_and_alternative_delimiters()
    {
        // Semicolon delimited file with UTF-8 BOM
        $bom = "\xEF\xBB\xBF";
        $csvContent = $bom . "Mobile Number;Full Name;Email Address;Region\n"
                    . "9876543210;Amit Kumar;amit@example.com;North\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_bom');
        file_put_contents($tempFile, $csvContent);

        $file = new UploadedFile($tempFile, 'contacts_bom.csv', 'text/csv', null, true);

        $service = new ContactImportService();
        $result = $service->import($file, $this->tenant->id);

        $this->assertEquals(1, $result['imported']);
        $this->assertEmpty($result['errors']);

        $contact = Contact::where('phone_number', '919876543210')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Amit Kumar', $contact->name);
        $this->assertEquals('North', $contact->custom_fields['Region']);

        unlink($tempFile);
    }

    public function test_merges_custom_fields_on_existing_contact_update()
    {
        $existing = Contact::create([
            'tenant_id' => $this->tenant->id,
            'phone_number' => '919999988888',
            'name' => 'Original Name',
            'custom_fields' => ['ExistingKey' => 'ExistingValue']
        ]);

        $csvContent = "phone_number,NewKey\n"
                    . "919999988888,NewValue\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_update');
        file_put_contents($tempFile, $csvContent);

        $file = new UploadedFile($tempFile, 'contacts_update.csv', 'text/csv', null, true);

        $service = new ContactImportService();
        $result = $service->import($file, $this->tenant->id);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['updated']);

        $existing->refresh();
        $this->assertEquals('Original Name', $existing->name);
        $this->assertEquals('ExistingValue', $existing->custom_fields['ExistingKey']);
        $this->assertEquals('NewValue', $existing->custom_fields['NewKey']);

        unlink($tempFile);
    }

    public function test_skips_invalid_phone_rows_and_reports_errors()
    {
        $csvContent = "phone,name\n"
                    . ",No Phone User\n"
                    . "919111122222,Valid User\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_invalid');
        file_put_contents($tempFile, $csvContent);

        $file = new UploadedFile($tempFile, 'contacts_invalid.csv', 'text/csv', null, true);

        $service = new ContactImportService();
        $result = $service->import($file, $this->tenant->id);

        $this->assertEquals(1, $result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Row 2', $result['errors'][0]);

        unlink($tempFile);
    }
}
