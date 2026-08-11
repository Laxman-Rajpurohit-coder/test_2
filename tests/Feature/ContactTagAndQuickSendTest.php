<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendCampaignJob;

class ContactTagAndQuickSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create([
            'features' => ['contacts_bulk_messaging' => true]
        ]);
        $this->owner = clone User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner'
        ]);
        
        $this->actingAs($this->owner);
    }

    public function test_can_bulk_tag_contacts()
    {
        $contact1 = Contact::factory()->create(['tenant_id' => $this->tenant->id, 'phone_number' => '919876543210']);
        $contact2 = Contact::factory()->create(['tenant_id' => $this->tenant->id, 'phone_number' => '919876543211']);
        
        $tag = ContactTag::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'VIP'
        ]);

        $response = $this->post(route('contacts.bulk-tag'), [
            'contact_ids' => [$contact1->id, $contact2->id],
            'tag_ids' => [$tag->id],
        ]);

        $response->assertRedirect();

        $response->assertRedirect();
        
        $this->assertDatabaseHas('contact_contact_tag', [
            'contact_id' => $contact1->id,
            'contact_tag_id' => $tag->id
        ]);
    }

    public function test_can_quick_send_to_contacts()
    {
        Queue::fake();

        $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id, 'phone_number' => '919876543210']);

        $response = $this->post(route('contacts.quick-send'), [
            'contact_ids' => [$contact->id],
            'message_type' => 'text',
            'text_content' => 'Hello there!'
        ]);

        $response->assertRedirect();
        
        $campaign = Campaign::where('is_quick_send', true)->first();
        
        $this->assertNotNull($campaign);
        $this->assertEquals('text', $campaign->message_type);
        $this->assertEquals('Hello there!', $campaign->text_content);
        $this->assertEquals('all', $campaign->target_type);
        $this->assertEquals(1, $campaign->total_recipients);
        
        $this->assertDatabaseHas('campaign_recipients', [
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id
        ]);

        Queue::assertPushed(SendCampaignJob::class, function ($job) use ($campaign) {
            return $job->campaignId === $campaign->id;
        });
    }
}
