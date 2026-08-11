<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignBuilderEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_count_endpoint_calculates_audience_volume_and_excludes_unsubscribed()
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'tenant-a',
            'features' => ['contacts_bulk_messaging' => true]
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner'
        ]);

        // Active subscribed contacts
        $c1 = Contact::create(['tenant_id' => $tenant->id, 'phone_number' => '919800000001', 'is_subscribed' => true]);
        $c2 = Contact::create(['tenant_id' => $tenant->id, 'phone_number' => '919800000002', 'is_subscribed' => true]);

        // Unsubscribed contact (must be excluded)
        Contact::create(['tenant_id' => $tenant->id, 'phone_number' => '919800000003', 'is_subscribed' => false]);

        // Another tenant contact (tenant isolation check)
        $otherTenant = Tenant::factory()->create(['slug' => 'tenant-b']);
        Contact::create(['tenant_id' => $otherTenant->id, 'phone_number' => '919800000004', 'is_subscribed' => true]);

        // Tag
        $tag = ContactTag::create(['tenant_id' => $tenant->id, 'name' => 'VIP']);
        $c1->contactTags()->attach($tag->id);

        // Test 1: Count all
        $resAll = $this->actingAs($user)
            ->postJson(route('campaigns.recipient-count'), [
                'target_type' => 'all'
            ]);

        $resAll->assertStatus(200)
            ->assertJson([
                'recipient_count' => 2,
                'excluded' => 1
            ]);

        // Test 2: Count by tag
        $resTag = $this->actingAs($user)
            ->postJson(route('campaigns.recipient-count'), [
                'target_type' => 'tags',
                'tag_ids' => [$tag->id]
            ]);

        $resTag->assertStatus(200)
            ->assertJson([
                'recipient_count' => 1,
                'target_type' => 'tags'
            ]);
    }

    public function test_recipient_count_endpoint_validates_unauthorized_tag_ids()
    {
        $tenantA = Tenant::factory()->create([
            'slug' => 'tenant-a',
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
        $tagB = ContactTag::create(['tenant_id' => $tenantB->id, 'name' => 'Tenant B Tag']);

        // User A tries to pass Tag B
        $response = $this->actingAs($user)
            ->postJson(route('campaigns.recipient-count'), [
                'target_type' => 'tags',
                'tag_ids' => [$tagB->id]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids.0']);
    }
}
