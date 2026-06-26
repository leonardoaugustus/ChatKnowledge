<?php

use App\Enums\PublicationStatus;
use App\Jobs\PublishKnowledgeItem;
use App\Models\Agent;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\KnowledgeItemVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ai\PublishingService;
use App\Services\Curation\CurationService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Contracts\Files\StorableFile;
use Laravel\Ai\Stores;

beforeEach(function () {
    fakeVectorStore();

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create(['vector_store_id' => 'vs_one']);
});

function publishedItem(array $overrides = []): KnowledgeItem
{
    return KnowledgeItem::factory()->for(test()->organization)->for(test()->agent)->approved()->create(array_merge([
        'content' => 'version one content',
        'publication_status' => PublicationStatus::Published,
        'vector_file_id' => 'vf_one',
        'version' => 1,
    ], $overrides));
}

it('creates a new version when a published item is edited', function () {
    Queue::fake();

    $item = publishedItem();

    app(CurationService::class)->update($item, ['content' => 'version two content']);

    expect($item->fresh()->version)->toBe(2)
        ->and($item->versions()->count())->toBe(1);
});

it('keeps the previous versions in history', function () {
    Queue::fake();

    $item = publishedItem(['content' => 'v1']);

    app(CurationService::class)->update($item, ['content' => 'v2']);
    app(CurationService::class)->update($item->fresh(), ['content' => 'v3']);

    $history = $item->versions()->orderBy('version')->get();

    expect($history)->toHaveCount(2)
        ->and($history->pluck('content')->all())->toBe(['v1', 'v2'])
        ->and($history->pluck('version')->all())->toBe([1, 2])
        ->and($item->fresh()->content)->toBe('v3')
        ->and($item->fresh()->version)->toBe(3);
});

it('republishes only the edited item', function () {
    Queue::fake();

    $item = publishedItem();
    publishedItem(['content' => 'another item', 'vector_file_id' => 'vf_other']);

    app(CurationService::class)->update($item, ['content' => 'edited']);

    Queue::assertPushed(PublishKnowledgeItem::class, 1);
    Queue::assertPushed(fn (PublishKnowledgeItem $job) => $job->item->is($item));
});

it('never re-sends the whole document to the vector store', function () {
    $document = Document::factory()->for($this->organization)->for($this->agent)->create(['name' => 'whole.pdf']);
    $item = publishedItem(['content' => 'only the item', 'source_document_id' => $document->id]);

    app(PublishingService::class)->republish($item);

    $store = Stores::get($this->agent->vector_store_id);
    $store->assertAdded(fn (StorableFile $file) => $file->content() === 'only the item');
    $store->assertNotAdded(fn (StorableFile $file) => str_contains($file->name() ?? '', 'whole.pdf'));
});

it('does not version an unpublished item on edit', function () {
    Queue::fake();

    $item = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'publication_status' => PublicationStatus::Unpublished,
        'version' => 1,
    ]);

    app(CurationService::class)->update($item, ['content' => 'edited']);

    expect($item->fresh()->version)->toBe(1)
        ->and($item->versions()->count())->toBe(0);
});

it('does not leak versions across organizations', function () {
    $other = Organization::factory()->create();
    $foreignVersion = KnowledgeItemVersion::factory()->for($other)->create();

    expect(KnowledgeItemVersion::find($foreignVersion->id))->toBeNull();
});
