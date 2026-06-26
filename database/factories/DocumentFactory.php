<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Agent;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory();
        $name = fake()->words(2, true);

        return [
            'organization_id' => $organization,
            'agent_id' => Agent::factory()->for($organization),
            'name' => $name.'.pdf',
            'format' => 'pdf',
            'status' => DocumentStatus::Uploaded,
            'disk' => 'local',
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'size' => fake()->numberBetween(1000, 500000),
            'version' => 1,
        ];
    }
}
