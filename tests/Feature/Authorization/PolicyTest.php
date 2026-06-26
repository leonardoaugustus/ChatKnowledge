<?php

use App\Enums\Role;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Models\Organization;
use App\Models\User;

function memberOf(Organization $organization, Role $role): User
{
    $user = User::factory()->create();
    $organization->members()->attach($user, ['role' => $role->value]);
    $user->switchOrganization($organization);

    return $user->fresh();
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->agent = Agent::factory()->for($this->organization)->create();
    $this->item = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->create();
});

it('a collaborator cannot curate, manage agents, or change billing', function () {
    $collaborator = memberOf($this->organization, Role::Colaborador);

    expect($collaborator->can('curate', $this->item))->toBeFalse()
        ->and($collaborator->can('update', $this->agent))->toBeFalse()
        ->and($collaborator->can('delete', $this->agent))->toBeFalse()
        ->and($collaborator->can('train', $this->agent))->toBeFalse()
        ->and($collaborator->can('manageBilling', $this->organization))->toBeFalse();
});

it('a collaborator can chat', function () {
    $collaborator = memberOf($this->organization, Role::Colaborador);

    expect($collaborator->can('chat', $this->agent))->toBeTrue()
        ->and($collaborator->can('view', $this->agent))->toBeTrue();
});

it('an admin can do all of the above', function () {
    $admin = memberOf($this->organization, Role::Admin);

    expect($admin->can('curate', $this->item))->toBeTrue()
        ->and($admin->can('update', $this->agent))->toBeTrue()
        ->and($admin->can('delete', $this->agent))->toBeTrue()
        ->and($admin->can('train', $this->agent))->toBeTrue()
        ->and($admin->can('manageBilling', $this->organization))->toBeTrue()
        ->and($admin->can('chat', $this->agent))->toBeTrue();
});

it('enforces the agent policy on the builder and training pages', function () {
    $collaborator = memberOf($this->organization, Role::Colaborador);
    $this->actingAs($collaborator);

    Livewire\Livewire::test('pages::agent.edit', ['agent' => $this->agent])->assertForbidden();
    Livewire\Livewire::test('pages::training.upload', ['agent' => $this->agent])->assertForbidden();
});
