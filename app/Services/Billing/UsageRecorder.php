<?php

namespace App\Services\Billing;

use App\Enums\UsageType;
use App\Models\Organization;
use App\Models\UsageRecord;

class UsageRecorder
{
    /**
     * Record a unit of consumption for the given organization.
     *
     * Measurement only — there is no overage billing in V1. Called on each AI
     * question/extraction once those features exist (Phases 4 and 7).
     */
    public function record(Organization $organization, UsageType $type, int $quantity = 1, ?int $agentId = null): UsageRecord
    {
        return UsageRecord::create([
            'organization_id' => $organization->id,
            'agent_id' => $agentId,
            'type' => $type,
            'quantity' => $quantity,
        ]);
    }
}
