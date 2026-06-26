<?php

namespace App\Models;

use App\Enums\AiLogType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\AiLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $agent_id
 * @property AiLogType $type
 * @property int $latency_ms
 * @property int $tokens
 * @property float $estimated_cost
 * @property string|null $error
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable(['organization_id', 'agent_id', 'type', 'latency_ms', 'tokens', 'estimated_cost', 'error', 'metadata'])]
class AiLog extends Model
{
    /** @use HasFactory<AiLogFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AiLogType::class,
            'latency_ms' => 'integer',
            'tokens' => 'integer',
            'estimated_cost' => 'float',
            'metadata' => 'array',
        ];
    }
}
