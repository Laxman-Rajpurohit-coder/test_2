<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['features' => ['contacts_bulk_messaging' => true]]);
        $this->tenantB = Tenant::factory()->create(['features' => ['contacts_bulk_messaging' => true]]);

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'owner']);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id, 'role' => 'owner']);

        app(\App\Services\TenantResolverService::class)->setActiveTenantId($this->tenantA->id);
        $this->withSession(['impersonated_tenant_id' => $this->tenantA->id]);
    }

    public function test_store_rejects_cross_tenant_assigned_user_id()
    {
        $response = $this->actingAs($this->userA)->post('/contacts', [
            'name' => 'Cross Tenant Test',
            'phone_number' => '919999888877',
            'assigned_user_id' => $this->userB->id, // Belongs to Tenant B
        ]);

        $response->assertStatus(404);
    }

    public function test_update_rejects_cross_tenant_assigned_user_id()
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'phone_number' => '919876543210',
        ]);

        $response = $this->actingAs($this->userA)->putJson('/contacts/' . $contact->id, [
            'name' => 'Updated Name',
            'assigned_user_id' => $this->userB->id, // Belongs to Tenant B
        ]);

        $response->assertStatus(404);
    }

    public function test_update_cannot_mutate_tenant_id_or_is_subscribed()
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'phone_number' => '919876543210',
            'is_subscribed' => true,
        ]);

        $response = $this->actingAs($this->userA)->putJson('/contacts/' . $contact->id, [
            'name' => 'Hacker Update',
            'tenant_id' => $this->tenantB->id,
            'is_subscribed' => false,
        ]);

        $response->assertStatus(200);

        $contact->refresh();
        $this->assertEquals($this->tenantA->id, $contact->tenant_id, 'Tenant ID must never be mutated via update.');
        $this->assertTrue($contact->is_subscribed, 'is_subscribed must not be un-subscribed via update endpoint.');
    }

    public function test_bulk_tag_rejects_cross_tenant_tag_ids()
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'phone_number' => '919876543210',
        ]);

        $tagB = ContactTag::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Secret Tag',
        ]);

        $response = $this->actingAs($this->userA)->post('/contacts/bulk-tag', [
            'contact_ids' => [$contact->id],
            'tag_ids' => [$tagB->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_bulk_assign_ignores_cross_tenant_contact_ids()
    {
        $contactB = Contact::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'phone_number' => '919111111111',
            'assigned_user_id' => null,
        ]);

        $response = $this->actingAs($this->userA)->post('/contacts/bulk-assign', [
            'contact_ids' => [$contactB->id],
            'assigned_user_id' => $this->userA->id,
        ]);

        $response->assertStatus(302);
        $contactB->refresh();
        $this->assertNull($contactB->assigned_user_id, 'Bulk assign must never modify contacts belonging to another tenant.');
    }

    public function test_update_and_delete_rejects_cross_tenant_contact_id()
    {
        $contactB = Contact::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'phone_number' => '919222222222',
        ]);

        $updateResp = $this->actingAs($this->userA)->putJson('/contacts/' . $contactB->id, [
            'name' => 'Tampered Name',
        ]);
        $updateResp->assertStatus(404);

        $deleteResp = $this->actingAs($this->userA)->deleteJson('/contacts/' . $contactB->id);
        $deleteResp->assertStatus(404);
    }
}
