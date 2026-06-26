<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'status' => AgentStatus::Draft,
            'vector_store_id' => null,
        ];
    }

    /**
     * Indicate that the agent is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AgentStatus::Published,
            'vector_store_id' => 'vs_'.fake()->unique()->lexify('????????'),
        ]);
    }

    /**
     * Give the agent a personality config.
     */
    public function withConfig(): static
    {
        return $this->has(AgentConfig::factory(), 'config');
    }
}
