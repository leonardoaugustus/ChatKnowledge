<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $agent_id
 * @property string $name
 * @property string $format
 * @property DocumentStatus $status
 * @property string $disk
 * @property string $path
 * @property int $size
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read Agent $agent
 */
#[Fillable(['organization_id', 'agent_id', 'name', 'format', 'status', 'disk', 'path', 'size', 'version'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * Get the agent that owns the document.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the knowledge items extracted from this document.
     *
     * @return HasMany<KnowledgeItem, $this>
     */
    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class, 'source_document_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'size' => 'integer',
            'version' => 'integer',
        ];
    }
}
