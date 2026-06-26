<?php

use App\Enums\CurationStatus;
use App\Enums\KnowledgeType;
use App\Enums\PublicationStatus;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->organization = $this->admin->currentOrganization;
    $this->actingAs($this->admin);

    $this->agent = Agent::factory()->for($this->organization)->create();
});

it('creates a manual FAQ item', function () {
    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->set('faqForm.title', 'What are the business hours?')
        ->set('faqForm.content', 'We are open from 9 to 5.')
        ->set('faqForm.summary', 'Open 9-5.')
        ->call('createManualFaq')
        ->assertHasNoErrors();

    $item = KnowledgeItem::sole();

    expect($item)
        ->type->toBe(KnowledgeType::Faq)
        ->curation_status->toBe(CurationStatus::Approved)
        ->publication_status->toBe(PublicationStatus::Unpublished)
        ->title->toBe('What are the business hours?')
        ->approved_by->toBe($this->admin->id)
        ->and($item->approved_at)->not->toBeNull();
});

it('validates required fields', function () {
    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->call('createManualFaq')
        ->assertHasErrors(['faqForm.title', 'faqForm.content']);

    expect(KnowledgeItem::count())->toBe(0);
});

it('scopes the manual FAQ to the agent and organization', function () {
    Livewire::test('pages::curation.queue', ['agent' => $this->agent])
        ->set('faqForm.title', 'Q')
        ->set('faqForm.content', 'A')
        ->call('createManualFaq')
        ->assertHasNoErrors();

    $item = KnowledgeItem::sole();

    expect($item->agent_id)->toBe($this->agent->id)
        ->and($item->organization_id)->toBe($this->organization->id);

    // Not visible from another organization.
    $other = User::factory()->create();
    $otherOrg = $other->currentOrganization;
    $this->actingAs($other);

    expect(KnowledgeItem::count())->toBe(0)
        ->and($otherOrg->id)->not->toBe($this->organization->id);
});
