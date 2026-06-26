<?php

use App\Enums\Role;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);
});

it('lists the active organization\'s agents to chat with', function () {
    Agent::factory()->for($this->organization)->create(['name' => 'Suporte']);

    $other = Organization::factory()->create();
    Agent::factory()->for($other)->create(['name' => 'Foreign Agent']);

    Livewire::test('pages::chat.hub')
        ->assertSee('Suporte')
        ->assertDontSee('Foreign Agent');
});

it('is accessible to a collaborator', function () {
    $collaborator = User::factory()->create();
    $this->organization->members()->attach($collaborator, ['role' => Role::Colaborador->value]);
    $collaborator->switchOrganization($this->organization);

    Agent::factory()->for($this->organization)->create(['name' => 'Suporte']);

    $this->actingAs($collaborator);

    Livewire::test('pages::chat.hub')
        ->assertOk()
        ->assertSee('Suporte');
});

it('requires authentication', function () {
    auth()->logout();

    $this->get(route('chat.index', ['current_organization' => $this->organization->slug]))
        ->assertRedirect(route('login'));
});
