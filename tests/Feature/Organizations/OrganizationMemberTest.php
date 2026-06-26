<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

test('organization member role can be updated by owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);
    $organization->members()->attach($member, ['role' => Role::Colaborador->value]);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.edit', ['organization' => $organization])
        ->call('updateMember', $member->id, Role::Admin->value)
        ->assertHasNoErrors();

    expect($organization->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(Role::Admin->value);
});

test('organization member role cannot be updated by colaboradores', function () {
    $admin = User::factory()->create();
    $colaborador = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($admin, ['role' => Role::Admin->value]);
    $organization->members()->attach($colaborador, ['role' => Role::Colaborador->value]);
    $organization->members()->attach($member, ['role' => Role::Colaborador->value]);

    $this->actingAs($colaborador);

    Livewire::test('pages::organizations.edit', ['organization' => $organization])
        ->call('updateMember', $member->id, Role::Admin->value)
        ->assertForbidden();
});

test('organization member can be removed by owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);
    $organization->members()->attach($member, ['role' => Role::Colaborador->value]);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.remove-member-modal', ['organization' => $organization])
        ->set('memberId', $member->id)
        ->call('removeMember')
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('organization member cannot be removed by colaboradores', function () {
    $admin = User::factory()->create();
    $colaborador = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($admin, ['role' => Role::Admin->value]);
    $organization->members()->attach($colaborador, ['role' => Role::Colaborador->value]);
    $organization->members()->attach($member, ['role' => Role::Colaborador->value]);

    $this->actingAs($colaborador);

    Livewire::test('pages::organizations.remove-member-modal', ['organization' => $organization])
        ->set('memberId', $member->id)
        ->call('removeMember')
        ->assertForbidden();
});

test('removed members current organization is set to personal organization', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalOrganization = $member->personalOrganization();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);
    $organization->members()->attach($member, ['role' => Role::Colaborador->value]);

    $member->update(['current_organization_id' => $organization->id]);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.remove-member-modal', ['organization' => $organization])
        ->set('memberId', $member->id)
        ->call('removeMember')
        ->assertHasNoErrors();

    expect($member->fresh()->current_organization_id)->toEqual($personalOrganization->id);
});
