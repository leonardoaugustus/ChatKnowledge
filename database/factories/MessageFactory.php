<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
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
            'conversation_id' => Conversation::factory()->for($organization),
            'role' => fake()->randomElement(MessageRole::cases()),
            'content' => fake()->paragraph(),
            'sources' => null,
        ];
    }
}
