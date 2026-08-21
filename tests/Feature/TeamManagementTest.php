<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable foreign key constraints so that ConversationFactory doesn't fail on missing tenant_numbers
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
    }

    public function test_tenant_isolation_during_member_creation()
    {
        $tenantA = Tenant::factory()->create();
        $ownerA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);

        $tenantB = Tenant::factory()->create();
        $ownerB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'owner']);

        // Owner A creates a member
        $response = $this->actingAs($ownerA)->post('/team', [
            'name' => 'Member A',
            'email' => 'member.a@example.com',
            'role' => 'member'
        ]);
        
        $response->assertRedirect();
        
        $member = User::where('email', 'member.a@example.com')->first();
        $this->assertNotNull($member);
        
        // Assert the member belongs to Tenant A, not Tenant B
        $this->assertEquals($tenantA->id, $member->tenant_id);
    }

    public function test_member_view_scoping()
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'member']);
        
        $contactAssigned = Contact::factory()->create(['tenant_id' => $tenant->id, 'assigned_user_id' => $member->id]);
        $contactUnassigned = Contact::factory()->create(['tenant_id' => $tenant->id]);
        
        $tenantNumber = \App\Models\TenantNumber::factory()->create(['tenant_id' => $tenant->id]);
        
        $convAssigned = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 
            'tenant_number_id' => $tenantNumber->id,
            'customer_number' => $contactAssigned->phone_number
        ]);
        $convUnassigned = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 
            'tenant_number_id' => $tenantNumber->id,
            'customer_number' => $contactUnassigned->phone_number
        ]);

        // Owner sees both
        $response = $this->actingAs($owner)->get('/api/conversations');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        // Member sees only assigned
        $response = $this->actingAs($member)->get('/api/conversations');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($convAssigned->id, $response->json('data')[0]['id']);
    }

    public function test_member_send_restriction()
    {
        $tenant = Tenant::factory()->create();
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'member']);
        $tenantNumber = \App\Models\TenantNumber::factory()->create(['tenant_id' => $tenant->id]);
        
        $contactUnassigned = Contact::factory()->create(['tenant_id' => $tenant->id]);
        $convUnassigned = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 
            'tenant_number_id' => $tenantNumber->id,
            'customer_number' => $contactUnassigned->phone_number
        ]);
        
        // Ensure the session window exists so it doesn't fail on the 24-hr rule before hitting the authorization check
        Cache::put('session:' . $convUnassigned->customer_number, true, 86400);

        // Try sending to unassigned contact
        $response = $this->actingAs($member)->postJson("/api/conversations/{$convUnassigned->id}/messages", [
            'type' => 'text',
            'content' => 'Hello'
        ]);
        
        $response->assertStatus(403);
    }

    public function test_bulk_assignment_security()
    {
        $tenantA = Tenant::factory()->create(['features' => ['contacts_bulk_messaging' => true]]);
        $ownerA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
        $memberA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'member']);
        $contactA = Contact::factory()->create(['tenant_id' => $tenantA->id]);

        $tenantB = Tenant::factory()->create(['features' => ['contacts_bulk_messaging' => true]]);
        $contactB = Contact::factory()->create(['tenant_id' => $tenantB->id]);
        $memberB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'member']);

        // Owner A tries to assign Contact A to Member B (cross-tenant IDOR)
        $response = $this->actingAs($ownerA)->post('/contacts/bulk-assign', [
            'contact_ids' => [$contactA->id],
            'assigned_user_id' => $memberB->id
        ]);
        
        // Validation should fail because memberB doesn't exist in tenant A's context according to TeamController or it will 404
        $response->assertStatus(404);

        // Member A tries to assign Contact A (role check)
        $response2 = $this->actingAs($memberA)->post('/contacts/bulk-assign', [
            'contact_ids' => [$contactA->id],
            'assigned_user_id' => $memberA->id
        ]);
        $response2->assertStatus(403);
    }

    public function test_page_gating_for_members()
    {
        $tenant = Tenant::factory()->create();
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'member']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        // Test team management POST (Owner only)
        $this->actingAs($admin)->post('/team', [
            'name' => 'Test', 'email' => 'test@test.com', 'role' => 'member'
        ])->assertStatus(403);
        
        // Test settings access (Owner/Admin only)
        $this->actingAs($member)->get('/settings/tenant')->assertStatus(403);
        $this->actingAs($admin)->get('/settings/tenant')->assertStatus(200);
    }
}
