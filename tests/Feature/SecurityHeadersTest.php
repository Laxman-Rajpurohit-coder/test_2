<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_requests_do_not_send_clear_site_data_header()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        TenantNumber::factory()->create(['tenant_id' => $tenant->id]);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        // 1. Authenticated Dashboard request
        $dashResponse = $this->actingAs($user)->get('/dashboard');
        $dashResponse->assertStatus(200);
        $this->assertFalse($dashResponse->headers->has('Clear-Site-Data'), 'Dashboard response must NOT contain Clear-Site-Data');
        
        $cacheControl = $dashResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);

        // 2. Authenticated API request
        $apiResponse = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp');
        $apiResponse->assertStatus(200);
        $this->assertFalse($apiResponse->headers->has('Clear-Site-Data'), 'API response must NOT contain Clear-Site-Data');
    }

    public function test_guest_requests_do_not_send_clear_site_data_header()
    {
        $welcomeResponse = $this->get('/');
        $welcomeResponse->assertStatus(200);
        $this->assertFalse($welcomeResponse->headers->has('Clear-Site-Data'));

        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);
        $this->assertFalse($loginResponse->headers->has('Clear-Site-Data'));
    }

    public function test_logout_specifically_sends_clear_site_data_cache_header()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $logoutResponse = $this->actingAs($user)->post('/logout');
        $logoutResponse->assertRedirect('/');
        $this->assertEquals('"cache"', $logoutResponse->headers->get('Clear-Site-Data'));
    }

    public function test_csp_and_security_headers_are_present_with_exact_directives()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        // Assert Modern Security Headers
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertEquals('camera=(), microphone=(self), geolocation=()', $response->headers->get('Permissions-Policy'));

        // Assert CSP Directives
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'Content-Security-Policy header must be present');

        // Required directives
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("font-src 'self' https://fonts.bunny.net data:", $csp);
        $this->assertStringContainsString("img-src 'self' data: blob: https:", $csp);
        $this->assertStringContainsString("media-src 'self' data: blob: https:", $csp);
        $this->assertStringContainsString("connect-src 'self' ws: wss:", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);

        // Forbidden directives / unneeded origins
        $this->assertStringNotContainsString("'unsafe-eval'", $csp, "CSP must NOT contain unsafe-eval");
        $this->assertStringNotContainsString("msg91.com", $csp, "CSP must NOT contain msg91.com in connect-src");
        $this->assertStringNotContainsString("railway.app", $csp, "CSP must NOT contain railway.app in connect-src");
    }
}
