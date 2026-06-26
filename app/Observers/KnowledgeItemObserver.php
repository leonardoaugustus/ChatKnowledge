<?php

namespace App\Observers;

use App\Jobs\RemoveKnowledgeItemFromStore;
use App\Models\KnowledgeItem;

class KnowledgeItemObserver
{
    /**
     * Remove the item's file from the vector store when a published item is
     * deleted.
     */
    public function deleted(KnowledgeItem $knowledgeItem): void
    {
        if ($knowledgeItem->vector_file_id && $knowledgeItem->agent?->vector_store_id) {
            RemoveKnowledgeItemFromStore::dispatch(
                $knowledgeItem->agent->vector_store_id,
                $knowledgeItem->vector_file_id,
            );
        }
    }
}
