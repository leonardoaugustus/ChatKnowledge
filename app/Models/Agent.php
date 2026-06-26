<?php

namespace App\Models;

use App\Enums\AgentStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Observers\AgentObserver;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property AgentStatus $status
 * @property string|null $vector_store_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read AgentConfig|null $config
 */
#[Fillable(['organization_id', 'name', 'status', 'vector_store_id'])]
#[ObservedBy([AgentObserver::class])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * Get the agent's personality configuration.
     *
     * @return HasOne<AgentConfig, $this>
     */
    public function config(): HasOne
    {
        return $this->hasOne(AgentConfig::class);
    }

    /**
     * Get the documents uploaded to train the agent.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the knowledge items extracted/curated for the agent.
     *
     * @return HasMany<KnowledgeItem, $this>
     */
    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
        ];
    }
}
