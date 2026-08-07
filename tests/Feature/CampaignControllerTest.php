<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContactGroup;
use App\Models\ContactTag;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_template_campaign_with_variable_map()
    {
        $tenant = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->postJson(route('campaigns.store'), [
            'name' => 'Mapped Promo',
            'message_type' => 'template',
            'template_name' => 'hello_world',
            'template_language' => 'en',
            'template_variable_map' => ['first_name', 'city'],
            'target_type' => 'all',
        ]);

        $response->assertRedirect();

        $campaign = Campaign::first();
        $this->assertEquals('hello_world', $campaign->template_name);
        $this->assertEquals(['first_name', 'city'], $campaign->template_variable_map);
    }

    public function test_it_prevents_target_id_idor_by_404ing_on_mismatched_tenant_group()
    {
        $tenant1 = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        
        $tenant2 = Tenant::factory()->create();
        $group2 = ContactGroup::create(['tenant_id' => $tenant2->id, 'name' => 'Target Group']);

        $response = $this->actingAs($user1)->postJson(route('campaigns.store'), [
            'name' => 'Hacked Promo',
            'message_type' => 'text',
            'text_content' => 'Hello',
            'target_type' => 'group',
            'target_id' => $group2->id, // Passing tenant 2's group ID
        ]);

        // The controller should throw ModelNotFoundException for ContactGroup::where('tenant_id', $tenantId)->findOrFail($validated['target_id'])
        $response->assertStatus(404);
        $this->assertCount(0, Campaign::where('tenant_id', $tenant1->id)->get());
    }

    public function test_it_schedules_campaign_and_dispatches_delayed_job()
    {
        $tenant = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\SendCampaignJob::class]);

        $futureDate = now()->addDays(2)->format('Y-m-d\TH:i');

        $response = $this->actingAs($user)->postJson(route('campaigns.store'), [
            'name' => 'Scheduled Promo',
            'message_type' => 'text',
            'text_content' => 'Hello Future',
            'target_type' => 'all',
            'scheduled_at' => $futureDate,
            'timezone' => 'UTC'
        ]);

        $response->assertRedirect();

        $campaign = Campaign::where('name', 'Scheduled Promo')->first();
        $this->assertEquals('scheduled', $campaign->status);
        $this->assertNotNull($campaign->scheduled_at);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendCampaignJob::class, function ($job) use ($campaign) {
            return $job->campaignId === $campaign->id && $job->delay !== null;
        });
    }
}
