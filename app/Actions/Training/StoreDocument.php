<?php

namespace App\Actions\Training;

use App\Enums\DocumentStatus;
use App\Jobs\ExtractKnowledgeFromDocument;
use App\Models\Agent;
use App\Models\Document;
use Illuminate\Http\UploadedFile;

class StoreDocument
{
    /**
     * Store the raw uploaded material and create an Uploaded document for the
     * agent. Extraction happens later (Phase 4.2).
     */
    public function handle(Agent $agent, UploadedFile $file, string $disk = 'local'): Document
    {
        $path = $file->store('documents/'.$agent->id, $disk);

        $document = Document::create([
            'organization_id' => $agent->organization_id,
            'agent_id' => $agent->id,
            'name' => $file->getClientOriginalName(),
            'format' => strtolower($file->getClientOriginalExtension()),
            'status' => DocumentStatus::Uploaded,
            'disk' => $disk,
            'path' => $path,
            'size' => $file->getSize(),
            'version' => 1,
        ]);

        // Extraction runs asynchronously (Phase 4.2).
        ExtractKnowledgeFromDocument::dispatch($document);

        return $document;
    }
}
