<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tenant_number_id' => \App\Models\TenantNumber::factory(),
            'customer_number' => $this->faker->e164PhoneNumber,
            'customer_name' => $this->faker->name,
            'last_message_at' => now(),
            'is_human_escalated' => false,
            'ai_fallback_count' => 0,
            'unread_count' => 0,
        ];
    }
}
