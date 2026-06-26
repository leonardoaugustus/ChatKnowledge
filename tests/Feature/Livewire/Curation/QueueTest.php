<?php

use App\Enums\KnowledgeType;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create();
});

it('lists only pending items for the active organization', function () {
    $pending = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'title' => 'Pending item',
        'type' => KnowledgeType::Faq,
    ]);

    $approved = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->approved()->create([
        'title' => 'Approved item',
        'type' => KnowledgeType::Faq,
    ]);

    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->assertSee('Pending item')
        ->assertDontSee('Approved item');
});

it('groups items by KnowledgeType', function () {
    KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'title' => 'A procedure', 'type' => KnowledgeType::Procedure,
    ]);
    KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'title' => 'A policy', 'type' => KnowledgeType::Policy,
    ]);

    $grouped = Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->instance()
        ->groupedItems();

    expect($grouped->keys()->all())
        ->toContain(KnowledgeType::Procedure->value, KnowledgeType::Policy->value)
        ->and($grouped[KnowledgeType::Procedure->value])->toHaveCount(1);
});

it('does not show items from another organization', function () {
    $other = Organization::factory()->create();
    $foreignItem = KnowledgeItem::factory()->for($other)->create(['title' => 'Foreign item']);

    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->assertDontSee('Foreign item');

    expect(KnowledgeItem::find($foreignItem->id))->toBeNull();
});
