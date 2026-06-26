<?php

namespace Database\Factories;

use App\Enums\CurationStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeItem>
 */
class KnowledgeItemFactory extends Factory
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
            'source_document_id' => null,
            'type' => fake()->randomElement(KnowledgeType::cases()),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'summary' => fake()->sentence(),
            'source_excerpt' => fake()->sentence(),
            'confidence_score' => fake()->randomFloat(3, 0, 1),
            'curation_status' => CurationStatus::Pending,
            'publication_status' => PublicationStatus::Unpublished,
            'version' => 1,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'curation_status' => CurationStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
