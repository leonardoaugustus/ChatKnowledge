<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

test('organizations index page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.index'));

    $response->assertOk();
});

test('organizations can be created', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::organizations.index')
        ->set('name', 'Test Organization')
        ->call('createOrganization')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Test Organization',
        'is_personal' => false,
    ]);
});

test('organization slug uses next available suffix', function () {
    $user = User::factory()->create();

    Organization::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Organization::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Organization::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    $this->actingAs($user);

    Livewire::test('pages::organizations.index')
        ->set('name', 'Acme')
        ->call('createOrganization')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('organization edit page can be rendered', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($user, ['role' => Role::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.edit', $organization));

    $response->assertOk();
});

test('organizations can be updated by owners', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'Original Name']);

    $organization->members()->attach($user, ['role' => Role::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::organizations.edit', ['organization' => $organization])
        ->set('organizationName', 'Updated Name')
        ->call('updateOrganization')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => 'Updated Name',
    ]);
});

test('organizations cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Owner->value]);
    $organization->members()->attach($member, ['role' => Role::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::organizations.edit', ['organization' => $organization])
        ->set('organizationName', 'Updated Name')
        ->call('updateOrganization')
        ->assertForbidden();
});

test('organizations can be deleted by owners', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($user, ['role' => Role::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $organization])
        ->set('deleteName', $organization->name)
        ->call('deleteOrganization')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('organizations', [
        'id' => $organization->id,
    ]);
});

test('organization deletion requires name confirmation', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($user, ['role' => Role::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $organization])
        ->set('deleteName', 'Wrong Name')
        ->call('deleteOrganization')
        ->assertHasErrors(['deleteName']);

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'deleted_at' => null,
    ]);
});

test('deleting current organization switches to alphabetically first remaining organization', function () {
    $user = User::factory()->create(['name' => 'Mike']);

    $zuluOrganization = Organization::factory()->create(['name' => 'Zulu Organization']);
    $zuluOrganization->members()->attach($user, ['role' => Role::Owner->value]);

    $alphaOrganization = Organization::factory()->create(['name' => 'Alpha Organization']);
    $alphaOrganization->members()->attach($user, ['role' => Role::Owner->value]);

    $betaOrganization = Organization::factory()->create(['name' => 'Beta Organization']);
    $betaOrganization->members()->attach($user, ['role' => Role::Owner->value]);

    $user->update(['current_organization_id' => $zuluOrganization->id]);

    $this->actingAs($user);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $zuluOrganization])
        ->set('deleteName', $zuluOrganization->name)
        ->call('deleteOrganization')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('organizations', [
        'id' => $zuluOrganization->id,
    ]);

    expect($user->fresh()->current_organization_id)->toEqual($alphaOrganization->id);
});

test('deleting current organization falls back to personal organization when alphabetically first', function () {
    $user = User::factory()->create();
    $personalOrganization = $user->personalOrganization();
    $organization = Organization::factory()->create(['name' => 'Zulu Organization']);
    $organization->members()->attach($user, ['role' => Role::Owner->value]);

    $user->update(['current_organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $organization])
        ->set('deleteName', $organization->name)
        ->call('deleteOrganization')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('organizations', [
        'id' => $organization->id,
    ]);

    expect($user->fresh()->current_organization_id)->toEqual($personalOrganization->id);
});

test('deleting non current organization leaves current organization unchanged', function () {
    $user = User::factory()->create();
    $personalOrganization = $user->personalOrganization();
    $organization = Organization::factory()->create();
    $organization->members()->attach($user, ['role' => Role::Owner->value]);

    $user->update(['current_organization_id' => $personalOrganization->id]);

    $this->actingAs($user);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $organization])
        ->set('deleteName', $organization->name)
        ->call('deleteOrganization')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('organizations', [
        'id' => $organization->id,
    ]);

    expect($user->fresh()->current_organization_id)->toEqual($personalOrganization->id);
});

test('members can leave non personal organizations', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Owner->value]);
    $organization->members()->attach($member, ['role' => Role::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::organizations.index')
        ->call('leaveOrganization', $organization->id)
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('leaving current organization switches to alphabetically first remaining organization', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mike']);

    $zuluOrganization = Organization::factory()->create(['name' => 'Zulu Organization']);
    $zuluOrganization->members()->attach($owner, ['role' => Role::Owner->value]);
    $zuluOrganization->members()->attach($member, ['role' => Role::Member->value]);

    $alphaOrganization = Organization::factory()->create(['name' => 'Alpha Organization']);
    $alphaOrganization->members()->attach($member, ['role' => Role::Member->value]);

    $betaOrganization = Organization::factory()->create(['name' => 'Beta Organization']);
    $betaOrganization->members()->attach($member, ['role' => Role::Member->value]);

    $member->update(['current_organization_id' => $zuluOrganization->id]);

    $this->actingAs($member);

    Livewire::test('pages::organizations.index')
        ->call('leaveOrganization', $zuluOrganization->id)
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToOrganization($zuluOrganization))->toBeFalse();
    expect($member->fresh()->current_organization_id)->toEqual($alphaOrganization->id);
});

test('personal organizations cannot be left', function () {
    $user = User::factory()->create();
    $personalOrganization = $user->personalOrganization();

    $this->actingAs($user);

    Livewire::test('pages::organizations.index')
        ->call('leaveOrganization', $personalOrganization->id)
        ->assertForbidden();

    expect($user->fresh()->belongsToOrganization($personalOrganization))->toBeTrue();
});

test('organization owners cannot leave their organization', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Owner->value]);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.index')
        ->call('leaveOrganization', $organization->id)
        ->assertForbidden();

    expect($owner->fresh()->belongsToOrganization($organization))->toBeTrue();
});

test('users cannot leave organizations they dont belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::organizations.index')
        ->call('leaveOrganization', $organization->id)
        ->assertForbidden();
});

test('leave control is only rendered for leaveable organizations', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $leaveableOrganization = Organization::factory()->create();

    $leaveableOrganization->members()->attach($owner, ['role' => Role::Owner->value]);
    $leaveableOrganization->members()->attach($member, ['role' => Role::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::organizations.index')
        ->assertSeeHtml('data-test="organization-leave-button"');
});

test('leave control is not rendered for personal or owned organizations', function () {
    $user = User::factory()->create();
    $ownedOrganization = Organization::factory()->create();

    $ownedOrganization->members()->attach($user, ['role' => Role::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::organizations.index')
        ->assertDontSeeHtml('data-test="organization-leave-button"');
});

test('deleting organization switches other affected users to their personal organization', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $organization = Organization::factory()->create();
    $organization->members()->attach($owner, ['role' => Role::Owner->value]);
    $organization->members()->attach($member, ['role' => Role::Member->value]);

    $owner->update(['current_organization_id' => $organization->id]);
    $member->update(['current_organization_id' => $organization->id]);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $organization])
        ->set('deleteName', $organization->name)
        ->call('deleteOrganization')
        ->assertHasNoErrors();

    expect($member->fresh()->current_organization_id)->toEqual($member->personalOrganization()->id);
});

test('personal organizations cannot be deleted', function () {
    $user = User::factory()->create();

    $personalOrganization = $user->personalOrganization();

    $this->actingAs($user);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $personalOrganization])
        ->set('deleteName', $personalOrganization->name)
        ->call('deleteOrganization')
        ->assertForbidden();

    $this->assertDatabaseHas('organizations', [
        'id' => $personalOrganization->id,
        'deleted_at' => null,
    ]);
});

test('organizations cannot be deleted by non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Owner->value]);
    $organization->members()->attach($member, ['role' => Role::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::organizations.delete-organization-modal', ['organization' => $organization])
        ->set('deleteName', $organization->name)
        ->call('deleteOrganization')
        ->assertForbidden();
});

test('guests cannot access organizations', function () {
    $response = $this->get(route('organizations.index'));

    $response->assertRedirect(route('login'));
});