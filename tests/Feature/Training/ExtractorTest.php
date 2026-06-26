<?php

use App\Ai\Agents\KnowledgeExtractor;
use App\Enums\CurationStatus;
use App\Enums\DocumentStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Jobs\ExtractKnowledgeFromDocument;
use App\Models\Agent;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\Ai\KnowledgeExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create();

    Storage::disk('local')->put('documents/raw.txt', 'Some raw training material.');

    $this->document = Document::factory()->for($this->organization)->for($this->agent)->create([
        'disk' => 'local',
        'path' => 'documents/raw.txt',
        'status' => DocumentStatus::Uploaded,
    ]);
});

function fakeExtractorItems(array $items): void
{
    KnowledgeExtractor::fake([['items' => $items]]);
}

function runExtraction(Document $document): void
{
    app(KnowledgeExtractionService::class)->extract($document);
}

it('produces structured knowledge items from raw material', function () {
    fakeExtractorItems([
        ['type' => 'procedure', 'title' => 'Reset', 'content' => 'Steps', 'summary' => 'S', 'source_excerpt' => 'E', 'confidence' => 0.8],
        ['type' => 'faq', 'title' => 'Hours', 'content' => '9-5', 'summary' => 'S2', 'source_excerpt' => 'E2', 'confidence' => 0.6],
    ]);

    runExtraction($this->document);

    expect(KnowledgeItem::where('source_document_id', $this->document->id)->count())->toBe(2);
});

it('tags each item with a KnowledgeType', function () {
    fakeExtractorItems([
        ['type' => 'procedimento', 'title' => 'A', 'content' => 'x'],
        ['type' => 'rule', 'title' => 'B', 'content' => 'y'],
    ]);

    runExtraction($this->document);

    $items = KnowledgeItem::where('source_document_id', $this->document->id)->get();

    expect($items->pluck('type')->all())->toBe([KnowledgeType::Procedure, KnowledgeType::Rule]);
});

it('marks items Pending for curation', function () {
    fakeExtractorItems([['type' => 'faq', 'title' => 'A', 'content' => 'x']]);

    runExtraction($this->document);

    expect(KnowledgeItem::sole()->curation_status)->toBe(CurationStatus::Pending);
});

it('sets DocumentStatus to Extracted on success', function () {
    fakeExtractorItems([['type' => 'faq', 'title' => 'A', 'content' => 'x']]);

    runExtraction($this->document);

    expect($this->document->fresh()->status)->toBe(DocumentStatus::Extracted);
});

it('sets DocumentStatus to Failed on error', function () {
    KnowledgeExtractor::fake(fn () => throw new RuntimeException('boom'));

    expect(fn () => runExtraction($this->document))->toThrow(RuntimeException::class);

    expect($this->document->fresh()->status)->toBe(DocumentStatus::Failed)
        ->and(KnowledgeItem::count())->toBe(0);
});

it('runs extraction asynchronously when a document is uploaded', function () {
    Queue::fake();

    Livewire::test('pages::training.upload', ['agent' => $this->agent])
        ->set('file', UploadedFile::fake()->create('manual.pdf', 50))
        ->call('save')
        ->assertHasNoErrors();

    Queue::assertPushed(ExtractKnowledgeFromDocument::class);
});

it('populates title, content, summary, source_document_id and source_excerpt', function () {
    fakeExtractorItems([[
        'type' => 'faq',
        'title' => 'Business hours',
        'content' => 'We are open 9 to 5.',
        'summary' => 'Open 9-5.',
        'source_excerpt' => 'Our hours are 9 to 5.',
        'confidence' => 0.95,
    ]]);

    runExtraction($this->document);

    $item = KnowledgeItem::sole();

    expect($item->title)->toBe('Business hours')
        ->and($item->content)->toBe('We are open 9 to 5.')
        ->and($item->summary)->toBe('Open 9-5.')
        ->and($item->source_excerpt)->toBe('Our hours are 9 to 5.')
        ->and($item->source_document_id)->toBe($this->document->id)
        ->and($item->confidence_score)->toBe(0.95)
        ->and($item->agent_id)->toBe($this->agent->id);
});

it('sets an initial version and publication_status', function () {
    fakeExtractorItems([['type' => 'faq', 'title' => 'A', 'content' => 'x']]);

    runExtraction($this->document);

    $item = KnowledgeItem::sole();

    expect($item->version)->toBe(1)
        ->and($item->publication_status)->toBe(PublicationStatus::Unpublished);
});
