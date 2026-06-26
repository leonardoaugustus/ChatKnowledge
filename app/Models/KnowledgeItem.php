<?php

namespace App\Models;

use App\Enums\CurationStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\KnowledgeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $agent_id
 * @property int|null $source_document_id
 * @property KnowledgeType $type
 * @property string $title
 * @property string $content
 * @property string|null $summary
 * @property string|null $source_excerpt
 * @property float|null $confidence_score
 * @property CurationStatus $curation_status
 * @property PublicationStatus $publication_status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $published_at
 * @property string|null $vector_file_id
 * @property array<string, mixed>|null $metadata
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read Agent $agent
 * @property-read Document|null $sourceDocument
 * @property-read User|null $approver
 */
#[Fillable([
    'organization_id', 'agent_id', 'source_document_id', 'type', 'title', 'content',
    'summary', 'source_excerpt', 'confidence_score', 'curation_status', 'publication_status',
    'approved_by', 'approved_at', 'published_at', 'vector_file_id', 'metadata', 'version',
])]
class KnowledgeItem extends Model
{
    /** @use HasFactory<KnowledgeItemFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * Get the agent that owns the knowledge item.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the source document the item was extracted from, if any.
     *
     * @return BelongsTo<Document, $this>
     */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    /**
     * Get the user who approved the item, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => KnowledgeType::class,
            'curation_status' => CurationStatus::class,
            'publication_status' => PublicationStatus::class,
            'confidence_score' => 'float',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
