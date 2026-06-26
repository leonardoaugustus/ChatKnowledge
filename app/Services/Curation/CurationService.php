<?php

namespace App\Services\Curation;

use App\Enums\CurationStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Jobs\PublishKnowledgeItem;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Models\KnowledgeItemVersion;
use App\Models\User;
use Illuminate\Support\Arr;

class CurationService
{
    /**
     * Edit / complement a knowledge item's editable fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(KnowledgeItem $item, array $attributes): KnowledgeItem
    {
        $isPublished = $item->publication_status === PublicationStatus::Published;

        // Editing an already-published item snapshots the current state into
        // history and bumps the version before applying the edit.
        if ($isPublished) {
            $this->snapshotVersion($item);
            $attributes['version'] = $item->version + 1;
        }

        $item->update(Arr::only($attributes, ['title', 'content', 'summary', 'version']));

        // Re-sync only that item to the vector store.
        if ($isPublished) {
            PublishKnowledgeItem::dispatch($item);
        }

        return $item;
    }

    /**
     * Record an unanswered chat question as a Pending curation gap for the
     * agent so a curator can fill the knowledge base. Identical questions are
     * deduplicated (the existing gap's asked_count is incremented instead).
     */
    public function recordGap(Agent $agent, string $question): KnowledgeItem
    {
        $hash = sha1(str($question)->squish()->lower()->value());

        $existing = $agent->knowledgeItems()
            ->where('curation_status', CurationStatus::Pending->value)
            ->where('metadata->gap', true)
            ->where('metadata->question_hash', $hash)
            ->first();

        if ($existing) {
            $metadata = $existing->metadata;
            $metadata['asked_count'] = ($metadata['asked_count'] ?? 1) + 1;
            $existing->update(['metadata' => $metadata]);

            return $existing;
        }

        return KnowledgeItem::create([
            'organization_id' => $agent->organization_id,
            'agent_id' => $agent->id,
            'source_document_id' => null,
            'type' => KnowledgeType::Faq,
            'title' => $question,
            'content' => 'Pergunta sem resposta na base de conhecimento: '.$question,
            'curation_status' => CurationStatus::Pending,
            'publication_status' => PublicationStatus::Unpublished,
            'version' => 1,
            'metadata' => [
                'gap' => true,
                'question' => $question,
                'question_hash' => $hash,
                'asked_count' => 1,
            ],
        ]);
    }

    /**
     * Snapshot the item's current state as a historical version.
     */
    protected function snapshotVersion(KnowledgeItem $item): void
    {
        KnowledgeItemVersion::create([
            'organization_id' => $item->organization_id,
            'knowledge_item_id' => $item->id,
            'version' => $item->version,
            'type' => $item->type,
            'title' => $item->title,
            'content' => $item->content,
            'summary' => $item->summary,
        ]);
    }

    /**
     * Flag a knowledge item as approved (ready for publishing). This does NOT
     * push anything to the vector store — publishing happens later (Phase 6).
     */
    public function approve(KnowledgeItem $item, User $approver): KnowledgeItem
    {
        $item->update([
            'curation_status' => CurationStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $item;
    }

    /**
     * Reject a knowledge item.
     */
    public function reject(KnowledgeItem $item): KnowledgeItem
    {
        $item->update(['curation_status' => CurationStatus::Rejected]);

        return $item;
    }

    /**
     * Remove a knowledge item.
     */
    public function remove(KnowledgeItem $item): void
    {
        $item->delete();
    }

    /**
     * Create a manual FAQ knowledge item directly in an approved state. It is
     * still explicitly approved (approved_by/at) but not yet published — the
     * vector store push happens later (Phase 6).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createManualFaq(Agent $agent, User $author, array $attributes): KnowledgeItem
    {
        return KnowledgeItem::create([
            'organization_id' => $agent->organization_id,
            'agent_id' => $agent->id,
            'source_document_id' => null,
            'type' => KnowledgeType::Faq,
            'title' => $attributes['title'],
            'content' => $attributes['content'],
            'summary' => $attributes['summary'] ?? null,
            'curation_status' => CurationStatus::Approved,
            'publication_status' => PublicationStatus::Unpublished,
            'approved_by' => $author->id,
            'approved_at' => now(),
            'version' => 1,
        ]);
    }
}
