<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_contact_profile_with_timeline_and_tags()
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'test-tenant',
            'features' => ['contacts_bulk_messaging' => true]
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner'
        ]);

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'name' => 'John Doe Profile',
            'phone_number' => '919876543210',
            'is_subscribed' => true,
            'custom_fields' => ['City' => 'Mumbai', 'Plan' => 'Enterprise']
        ]);

        $tag = ContactTag::create([
            'tenant_id' => $tenant->id,
            'name' => 'VIP Customer'
        ]);
        $contact->contactTags()->attach($tag->id);

        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_number' => '919876543210',
            'customer_name' => 'John Doe Profile',
            'last_message_at' => now(),
        ]);

        WhatsappMessage::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'content' => json_encode(['text' => 'Hello interested in pricing']),
            'status' => 'delivered'
        ]);

        $response = $this->actingAs($user)
            ->get(route('contacts.show', $contact->id));

        $response->assertStatus(200);
    }
}
