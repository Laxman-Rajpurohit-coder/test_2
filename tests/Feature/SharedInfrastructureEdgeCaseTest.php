<?php

namespace Tests\Feature;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BotResponderPipeline;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SharedInfrastructureEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_identify_tenant_middleware_survives_missing_impersonation_target()
    {
        $middleware = new IdentifyTenant();
        $resolver = app(TenantResolverService::class);
        
        session(['impersonating_tenant_id' => 999999]);
        
        $request = Request::create('/dashboard', 'GET');
        
        $middleware->handle($request, function ($req) {
            return response('OK');
        });
        
        $this->assertEquals(999999, $resolver->getActiveTenantId());
        // It successfully sets it without crashing (since it doesn't query the DB during resolution)
    }

    public function test_tenant_resolver_service_handles_unmapped_number_gracefully()
    {
        $resolver = app(TenantResolverService::class);
        
        $record = $resolver->getTenantNumberRecord('9999999999');
        $this->assertNull($record);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("SECURITY ABORT: Unmapped integrated number 9999999999 cannot be resolved to a tenant. Failing closed.");
        
        $resolver->getTenantIdByIntegratedNumber('9999999999');
    }

    public function test_tenant_resolver_service_throws_when_integrated_number_missing()
    {
        $tenant = Tenant::create([
            'name' => 'Valid Tenant',
            'slug' => 'valid-tenant-2',
            'status' => 'active'
        ]);
        $resolver = app(TenantResolverService::class);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("No integrated WhatsApp number found for Tenant ID {$tenant->id}");
        
        $resolver->getIntegratedNumber($tenant->id);
    }

    public function test_bot_responder_pipeline_returns_false_when_unhandled()
    {
        $pipeline = new BotResponderPipeline();
        
        $context = new InboundMessageContext(
            'msg_123',
            1,
            '1234567890',
            'Hello',
            'Test Customer',
            1
        );
        
        $result = $pipeline->process($context);
        
        $this->assertFalse($result);
    }
}
