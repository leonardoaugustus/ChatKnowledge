<?php

namespace Database\Factories;

use App\Enums\KnowledgeType;
use App\Models\KnowledgeItem;
use App\Models\KnowledgeItemVersion;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeItemVersion>
 */
class KnowledgeItemVersionFactory extends Factory
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
            'knowledge_item_id' => KnowledgeItem::factory()->for($organization),
            'version' => 1,
            'type' => fake()->randomElement(KnowledgeType::cases()),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'summary' => fake()->sentence(),
        ];
    }
}
