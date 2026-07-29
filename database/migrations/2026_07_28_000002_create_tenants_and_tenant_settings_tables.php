<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tenants Master Table
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed Default Tenant 1
        DB::table('tenants')->insert([
            'id'         => 1,
            'name'       => 'Default Organization',
            'slug'       => 'default',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fix PostgreSQL auto-increment sequence after explicit ID insert
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('tenants_id_seq', (SELECT MAX(id) FROM tenants));");
        }

        // 2. Tenant Settings Table (Encrypted Credentials at Rest)
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->onDelete('cascade');
            $table->text('msg91_auth_key')->nullable();
            $table->text('openai_api_key')->nullable();
            $table->string('flowise_endpoint')->nullable();
            $table->timestamps();
        });

        // 3. Tenant Numbers Table (Many-to-One: integrated_number -> tenant_id)
        Schema::create('tenant_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('integrated_number')->unique()->index();
            $table->timestamps();
        });

        // Seed Default Number for Tenant 1
        $defaultNumber = config('services.msg91.integrated_number') ?? '917425889008';
        DB::table('tenant_numbers')->insert([
            'tenant_id'         => 1,
            'integrated_number' => $defaultNumber,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_numbers');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenants');
    }
};
