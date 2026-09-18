<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Upgrade tenants table with decoupled billing status and enforcement flag
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'billing_enabled')) {
                $table->boolean('billing_enabled')->default(false)->after('balance');
            }
            if (!Schema::hasColumn('tenants', 'billing_status')) {
                $table->string('billing_status', 32)->default('active')->after('billing_enabled');
            }
            if (!Schema::hasColumn('tenants', 'suspension_reason')) {
                $table->string('suspension_reason')->nullable()->after('status');
            }
        });

        // 2. Upgrade tenant_balance_transactions table with idempotency and audit metadata
        Schema::table('tenant_balance_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_balance_transactions', 'currency')) {
                $table->char('currency', 3)->default('INR')->after('amount');
            }
            if (!Schema::hasColumn('tenant_balance_transactions', 'reference_type')) {
                $table->string('reference_type', 64)->nullable()->after('payment_reference');
            }
            if (!Schema::hasColumn('tenant_balance_transactions', 'reference_id')) {
                $table->string('reference_id', 128)->nullable()->after('reference_type');
            }
            if (!Schema::hasColumn('tenant_balance_transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->unique()->after('reference_id');
            }
            if (!Schema::hasColumn('tenant_balance_transactions', 'metadata')) {
                $table->json('metadata')->nullable()->after('idempotency_key');
            }
            if (!Schema::hasColumn('tenant_balance_transactions', 'created_by_type')) {
                $table->string('created_by_type', 32)->nullable()->after('admin_user_id');
            }
            if (!Schema::hasColumn('tenant_balance_transactions', 'created_by_id')) {
                $table->unsignedBigInteger('created_by_id')->nullable()->after('created_by_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_balance_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'reference_type',
                'reference_id',
                'idempotency_key',
                'metadata',
                'created_by_type',
                'created_by_id',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'billing_enabled',
                'billing_status',
                'suspension_reason',
            ]);
        });
    }
};
