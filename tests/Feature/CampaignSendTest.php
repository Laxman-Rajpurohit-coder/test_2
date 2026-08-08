<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\OutboundReplyService;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CampaignSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_text_campaign_only_to_contacts_within_24h_window()
    {
        $tenant = Tenant::factory()->create();
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        \Illuminate\Support\Facades\DB::table('tenant_numbers')->insert([
            'tenant_id' => $tenant->id,
            'integrated_number' => '15551234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantSetting::create(['tenant_id' => $tenant->id, 'msg91_auth_key' => 'mock_key']);

        $campaign = Campaign::create([
            'tenant_id' => $tenant->id,
            'name' => 'Promo',
            'message_type' => 'text',
            'text_content' => 'Hello!',
            'status' => 'draft',
            'target_type' => 'all',
            'total_recipients' => 2
        ]);

        // Contact 1: Has 24h session
        $contactInWindow = Contact::create(['tenant_id' => $tenant->id, 'phone_number' => '111111111']);
        $recipientInWindow = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contactInWindow->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending'
        ]);
        Cache::put('session:111111111', true, now()->addHours(24));

        // Contact 2: Outside 24h window
        $contactOutWindow = Contact::create(['tenant_id' => $tenant->id, 'phone_number' => '222222222']);
        $recipientOutWindow = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contactOutWindow->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending'
        ]);

        // Use Queue fake to assert the underlying delivery job was dispatched
        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\SendMsg91Message::class]);

        // Run the job synchronously
        $job = new SendCampaignJob($campaign->id);
        $job->handle(app(TenantResolverService::class), app(OutboundReplyService::class));

        $recipientInWindow->refresh();
        $recipientOutWindow->refresh();
        $campaign->refresh();

        $this->assertEquals('sent', $recipientInWindow->status);
        $this->assertEquals('skipped_24h', $recipientOutWindow->status);
        
        $this->assertEquals(1, $campaign->sent_count);
        $this->assertEquals(1, $campaign->failed_count);
        $this->assertEquals('completed', $campaign->status);

        // Assert the in-window contact had a message queued
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendMsg91Message::class, function ($job) {
            return str_contains(serialize($job), '111111111');
        });

        // Assert the out-of-window contact was correctly skipped
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SendMsg91Message::class, function ($job) {
            return str_contains(serialize($job), '222222222');
        });
    }

    public function test_it_sends_template_campaign_with_positional_variables_and_bypasses_24h_window()
    {
        $tenant = Tenant::factory()->create();
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        \Illuminate\Support\Facades\DB::table('tenant_numbers')->insert([
            'tenant_id' => $tenant->id,
            'integrated_number' => '15551234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantSetting::create(['tenant_id' => $tenant->id, 'msg91_auth_key' => 'mock_key']);

        $campaign = Campaign::create([
            'tenant_id' => $tenant->id,
            'name' => 'Template Promo',
            'message_type' => 'template',
            'template_name' => 'hello_world',
            'template_language' => 'en',
            'template_variable_map' => ['name', 'city'], // {{1}}, {{2}}
            'status' => 'draft',
            'target_type' => 'all',
            'total_recipients' => 1
        ]);

        // Contact with no 24h session, but it shouldn't matter for templates
        $contact = Contact::create([
            'tenant_id' => $tenant->id, 
            'phone_number' => '333333333',
            'name' => 'Alice',
            'custom_fields' => ['city' => 'Wonderland']
        ]);
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending'
        ]);

        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\SendMsg91Message::class]);

        $job = new SendCampaignJob($campaign->id);
        $job->handle(app(TenantResolverService::class), app(OutboundReplyService::class));

        $recipient->refresh();
        $campaign->refresh();

        $this->assertEquals('sent', $recipient->status);
        $this->assertEquals(1, $campaign->sent_count);
        $this->assertEquals(0, $campaign->failed_count);
        $this->assertEquals('completed', $campaign->status);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendMsg91Message::class, function ($job) {
            $payload = serialize($job);
            // Verify positional variable values made it into the payload
            return str_contains($payload, 'Alice') && str_contains($payload, 'Wonderland') && str_contains($payload, 'hello_world');
        });
    }

    public function test_it_hard_fails_template_campaign_if_positional_variable_is_missing()
    {
        $tenant = Tenant::factory()->create();
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        \Illuminate\Support\Facades\DB::table('tenant_numbers')->insert([
            'tenant_id' => $tenant->id,
            'integrated_number' => '15551234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantSetting::create(['tenant_id' => $tenant->id, 'msg91_auth_key' => 'mock_key']);

        $campaign = Campaign::create([
            'tenant_id' => $tenant->id,
            'name' => 'Faulty Promo',
            'message_type' => 'template',
            'template_name' => 'hello_world',
            'template_language' => 'en',
            'template_variable_map' => ['name', 'missing_field'], // {{2}} will fail
            'status' => 'draft',
            'target_type' => 'all',
            'total_recipients' => 1
        ]);

        $contact = Contact::create([
            'tenant_id' => $tenant->id, 
            'phone_number' => '444444444',
            'name' => 'Bob',
            // city is missing, so missing_field won't resolve
        ]);
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending'
        ]);

        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\SendMsg91Message::class]);

        $job = new SendCampaignJob($campaign->id);
        $job->handle(app(TenantResolverService::class), app(OutboundReplyService::class));

        $recipient->refresh();
        $campaign->refresh();

        $this->assertEquals('failed', $recipient->status);
        $this->assertStringContainsString('Missing required template variable(s): {{2}} (missing_field)', $recipient->failure_reason);
        
        $this->assertEquals(0, $campaign->sent_count);
        $this->assertEquals(1, $campaign->failed_count);
        $this->assertEquals('completed', $campaign->status);

        // Assert nothing was sent to MSG91
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SendMsg91Message::class);
    }

    public function test_it_aborts_if_campaign_is_cancelled()
    {
        $tenant = Tenant::factory()->create();
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        $campaign = Campaign::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cancelled Promo',
            'message_type' => 'text',
            'text_content' => 'Will not be sent',
            'status' => 'cancelled', // Setting to cancelled immediately
            'target_type' => 'all',
            'total_recipients' => 1
        ]);

        $contact = Contact::create(['tenant_id' => $tenant->id, 'phone_number' => '555555555']);
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending'
        ]);

        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\SendMsg91Message::class]);

        $job = new SendCampaignJob($campaign->id);
        $job->handle(app(TenantResolverService::class), app(OutboundReplyService::class));

        // Since it's cancelled, the recipient should still be pending (the loop never ran)
        $recipient->refresh();
        $this->assertEquals('pending', $recipient->status);

        // Nothing dispatched
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SendMsg91Message::class);
    }
}
