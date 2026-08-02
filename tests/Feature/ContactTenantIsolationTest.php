<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_other_tenants_contacts()
    {
        // Setup Tenant A
        $tenantA = Tenant::factory()->create(['features' => ['contacts_bulk_messaging' => true]]);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        
        $contactA = Contact::create([
            'tenant_id' => $tenantA->id,
            'phone_number' => '111111111',
        ]);

        // Setup Tenant B
        $tenantB = Tenant::factory()->create(['features' => ['contacts_bulk_messaging' => true]]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        
        $contactB = Contact::create([
            'tenant_id' => $tenantB->id,
            'phone_number' => '222222222',
        ]);

        // Login as User A and try to access Tenant B's contact
        $this->actingAs($userA);

        // Try to read Tenant B's contact (IDOR check)
        $response = $this->get('/contacts/' . $contactB->id);
        $response->assertStatus(404);

        // Try to update Tenant B's contact
        $response = $this->put('/contacts/' . $contactB->id, ['name' => 'Hacked']);
        $response->assertStatus(404);

        // Try to delete Tenant B's contact
        $response = $this->delete('/contacts/' . $contactB->id);
        $response->assertStatus(404);

        // Also test index filtering
        $response = $this->get('/contacts');
        $response->assertStatus(200);
        $response->assertSee('111111111');
        $response->assertDontSee('222222222');
        // Let's add a quick show route for Contacts to test explicit 404.
        // Actually, BelongsToTenant prevents the query from even finding it. 
        // If we query it directly:
        $this->assertNull(Contact::find($contactB->id)); // BelongsToTenant global scope hides it!
    }
}
