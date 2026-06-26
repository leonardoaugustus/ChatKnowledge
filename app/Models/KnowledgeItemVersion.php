<?php

namespace App\Models;

use App\Enums\KnowledgeType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\KnowledgeItemVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_item_id
 * @property int $version
 * @property KnowledgeType $type
 * @property string $title
 * @property string $content
 * @property string|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read KnowledgeItem $knowledgeItem
 */
#[Fillable(['organization_id', 'knowledge_item_id', 'version', 'type', 'title', 'content', 'summary'])]
class KnowledgeItemVersion extends Model
{
    /** @use HasFactory<KnowledgeItemVersionFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Get the knowledge item this version belongs to.
     *
     * @return BelongsTo<KnowledgeItem, $this>
     */
    public function knowledgeItem(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class);
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
            'version' => 'integer',
        ];
    }
}
