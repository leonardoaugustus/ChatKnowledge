<?php

namespace Database\Factories;

use App\Enums\HttpMethod;
use App\Models\Agent;
use App\Models\AgentTool;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentTool>
 */
class AgentToolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'agent_id' => Agent::factory()->for($organization),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'endpoint' => fake()->url(),
            'method' => HttpMethod::Get,
            'headers' => ['Accept' => 'application/json'],
            'auth' => ['type' => 'bearer', 'token' => fake()->sha256()],
            'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
            'output_schema' => ['type' => 'object'],
            'enabled' => true,
        ];
    }
}
