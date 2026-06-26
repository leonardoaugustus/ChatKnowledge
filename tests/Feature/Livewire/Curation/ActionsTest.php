<?php

use App\Enums\CurationStatus;
use App\Enums\PublicationStatus;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Models\User;
use Laravel\Ai\Stores;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->organization = $this->admin->currentOrganization;
    $this->actingAs($this->admin);

    $this->agent = Agent::factory()->for($this->organization)->create();

    $this->item = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'title' => 'Original title',
        'content' => 'Original content',
    ]);
});

it('edits an item', function () {
    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->call('edit', $this->item->id)
        ->set('editTitle', 'New title')
        ->set('editContent', 'New content')
        ->set('editSummary', 'New summary')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($this->item->fresh())
        ->title->toBe('New title')
        ->content->toBe('New content')
        ->summary->toBe('New summary');
});

it('approves an item', function () {
    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->call('approve', $this->item->id);

    expect($this->item->fresh())
        ->curation_status->toBe(CurationStatus::Approved)
        ->approved_by->toBe($this->admin->id)
        ->and($this->item->fresh()->approved_at)->not->toBeNull();
});

it('rejects an item', function () {
    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->call('reject', $this->item->id);

    expect($this->item->fresh()->curation_status)->toBe(CurationStatus::Rejected);
});

it('removes an item', function () {
    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->call('remove', $this->item->id);

    expect(KnowledgeItem::find($this->item->id))->toBeNull()
        ->and(KnowledgeItem::withTrashed()->find($this->item->id)->trashed())->toBeTrue();
});

it('only allows an Admin to curate', function () {
    $collaborator = User::factory()->create();
    $this->organization->members()->attach($collaborator, ['role' => Role::Colaborador->value]);
    $collaborator->switchOrganization($this->organization);

    $this->actingAs($collaborator);

    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->call('approve', $this->item->id)
        ->assertForbidden();

    expect($this->item->fresh()->curation_status)->toBe(CurationStatus::Pending);
});

it('does not push to the vector store on approval', function () {
    fakeVectorStore();

    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->call('approve', $this->item->id);

    expect($this->item->fresh())
        ->publication_status->toBe(PublicationStatus::Unpublished)
        ->vector_file_id->toBeNull()
        ->and($this->item->fresh()->published_at)->toBeNull();

    Stores::assertNothingCreated();
});
