<?php

namespace Database\Factories;

use App\Enums\AiLogType;
use App\Models\AiLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiLog>
 */
class AiLogFactory extends Factory
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
            'type' => fake()->randomElement(AiLogType::cases()),
            'latency_ms' => fake()->numberBetween(50, 5000),
            'tokens' => fake()->numberBetween(0, 4000),
            'estimated_cost' => fake()->randomFloat(8, 0, 0.05),
            'error' => null,
        ];
    }
}
