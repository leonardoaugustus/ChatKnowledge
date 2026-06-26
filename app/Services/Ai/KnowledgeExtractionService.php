<?php

namespace App\Services\Ai;

use App\Ai\Agents\KnowledgeExtractor;
use App\Enums\CurationStatus;
use App\Enums\DocumentStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Models\Document;
use App\Models\KnowledgeItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KnowledgeExtractionService
{
    /**
     * The user-facing message shown when extraction fails. Raw provider errors
     * are never surfaced to the user — only logged.
     */
    public const FAILURE_MESSAGE = 'Não foi possível processar este documento. Tente novamente em instantes.';

    /**
     * Run the extractor over the document's raw material and persist the
     * resulting knowledge items as Pending for curation. Updates the document
     * status to Extracted on success and Failed on error.
     */
    public function extract(Document $document): void
    {
        $document->update(['status' => DocumentStatus::Processing]);

        try {
            $raw = (string) Storage::disk($document->disk)->get($document->path);

            $response = (new KnowledgeExtractor)->prompt($raw);

            foreach (($response['items'] ?? []) as $item) {
                $this->createItem($document, $item);
            }

            $document->update(['status' => DocumentStatus::Extracted, 'error' => null]);
        } catch (Throwable $e) {
            Log::error('Knowledge extraction failed', [
                'document_id' => $document->id,
                'organization_id' => $document->organization_id,
                'code' => $e->getCode(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $document->update(['status' => DocumentStatus::Failed, 'error' => self::FAILURE_MESSAGE]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function createItem(Document $document, array $item): KnowledgeItem
    {
        return KnowledgeItem::create([
            'organization_id' => $document->organization_id,
            'agent_id' => $document->agent_id,
            'source_document_id' => $document->id,
            'type' => KnowledgeType::fromExtractor((string) ($item['type'] ?? '')) ?? KnowledgeType::Faq,
            'title' => $item['title'] ?? '',
            'content' => $item['content'] ?? '',
            'summary' => $item['summary'] ?? null,
            'source_excerpt' => $item['source_excerpt'] ?? null,
            'confidence_score' => isset($item['confidence']) ? (float) $item['confidence'] : null,
            'curation_status' => CurationStatus::Pending,
            'publication_status' => PublicationStatus::Unpublished,
            'version' => 1,
        ]);
    }
}
