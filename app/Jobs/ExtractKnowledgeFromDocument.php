<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Ai\KnowledgeExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractKnowledgeFromDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(public Document $document) {}

    /**
     * Run the extractor over the document's raw material.
     */
    public function handle(KnowledgeExtractionService $service): void
    {
        $service->extract($this->document);
    }
}
