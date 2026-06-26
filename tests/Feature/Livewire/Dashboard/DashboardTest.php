<?php

use App\Enums\CurationStatus;
use App\Enums\DocumentStatus;
use App\Enums\PublicationStatus;
use App\Enums\UsageType;
use App\Models\Agent;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);
    $this->agent = Agent::factory()->for($this->organization)->create(['name' => 'Suporte']);
});

it('shows counts scoped to the active organization', function () {
    Agent::factory()->for($this->organization)->count(2)->create();
    Document::factory()->for($this->organization)->for($this->agent)->count(3)->create();

    Livewire::test('pages::dashboard')
        ->assertSet('stats.agents', 3) // the beforeEach agent + 2
        ->assertSet('stats.documents', 3);
});

it('shows pending curation and unanswered counts', function () {
    KnowledgeItem::factory()->for($this->organization)->for($this->agent)->count(2)->create([
        'curation_status' => CurationStatus::Pending,
    ]);
    KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create([
        'curation_status' => CurationStatus::Pending,
        'metadata' => ['gap' => true],
    ]);

    Livewire::test('pages::dashboard')
        ->assertSet('stats.pending_curation', 3)
        ->assertSet('stats.unanswered', 1);
});

it('shows plan usage', function () {
    config()->set('plan.limits.questions', 100);

    UsageRecord::factory()->for($this->organization)->count(4)->create([
        'agent_id' => $this->agent->id,
        'type' => UsageType::Question,
        'quantity' => 1,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSet('stats.questions', 4)
        ->assertSee('4 / 100');
});

it('shows items pending publication and published documents', function () {
    KnowledgeItem::factory()->for($this->organization)->for($this->agent)->count(2)->create([
        'curation_status' => CurationStatus::Approved,
        'publication_status' => PublicationStatus::Unpublished,
    ]);
    Document::factory()->for($this->organization)->for($this->agent)->create([
        'status' => DocumentStatus::Published,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSet('stats.pending_publication', 2)
        ->assertSet('stats.published_documents', 1);
});

it('shows usage per agent', function () {
    $other = Agent::factory()->for($this->organization)->create(['name' => 'Vendas']);

    UsageRecord::factory()->for($this->organization)->count(5)->create(['agent_id' => $this->agent->id, 'quantity' => 1]);
    UsageRecord::factory()->for($this->organization)->count(2)->create(['agent_id' => $other->id, 'quantity' => 1]);

    $usage = collect(Livewire::test('pages::dashboard')->instance()->usagePerAgent());

    expect($usage->firstWhere('label', 'Suporte')['value'])->toBe(5)
        ->and($usage->firstWhere('label', 'Vendas')['value'])->toBe(2);
});

it('shows processing failures', function () {
    Document::factory()->for($this->organization)->for($this->agent)->count(2)->create([
        'status' => DocumentStatus::Failed,
    ]);

    Livewire::test('pages::dashboard')->assertSet('stats.processing_failures', 2);
});

it('never aggregates across organizations', function () {
    $other = Organization::factory()->create();
    $otherAgent = Agent::factory()->for($other)->create();
    Agent::factory()->for($other)->count(4)->create();
    Document::factory()->for($other)->for($otherAgent)->count(7)->create();
    KnowledgeItem::factory()->for($other)->for($otherAgent)->count(9)->create();

    Livewire::test('pages::dashboard')
        ->assertSet('stats.agents', 1)
        ->assertSet('stats.documents', 0)
        ->assertSet('stats.pending_curation', 0);
});
