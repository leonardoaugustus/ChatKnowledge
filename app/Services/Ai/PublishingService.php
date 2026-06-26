<?php

namespace App\Services\Ai;

use App\Enums\AiLogType;
use App\Enums\CurationStatus;
use App\Enums\PublicationStatus;
use App\Jobs\PublishKnowledgeItem;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use Laravel\Ai\Files\Document as VectorFile;
use Laravel\Ai\Stores;

class PublishingService
{
    public function __construct(private AiAuditLogger $auditLogger) {}

    /**
     * Push a single approved knowledge item to its agent's vector store.
     *
     * Publishing is incremental: only this item's content is synced, never the
     * whole source document. Pending/rejected items are ignored. When the item
     * was already published, its previous file is removed first so an edit
     * re-syncs only that item.
     */
    public function publish(KnowledgeItem $item): void
    {
        if ($item->curation_status !== CurationStatus::Approved) {
            return;
        }

        $agent = $item->agent;

        if (! $agent?->vector_store_id) {
            return;
        }

        $startedAt = microtime(true);

        $store = Stores::get($agent->vector_store_id);

        if ($item->vector_file_id) {
            $store->remove($item->vector_file_id);
        }

        $file = VectorFile::fromString($item->content, 'text/plain')
            ->as("knowledge-item-{$item->id}.txt");

        $response = $store->add($file, $this->metadataFor($item));

        $item->update([
            'vector_file_id' => $response->id,
            'publication_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $this->auditLogger->record(
            AiLogType::Publishing,
            $item->organization_id,
            $item->agent_id,
            (int) round((microtime(true) - $startedAt) * 1000),
            metadata: ['knowledge_item_id' => $item->id],
        );
    }

    /**
     * Re-sync an already-published item after an edit (removes the old file and
     * pushes the new content — only that item).
     */
    public function republish(KnowledgeItem $item): void
    {
        $this->publish($item);
    }

    /**
     * Dispatch a publish job for each approved, not-yet-published item of the
     * agent — one job per item (incremental).
     */
    public function publishApproved(Agent $agent): void
    {
        $agent->knowledgeItems()
            ->where('curation_status', CurationStatus::Approved->value)
            ->where('publication_status', PublicationStatus::Unpublished->value)
            ->get()
            ->each(fn (KnowledgeItem $item) => PublishKnowledgeItem::dispatch($item));
    }

    /**
     * The metadata attached to the item's file in the vector store.
     *
     * @return array<string, mixed>
     */
    public function metadataFor(KnowledgeItem $item): array
    {
        return [
            'knowledge_type' => $item->type->value,
            'source' => $item->sourceDocument?->name ?? 'manual',
            'approved_by' => $item->approved_by,
            'knowledge_item_id' => $item->id,
        ];
    }
}
