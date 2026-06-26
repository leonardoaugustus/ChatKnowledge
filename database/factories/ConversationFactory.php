<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
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
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
        ];
    }
}
