<?php

namespace App\Services\Ai;

use App\Enums\AiLogType;
use App\Models\AiLog;

class AiAuditLogger
{
    /**
     * Rough USD cost estimate per token (auditing/debugging only).
     */
    private const COST_PER_TOKEN = 0.000002;

    /**
     * Record an AI audit log capturing latency, tokens, estimated cost and any
     * error for an extraction / publishing / chat / tool-execution event.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        AiLogType $type,
        int $organizationId,
        ?int $agentId,
        int $latencyMs,
        int $tokens = 0,
        ?string $error = null,
        array $metadata = [],
    ): AiLog {
        return AiLog::create([
            'organization_id' => $organizationId,
            'agent_id' => $agentId,
            'type' => $type,
            'latency_ms' => max(0, $latencyMs),
            'tokens' => max(0, $tokens),
            'estimated_cost' => round(max(0, $tokens) * self::COST_PER_TOKEN, 8),
            'error' => $error,
            'metadata' => $metadata ?: null,
        ]);
    }
}
