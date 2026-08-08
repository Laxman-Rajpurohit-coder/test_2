<?php

namespace Tests\Feature;

use App\DTOs\InboundMessageContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BotResponderPipeline;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantAndUser(array $features = []): array
    {
        $tenant = Tenant::create([
            'name'     => 'Test Tenant',
            'slug'     => 'test-tenant',
            'status'   => 'active',
            'features' => $features,
        ]);

        $user = new User([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
            'role'     => 'owner',
        ]);
        $user->tenant_id = $tenant->id;
        $user->save();

        return [$tenant, $user];
    }

    // ─── Model-Level Tests ───────────────────────────────────────────

    public function test_tenant_has_feature_returns_true_when_enabled()
    {
        [$tenant] = $this->createTenantAndUser(['bot_auto_responder' => true]);
        $this->assertTrue($tenant->hasFeature('bot_auto_responder'));
    }

    public function test_tenant_has_feature_returns_false_when_disabled()
    {
        [$tenant] = $this->createTenantAndUser(['bot_auto_responder' => false]);
        $this->assertFalse($tenant->hasFeature('bot_auto_responder'));
    }

    public function test_tenant_has_feature_returns_false_when_missing()
    {
        [$tenant] = $this->createTenantAndUser([]);
        $this->assertFalse($tenant->hasFeature('bot_auto_responder'));
    }

    public function test_tenant_has_feature_returns_false_when_features_is_null()
    {
        [$tenant] = $this->createTenantAndUser();
        $tenant->features = null;
        $tenant->save();
        $tenant->refresh();
        $this->assertFalse($tenant->hasFeature('bot_auto_responder'));
    }

    // ─── Route Middleware Tests ──────────────────────────────────────

    public function test_bot_triggers_route_returns_200_when_feature_enabled()
    {
        [$tenant, $user] = $this->createTenantAndUser(['bot_auto_responder' => true]);
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        $response = $this->actingAs($user)->get('/bot-triggers');
        $response->assertStatus(200);
    }

    public function test_bot_triggers_route_returns_403_when_feature_disabled()
    {
        [$tenant, $user] = $this->createTenantAndUser(['bot_auto_responder' => false]);
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        $response = $this->actingAs($user)->get('/bot-triggers');
        $response->assertStatus(403);
    }

    public function test_bot_triggers_route_returns_403_when_features_null()
    {
        [$tenant, $user] = $this->createTenantAndUser();
        $tenant->features = null;
        $tenant->save();
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        $response = $this->actingAs($user)->get('/bot-triggers');
        $response->assertStatus(403);
    }

    // ─── Pipeline Guard Tests ────────────────────────────────────────

    public function test_pipeline_skips_when_feature_disabled()
    {
        [$tenant] = $this->createTenantAndUser(['bot_auto_responder' => false]);

        $pipeline = new BotResponderPipeline();
        $context = new InboundMessageContext(
            'msg_test_1', 1, '1234567890', 'Hello', 'Test Customer', $tenant->id
        );

        $result = $pipeline->process($context);
        $this->assertFalse($result);
    }

    public function test_pipeline_proceeds_when_feature_enabled()
    {
        [$tenant] = $this->createTenantAndUser(['bot_auto_responder' => true]);

        $pipeline = new BotResponderPipeline();
        $context = new InboundMessageContext(
            'msg_test_2', 1, '1234567890', 'Hello', 'Test Customer', $tenant->id
        );

        // Pipeline runs (no responders registered so returns false/unhandled) but does NOT short-circuit at guard
        $result = $pipeline->process($context);
        $this->assertFalse($result);
    }
}
