<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Stores;

class DeleteAgentVectorStore implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $vectorStoreId) {}

    /**
     * Delete the agent's dedicated OpenAI vector store.
     */
    public function handle(): void
    {
        Stores::delete($this->vectorStoreId);
    }
}
