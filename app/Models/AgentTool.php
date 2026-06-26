<?php

namespace App\Models;

use App\Enums\HttpMethod;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\AgentToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $agent_id
 * @property string $name
 * @property string|null $description
 * @property string $endpoint
 * @property HttpMethod $method
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $auth
 * @property array<string, mixed>|null $input_schema
 * @property array<string, mixed>|null $output_schema
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Agent $agent
 */
#[Fillable([
    'organization_id', 'agent_id', 'name', 'description', 'endpoint', 'method',
    'headers', 'auth', 'input_schema', 'output_schema', 'enabled',
])]
class AgentTool extends Model
{
    /** @use HasFactory<AgentToolFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'headers' => 'array',
            'auth' => 'array',
            'input_schema' => 'array',
            'output_schema' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
