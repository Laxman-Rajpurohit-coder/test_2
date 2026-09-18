<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignJob;
use App\Models\AdminUser;
use App\Models\BillingSetting;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantBalanceTransaction;
use App\Models\User;
use App\Services\MessageBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantBalanceSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BillingSetting::create([
            'tenant_id' => null,
            'rate_unit' => 1000,
            'currency' => 'INR',
            'base_message_rate' => 0.25,
            'utility_template_rate' => 0.35,
            'marketing_template_rate' => 0.78,
            'authentication_template_rate' => 0.30,
            'service_message_rate' => 0.25,
        ]);
    }

    public function test_admin_can_add_balance_with_audit_trail_and_auto_activate()
    {
        $admin = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'suspended',
            'suspension_reason' => 'balance_exhausted',
            'billing_enabled' => true,
            'billing_status' => 'exhausted',
            'balance' => '0.0000',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.tenants.balance.add', $tenant->id), [
            'amount' => 500.00,
            'type' => 'topup',
            'payment_reference' => 'UPI-TXN-998877',
            'notes' => 'Received via HDFC UPI QR',
            'auto_activate' => true,
        ]);

        $response->assertSessionHas('success');
        $tenant->refresh();

        $this->assertEquals('500.0000', (string) $tenant->balance);
        $this->assertEquals('active', $tenant->status);
        $this->assertEquals('active', $tenant->billing_status);
        $this->assertNull($tenant->suspension_reason);

        $this->assertDatabaseHas('tenant_balance_transactions', [
            'tenant_id' => $tenant->id,
            'admin_user_id' => $admin->id,
            'type' => 'topup',
            'reference_type' => 'topup',
            'amount' => 500.00,
            'balance_after' => 500.00,
            'payment_reference' => 'UPI-TXN-998877',
        ]);
    }

    public function test_admin_can_enable_billing_on_balance_topup()
    {
        $admin = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $tenant = Tenant::create([
            'name' => 'Legacy Tenant',
            'slug' => 'legacy-tenant',
            'status' => 'active',
            'billing_enabled' => false,
            'balance' => '0.0000',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.tenants.balance.add', $tenant->id), [
            'amount' => 100.00,
            'type' => 'topup',
            'payment_reference' => 'MANUAL-001',
            'enable_billing' => true,
        ]);

        $response->assertSessionHas('success');
        $tenant->refresh();

        $this->assertTrue((bool) $tenant->billing_enabled);
        $this->assertEquals('100.0000', (string) $tenant->balance);
    }

    public function test_admin_can_fetch_tenant_transactions_ledger()
    {
        $admin = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'active',
            'billing_enabled' => true,
            'balance' => '100.0000',
        ]);

        TenantBalanceTransaction::create([
            'tenant_id' => $tenant->id,
            'admin_user_id' => $admin->id,
            'type' => 'topup',
            'reference_type' => 'topup',
            'amount' => '100.0000',
            'balance_after' => '100.0000',
            'payment_reference' => 'REF123',
            'description' => 'Initial recharge',
            'idempotency_key' => 'topup_REF123',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.tenants.transactions', $tenant->id));

        $response->assertOk();
        $response->assertJsonPath('tenant.balance', '100.0000');
        $response->assertJsonCount(1, 'transactions');
        $response->assertJsonPath('transactions.0.payment_reference', 'REF123');
        $response->assertJsonPath('transactions.0.idempotency_key', 'topup_REF123');
    }

    public function test_strict_prepaid_guarantee_never_allows_negative_balance()
    {
        $tenant = Tenant::create([
            'name' => 'Prepaid Tenant',
            'slug' => 'prepaid-tenant',
            'status' => 'active',
            'billing_enabled' => true,
            'billing_status' => 'active',
            'balance' => '0.0001', // insufficient for service message (0.00025)
        ]);

        // canSend should report insufficient
        $check = MessageBillingService::canSend($tenant, 'service');
        $this->assertFalse($check['allowed']);

        // Attempt charge
        $result = MessageBillingService::chargeForMessage(
            tenant: $tenant,
            type: 'service',
            category: null,
            referenceId: 'msg_999',
            referenceType: 'message',
            idempotencyKey: 'msg_999_charge'
        );

        $this->assertNull($result);
        $tenant->refresh();

        // Strict non-negative guarantee: Balance remains 0.0001, NOT negative!
        $this->assertEquals('0.0001', (string) $tenant->balance);
        $this->assertEquals('exhausted', $tenant->billing_status);
        // Portal access decoupled: administrative status is still active
        $this->assertEquals('active', $tenant->status);
    }

    public function test_idempotency_prevents_double_charging()
    {
        $tenant = Tenant::create([
            'name' => 'Idempotent Corp',
            'slug' => 'idempotent-corp',
            'status' => 'active',
            'billing_enabled' => true,
            'billing_status' => 'active',
            'balance' => '1.0000',
        ]);

        $idempotencyKey = 'msg_unique_12345_charge';

        // First charge
        $tx1 = MessageBillingService::chargeForMessage(
            tenant: $tenant,
            type: 'service',
            category: null,
            referenceId: 'msg_12345',
            referenceType: 'message',
            idempotencyKey: $idempotencyKey
        );

        $this->assertNotNull($tx1);
        $tenant->refresh();
        $balanceAfterFirst = (string) $tenant->balance;

        // Second charge attempt with identical idempotency key
        $tx2 = MessageBillingService::chargeForMessage(
            tenant: $tenant,
            type: 'service',
            category: null,
            referenceId: 'msg_12345',
            referenceType: 'message',
            idempotencyKey: $idempotencyKey
        );

        $this->assertNotNull($tx2);
        $this->assertEquals($tx1->id, $tx2->id);

        $tenant->refresh();
        // Balance must not have been deducted a second time
        $this->assertEquals($balanceAfterFirst, (string) $tenant->balance);
        $this->assertEquals(1, TenantBalanceTransaction::where('tenant_id', $tenant->id)->count());
    }

    public function test_unmetered_tenant_is_allowed_to_send_without_charge()
    {
        $tenant = Tenant::create([
            'name' => 'Unmetered Tenant',
            'slug' => 'unmetered-tenant',
            'status' => 'active',
            'billing_enabled' => false,
            'balance' => '0.0000',
        ]);

        $check = MessageBillingService::canSend($tenant, 'service');
        $this->assertTrue($check['allowed']);

        $tx = MessageBillingService::chargeForMessage(
            tenant: $tenant,
            type: 'service',
            category: null,
            referenceId: 'msg_unmetered_01'
        );

        $this->assertNull($tx);
        $tenant->refresh();
        $this->assertEquals('0.0000', (string) $tenant->balance);
        $this->assertEquals(0, TenantBalanceTransaction::where('tenant_id', $tenant->id)->count());
    }

    public function test_outbound_chat_message_is_blocked_with_402_when_balance_is_insufficient()
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'active',
            'billing_enabled' => true,
            'billing_status' => 'active',
            'balance' => '0.0000',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Acme User',
            'email' => 'user@acme.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'channel' => 'whatsapp',
            'customer_identifier' => '919876543210',
            'status' => 'open',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($user);

        // Attempting to send an outbound message when balance is 0
        $response = $this->postJson("/api/conversations/{$conversation->id}/messages", [
            'type' => 'text',
            'text' => 'Hello customer',
        ]);

        $response->assertStatus(402);
        $response->assertJson([
            'error' => 'Insufficient Balance',
        ]);

        $tenant->refresh();
        $this->assertEquals('exhausted', $tenant->billing_status);
        // Tenant is still active administratively so user is NOT locked out of portal
        $this->assertEquals('active', $tenant->status);
    }

    public function test_adding_balance_auto_resumes_paused_campaigns()
    {
        Queue::fake();

        $tenant = Tenant::create([
            'name' => 'Campaign Tenant',
            'slug' => 'campaign-tenant',
            'status' => 'active',
            'billing_enabled' => true,
            'billing_status' => 'exhausted',
            'balance' => '0.0000',
        ]);

        $campaign = Campaign::create([
            'tenant_id' => $tenant->id,
            'name' => 'Paused Black Friday Sale',
            'status' => 'paused_insufficient_balance',
            'message_type' => 'template',
            'template_name' => 'black_friday',
            'target_type' => 'all',
        ]);

        MessageBillingService::addBalance(
            tenant: $tenant,
            amount: '200.0000',
            type: 'topup',
            paymentRef: 'UPI-RECOVERY'
        );

        $campaign->refresh();
        $this->assertEquals('sending', $campaign->status);

        Queue::assertPushed(SendCampaignJob::class, function ($job) use ($campaign) {
            return $job->campaignId === $campaign->id;
        });
    }
}
