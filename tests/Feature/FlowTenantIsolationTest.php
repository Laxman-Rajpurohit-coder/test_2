<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\FlowBuilder\Models\Flow;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_b_cannot_access_tenant_a_flow()
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantResolverService::class)->setActiveTenantId($tenantA->id);
        
        $flowA = Flow::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Tenant A Secret Flow',
            'description' => 'Top secret',
            'graph' => ['nodes' => [], 'edges' => []],
            'is_active' => true,
        ]);

        $this->assertEquals($tenantA->id, $flowA->tenant_id);

        $this->actingAs($userB);

        echo "\n[Test] Attempting cross-tenant access...\n";
        
        $responseGet = $this->get('/flows/' . $flowA->id);
        echo "GET /flows/" . $flowA->id . " -> Status: " . $responseGet->status() . "\n";
        $responseGet->assertStatus(404);

        $responsePut = $this->put('/flows/' . $flowA->id, [
            'name' => 'Hacked by Tenant B',
            'graph' => ['nodes' => [], 'edges' => []]
        ]);
        echo "PUT /flows/" . $flowA->id . " -> Status: " . $responsePut->status() . "\n";
        $responsePut->assertStatus(404);

        $responseDelete = $this->delete('/flows/' . $flowA->id);
        echo "DELETE /flows/" . $flowA->id . " -> Status: " . $responseDelete->status() . "\n";
        $responseDelete->assertStatus(404);

        echo "[Test] All cross-tenant endpoints correctly returned 404 Not Found.\n";
    }
}
