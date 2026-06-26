<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Stores;

class RemoveKnowledgeItemFromStore implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $vectorStoreId, public string $vectorFileId) {}

    /**
     * Remove the knowledge item's file from the agent's vector store.
     */
    public function handle(): void
    {
        Stores::get($this->vectorStoreId)->remove($this->vectorFileId, deleteFile: true);
    }
}
