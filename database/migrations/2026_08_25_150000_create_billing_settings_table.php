<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('rate_unit')->default(1000); // 1000 or 10000
            $table->string('currency', 10)->default('INR');
            $table->decimal('base_message_rate', 10, 4)->default(0.2500); // Per 1 msg rate or base
            $table->decimal('utility_template_rate', 10, 4)->default(0.3500);
            $table->decimal('marketing_template_rate', 10, 4)->default(0.7800);
            $table->decimal('authentication_template_rate', 10, 4)->default(0.3000);
            $table->decimal('service_message_rate', 10, 4)->default(0.2500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
