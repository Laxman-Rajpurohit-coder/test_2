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
        Schema::table('conversations', function (Blueprint $table) {
            // Drop the old globally unique constraint on customer_number
            $table->dropUnique('conversations_customer_number_unique');
            
            // Add the new tenant-scoped unique constraint
            $table->unique(['tenant_id', 'customer_number'], 'conversations_tenant_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_tenant_customer_unique');
            $table->unique('customer_number', 'conversations_customer_number_unique');
        });
    }
};
