<?php

namespace Database\Factories;

use App\Enums\UsageType;
use App\Models\Organization;
use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'agent_id' => null,
            'type' => fake()->randomElement(UsageType::cases()),
            'quantity' => 1,
        ];
    }
}
