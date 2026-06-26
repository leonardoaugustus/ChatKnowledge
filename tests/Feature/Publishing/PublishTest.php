<?php

use App\Enums\CurationStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Jobs\PublishKnowledgeItem;
use App\Models\Agent;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\Ai\PublishingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Ai\Contracts\Files\StorableFile;
use Laravel\Ai\Stores;

beforeEach(function () {
    fakeVectorStore();

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create([
        'vector_store_id' => 'vs_agent_one',
    ]);
});

function approvedItem(array $overrides = []): KnowledgeItem
{
    return KnowledgeItem::factory()
        ->for(test()->organization)
        ->for(test()->agent)
        ->approved()
        ->create(array_merge([
            'content' => 'approved content',
            'publication_status' => PublicationStatus::Unpublished,
            'approved_by' => test()->user->id,
        ], $overrides));
}

it('pushes only approved items', function () {
    Queue::fake();

    $approved = approvedItem();
    KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'curation_status' => CurationStatus::Pending,
    ]);
    KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'curation_status' => CurationStatus::Rejected,
    ]);

    app(PublishingService::class)->publishApproved($this->agent);

    Queue::assertPushed(PublishKnowledgeItem::class, 1);
    Queue::assertPushed(fn (PublishKnowledgeItem $job) => $job->item->is($approved));
});

it('never pushes pending or rejected items', function () {
    $pending = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'curation_status' => CurationStatus::Pending,
        'content' => 'pending content',
    ]);

    app(PublishingService::class)->publish($pending);

    expect($pending->fresh())
        ->publication_status->toBe(PublicationStatus::Unpublished)
        ->vector_file_id->toBeNull();

    Stores::get($this->agent->vector_store_id)
        ->assertNotAdded(fn (StorableFile $file) => $file->content() === 'pending content');
});

it('attaches knowledge_type and source metadata', function () {
    $document = Document::factory()->for($this->organization)->for($this->agent)->create(['name' => 'handbook.pdf']);
    $item = approvedItem(['type' => KnowledgeType::Policy, 'source_document_id' => $document->id]);

    $metadata = app(PublishingService::class)->metadataFor($item);

    expect($metadata)
        ->toMatchArray([
            'knowledge_type' => KnowledgeType::Policy->value,
            'source' => 'handbook.pdf',
            'approved_by' => $this->user->id,
        ]);
});

it('targets the agent\'s own vector store', function () {
    $item = approvedItem();

    app(PublishingService::class)->publish($item);

    Stores::get('vs_agent_one')->assertAdded(fn (StorableFile $file) => $file->content() === 'approved content');
});

it('publishes incrementally, one item at a time', function () {
    Queue::fake();

    approvedItem(['content' => 'first']);
    approvedItem(['content' => 'second']);

    app(PublishingService::class)->publishApproved($this->agent);

    Queue::assertPushed(PublishKnowledgeItem::class, 2);
});

it('never re-sends the whole document', function () {
    $document = Document::factory()->for($this->organization)->for($this->agent)->create(['name' => 'whole.pdf']);
    $item = approvedItem(['content' => 'just this item', 'source_document_id' => $document->id]);

    app(PublishingService::class)->publish($item);

    $store = Stores::get($this->agent->vector_store_id);
    $store->assertAdded(fn (StorableFile $file) => $file->content() === 'just this item');
    $store->assertNotAdded(fn (StorableFile $file) => str_contains($file->name() ?? '', 'whole.pdf'));
});

it('marks the item published and is async', function () {
    $item = approvedItem();

    app(PublishingService::class)->publish($item);

    expect($item->fresh())
        ->publication_status->toBe(PublicationStatus::Published)
        ->vector_file_id->not->toBeNull()
        ->and($item->fresh()->published_at)->not->toBeNull();

    expect(is_subclass_of(PublishKnowledgeItem::class, ShouldQueue::class))->toBeTrue();
});
