<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_index_loads_successfully_with_diverse_custom_fields()
    {
        $tenant = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        // Contact with object custom fields
        Contact::create([
            'tenant_id' => $tenant->id,
            'name' => 'Valid Object Contact',
            'phone_number' => '919888888881',
            'custom_fields' => ['city' => 'Mumbai', 'tier' => 'VIP'],
        ]);

        // Contact with empty custom fields
        Contact::create([
            'tenant_id' => $tenant->id,
            'name' => 'Empty Contact',
            'phone_number' => '919888888882',
            'custom_fields' => [],
        ]);

        $response = $this->actingAs($user)->get('/contacts');
        $response->assertStatus(200);

        $page = $response->original->getData()['page'];
        $fields = $page['props']['availableContactFields'] ?? [];
        $this->assertContains('city', $fields);
        $this->assertContains('tier', $fields);
    }

    public function test_contact_tags_tenant_isolation()
    {
        $tenantA = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $tenantB = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenantA->id);

        $tagA = ContactTag::create(['tenant_id' => $tenantA->id, 'name' => 'VIP Tag']);
        $tagB = ContactTag::create(['tenant_id' => $tenantB->id, 'name' => 'Secret Tag']);

        // User A lists tags -> only sees Tag A
        $response = $this->actingAs($userA)->getJson('/contact-tags');
        $response->assertStatus(200);
        $tagIds = collect($response->json())->pluck('id')->all();
        $this->assertContains($tagA->id, $tagIds);
        $this->assertNotContains($tagB->id, $tagIds);

        // User A cannot delete Tag B
        $delResponse = $this->actingAs($userA)->deleteJson("/contact-tags/{$tagB->id}");
        $delResponse->assertStatus(404);
    }

    public function test_bulk_tag_applies_tags_to_all_selected_contacts()
    {
        $tenant = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $tag = ContactTag::create(['tenant_id' => $tenant->id, 'name' => 'BARMER']);

        $contactIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $c = Contact::create([
                'tenant_id' => $tenant->id,
                'name' => "Batch Contact {$i}",
                'phone_number' => "91900000000{$i}",
            ]);
            $contactIds[] = $c->id;
        }

        $response = $this->actingAs($user)->post(route('contacts.bulk-tag'), [
            'contact_ids' => $contactIds,
            'tag_ids' => [$tag->id],
            'mode' => 'add'
        ]);

        $response->assertSessionHas('success');

        foreach ($contactIds as $cId) {
            $this->assertDatabaseHas('contact_contact_tag', [
                'contact_id' => $cId,
                'contact_tag_id' => $tag->id,
            ]);
        }
    }
}
