<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\AiBot\Services\AiBotService;
use Tests\TestCase;

class AiBotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AiBotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiBotService();
    }

    public function test_it_returns_null_if_ai_is_not_active()
    {
        $tenant = Tenant::create(['id' => 1, 'name' => 'T1', 'slug' => 't1']);
        $setting = TenantSetting::create([
            'tenant_id' => $tenant->id,
            'ai_is_active' => false,
        ]);

        $response = $this->service->generateResponse("Hello", $setting);
        $this->assertNull($response);
    }

    public function test_it_returns_null_if_openai_api_key_is_missing()
    {
        $tenant = Tenant::create(['id' => 1, 'name' => 'T1', 'slug' => 't1']);
        $setting = TenantSetting::create([
            'tenant_id' => $tenant->id,
            'ai_is_active' => true,
            'ai_provider' => 'openai',
            'openai_api_key' => null,
        ]);

        $response = $this->service->generateResponse("Hello", $setting);
        $this->assertNull($response);
    }

    public function test_it_returns_reply_when_confidence_is_high_enough()
    {
        $tenant = Tenant::create(['id' => 1, 'name' => 'T1', 'slug' => 't1']);
        $setting = TenantSetting::create([
            'tenant_id' => $tenant->id,
            'ai_is_active' => true,
            'ai_provider' => 'openai',
            'openai_api_key' => 'fake-key',
            'ai_confidence_threshold' => 0.70,
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['reply' => 'I am confident.', 'confidence' => 0.95])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->service->generateResponse("Hello", $setting);
        $this->assertEquals('I am confident.', $response);
    }

    public function test_it_returns_null_when_confidence_is_too_low_escalating()
    {
        $tenant = Tenant::create(['id' => 1, 'name' => 'T1', 'slug' => 't1']);
        $setting = TenantSetting::create([
            'tenant_id' => $tenant->id,
            'ai_is_active' => true,
            'ai_provider' => 'openai',
            'openai_api_key' => 'fake-key',
            'ai_confidence_threshold' => 0.70,
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['reply' => 'I am not sure.', 'confidence' => 0.30])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->service->generateResponse("Hello", $setting);
        $this->assertNull($response);
    }
}
