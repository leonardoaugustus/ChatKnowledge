<?php

use App\Actions\Agents\CreateAgent;
use App\Jobs\DeleteAgentVectorStore;
use App\Jobs\ProvisionAgentVectorStore;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Stores;

beforeEach(function () {
    fakeVectorStore();

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);
});

it('provisions a vector store on agent creation', function () {
    app(CreateAgent::class)->handle($this->organization, ['name' => 'Suporte']);

    Stores::assertCreated(fn (string $name) => str_contains($name, 'agent-'));
});

it('stores the returned vector_store_id', function () {
    $agent = app(CreateAgent::class)->handle($this->organization, ['name' => 'Suporte']);

    expect($agent->fresh()->vector_store_id)
        ->not->toBeNull()
        ->toStartWith('fake_store_');
});

it('gives each agent its own store', function () {
    $a = app(CreateAgent::class)->handle($this->organization, ['name' => 'Agente A']);
    $b = app(CreateAgent::class)->handle($this->organization, ['name' => 'Agente B']);

    expect($a->fresh()->vector_store_id)
        ->not->toBeNull()
        ->not->toBe($b->fresh()->vector_store_id);
});

it('deletes the store when the agent is deleted', function () {
    $agent = Agent::factory()->for($this->organization)->create([
        'vector_store_id' => 'fake_store_existing',
    ]);

    $agent->delete();

    Stores::assertDeleted('fake_store_existing');
});

it('does not delete a store when the agent has none', function () {
    Queue::fake();

    $agent = Agent::factory()->for($this->organization)->create(['vector_store_id' => null]);

    $agent->delete();

    Queue::assertNotPushed(DeleteAgentVectorStore::class);
});

it('queues provisioning rather than running it inline', function () {
    Queue::fake();

    app(CreateAgent::class)->handle($this->organization, ['name' => 'Async']);

    Queue::assertPushed(ProvisionAgentVectorStore::class);
});
