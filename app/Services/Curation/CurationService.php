<?php

namespace App\Services\Curation;

use App\Enums\CurationStatus;
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
}
