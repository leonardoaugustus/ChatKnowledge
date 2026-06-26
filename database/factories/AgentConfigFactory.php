<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConfig>
 */
class AgentConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'identity' => 'You are '.fake()->firstName().', a support specialist.',
            'soul' => 'Warm, concise and proactive.',
            'user' => 'Employees of the organization.',
            'bootstrap' => 'Greet the user and offer help.',
            'heartbeat' => 'Stay on topic and cite sources.',
            'tools' => 'Use file search to ground every answer.',
        ];
    }
}
