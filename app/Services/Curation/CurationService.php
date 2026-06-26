<?php

namespace App\Services\Curation;

use App\Enums\CurationStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Models\Agent;
use App\Models\KnowledgeItem;
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
        $item->update(Arr::only($attributes, ['title', 'content', 'summary']));

        return $item;
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
