<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\BillingSetting;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantBalanceTransaction;
use App\Models\User;
use App\Services\TenantBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_admin_can_add_balance_with_payment_reference_and_audit_trail()
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
            'balance' => 0.00,
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.tenants.balance.add', $tenant->id), [
            'amount' => 500.00,
            'payment_reference' => 'UPI-TXN-998877',
            'notes' => 'Received via HDFC UPI QR',
            'auto_activate' => true,
        ]);

        $response->assertSessionHas('success');
        $tenant->refresh();

        $this->assertEquals(500.00, (float) $tenant->balance);
        $this->assertEquals('active', $tenant->status);

        $this->assertDatabaseHas('tenant_balance_transactions', [
            'tenant_id' => $tenant->id,
            'admin_user_id' => $admin->id,
            'type' => 'credit',
            'amount' => 500.00,
            'balance_after' => 500.00,
            'payment_reference' => 'UPI-TXN-998877',
        ]);
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
            'balance' => 100.00,
        ]);

        TenantBalanceTransaction::create([
            'tenant_id' => $tenant->id,
            'admin_user_id' => $admin->id,
            'type' => 'credit',
            'amount' => 100.00,
            'balance_after' => 100.00,
            'payment_reference' => 'REF123',
            'description' => 'Initial payment',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.tenants.transactions', $tenant->id));

        $response->assertOk();
        $response->assertJsonPath('tenant.balance', 100);
        $response->assertJsonCount(1, 'transactions');
        $response->assertJsonPath('transactions.0.payment_reference', 'REF123');
    }

    public function test_tenant_balance_service_deducts_and_suspends_when_balance_exhausted()
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'active',
            'balance' => 0.0005,
        ]);

        $this->assertTrue(TenantBalanceService::hasBalance($tenant));

        // Deduct 0.0010, which drives balance below zero
        $cost = TenantBalanceService::deductForMessage($tenant, 0.0010, null, 'msg_test_01');

        $this->assertEquals(0.0010, $cost);
        $tenant->refresh();

        $this->assertLessThanOrEqual(0, (float) $tenant->balance);
        $this->assertEquals('suspended', $tenant->status);
        $this->assertFalse(TenantBalanceService::hasBalance($tenant));

        $this->assertDatabaseHas('tenant_balance_transactions', [
            'tenant_id' => $tenant->id,
            'type' => 'debit',
            'amount' => 0.0010,
        ]);
    }

    public function test_outbound_chat_message_is_blocked_with_402_when_balance_is_zero()
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'active',
            'balance' => 0.00,
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
        $this->assertEquals('suspended', $tenant->status);
    }
}
