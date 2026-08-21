<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Modules\Analytics\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceOptimizationsCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_controller_index_includes_all_custom_field_keys()
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-perf',
            'features' => ['contacts_bulk_messaging' => true]
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner'
        ]);

        // Create 205 contacts. Contact 205 has a unique custom field key.
        for ($i = 1; $i <= 205; $i++) {
            $customFields = [];
            if ($i === 205) {
                $customFields = ['unique_special_field' => 'special_value'];
            }
            Contact::create([
                'tenant_id' => $tenant->id,
                'name' => "Contact {$i}",
                'phone_number' => "91900000" . sprintf("%04d", $i),
                'is_subscribed' => true,
                'custom_fields' => $customFields,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('contacts.index'));

        $response->assertStatus(200);

        // Retrieve Inertia page props
        $page = $response->original->getData()['page'];
        $availableFields = $page['props']['availableContactFields'] ?? [];

        // Verify that the custom field on contact 205 is NOT lost
        $this->assertContains('unique_special_field', $availableFields);
    }

    public function test_analytics_service_retains_interactive_and_button_reply_message_types()
    {
        $tenant = Tenant::factory()->create();
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_number' => '919876543210',
            'customer_name' => 'Test User',
            'last_message_at' => now(),
        ]);

        // Create messages with interactive and button_reply types in the database
        Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'content' => json_encode(['type' => 'interactive', 'text' => 'Menu options']),
            'status' => 'received',
            'created_at' => now(),
        ]);

        Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'content' => json_encode(['type' => 'button_reply', 'text' => 'Yes chosen']),
            'status' => 'received',
            'created_at' => now(),
        ]);

        $service = new AnalyticsService();
        $metrics = $service->getOverviewMetrics(
            now()->subDays(1)->toDateString(),
            now()->toDateString(),
            'UTC',
            $tenant->id
        );

        $typeBreakdown = $metrics['type_breakdown'] ?? [];

        // Assert that interactive and button_reply types are parsed and returned with count 1
        $this->assertArrayHasKey('interactive', $typeBreakdown);
        $this->assertArrayHasKey('button_reply', $typeBreakdown);
        $this->assertEquals(1, $typeBreakdown['interactive']);
        $this->assertEquals(1, $typeBreakdown['button_reply']);
    }
}
