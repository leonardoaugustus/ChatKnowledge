<?php

use App\Enums\Role;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);
});

it('lists the active organization\'s agents', function () {
    Agent::factory()->for($this->organization)->create(['name' => 'Suporte']);

    $other = Organization::factory()->create();
    Agent::factory()->for($other)->create(['name' => 'Foreign Agent']);

    Livewire::test('pages::agents.index')
        ->assertSee('Suporte')
        ->assertDontSee('Foreign Agent');
});

it('lets an admin create an agent and redirects to its builder', function () {
    Livewire::test('pages::agents.index')
        ->set('name', 'Vendas')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Agent::where('name', 'Vendas')->where('organization_id', $this->organization->id)->exists())->toBeTrue();
});

it('forbids a collaborator from creating an agent', function () {
    $collaborator = User::factory()->create();
    $this->organization->members()->attach($collaborator, ['role' => Role::Colaborador->value]);
    $collaborator->switchOrganization($this->organization);

    $this->actingAs($collaborator);

    Livewire::test('pages::agents.index')
        ->set('name', 'Nope')
        ->call('create')
        ->assertForbidden();

    expect(Agent::where('name', 'Nope')->exists())->toBeFalse();
});

it('requires authentication', function () {
    auth()->logout();

    $this->get(route('agents.index', ['current_organization' => $this->organization->slug]))
        ->assertRedirect(route('login'));
});
