<?php

namespace App\Jobs;

use App\Jobs\Concerns\RetriesAiFailures;
use App\Models\KnowledgeItem;
use App\Services\Ai\PublishingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishKnowledgeItem implements ShouldQueue
{
    use Queueable, RetriesAiFailures;

    public function __construct(public KnowledgeItem $item) {}

    /**
     * Push the approved knowledge item to its agent's vector store.
     */
    public function handle(PublishingService $service): void
    {
        $service->publish($this->item);
    }
}
