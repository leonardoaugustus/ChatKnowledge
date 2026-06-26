<?php

namespace App\Actions\Agents;

use App\Enums\AgentStatus;
use App\Exceptions\AgentLimitReached;
use App\Models\Agent;
use App\Models\Organization;
use App\Services\Ai\SystemPromptCompiler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateAgent
{
    /**
     * Create an agent (and its personality config) for the given organization,
     * enforcing the plan's agent cap when one is configured.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AgentLimitReached
     */
    public function handle(Organization $organization, array $attributes): Agent
    {
        $this->ensureWithinLimit($organization);

        return DB::transaction(function () use ($organization, $attributes) {
            $agent = Agent::create([
                'organization_id' => $organization->id,
                'name' => $attributes['name'],
                'status' => $attributes['status'] ?? AgentStatus::Draft,
                'vector_store_id' => $attributes['vector_store_id'] ?? null,
            ]);

            $agent->config()->create(
                Arr::only($attributes, array_keys(SystemPromptCompiler::SECTIONS))
            );

            return $agent;
        });
    }

    /**
     * @throws AgentLimitReached
     */
    protected function ensureWithinLimit(Organization $organization): void
    {
        $limit = config('plan.limits.agents');

        if ($limit === null) {
            return;
        }

        $current = Agent::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->count();

        if ($current >= (int) $limit) {
            throw AgentLimitReached::forLimit((int) $limit);
        }
    }
}
