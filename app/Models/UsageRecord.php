<?php

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\UsageRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $agent_id
 * @property UsageType $type
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable(['organization_id', 'agent_id', 'type', 'quantity'])]
class UsageRecord extends Model
{
    /** @use HasFactory<UsageRecordFactory> */
    use BelongsToOrganization, HasFactory;

    // The agent() relationship is added in Phase 3, once the Agent model exists.

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UsageType::class,
            'quantity' => 'integer',
        ];
    }
}
