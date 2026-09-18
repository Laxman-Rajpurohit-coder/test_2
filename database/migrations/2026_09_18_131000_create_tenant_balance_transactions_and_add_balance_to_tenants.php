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
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('balance', 14, 4)->default(0.0000)->after('status');
        });

        Schema::create('tenant_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->decimal('amount', 14, 4);
            $table->string('type', 50); // topup, charge, adjustment, refund
            $table->string('description');
            $table->string('payment_reference')->nullable(); // UPI txn id, bank ref, invoice #
            $table->decimal('balance_after', 14, 4);
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_balance_transactions');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
