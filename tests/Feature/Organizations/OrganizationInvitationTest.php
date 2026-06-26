<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('organization invitations can be created', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.invite-member-modal', ['organization' => $organization])
        ->set('inviteEmail', 'invited@example.com')
        ->set('inviteRole', Role::Colaborador->value)
        ->call('createInvitation')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('organization_invitations', [
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'role' => Role::Colaborador->value,
    ]);
});

test('organization invitations cannot be created by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);
    $organization->members()->attach($member, ['role' => Role::Colaborador->value]);

    $this->actingAs($member);

    Livewire::test('pages::organizations.invite-member-modal', ['organization' => $organization])
        ->set('inviteEmail', 'invited@example.com')
        ->set('inviteRole', Role::Colaborador->value)
        ->call('createInvitation')
        ->assertForbidden();
});

test('organization invitations can be cancelled by owner', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.cancel-invitation-modal', ['organization' => $organization])
        ->set('invitationCode', $invitation->code)
        ->call('cancelInvitation')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('organization_invitations', [
        'id' => $invitation->id,
    ]);
});

test('organization invitations can be accepted', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'role' => Role::Colaborador,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitedUser);

    $response = Livewire::test('pages::organizations.accept-invitation', [
        'invitation' => $invitation,
    ]);

    $response->assertRedirect(route('dashboard'));

    expect(session('organization-invitation-accepted'))->toBeTrue();

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    expect($invitedUser->fresh()->belongsToOrganization($organization))->toBeTrue();
});

test('accepted invitation toast is shown on the dashboard', function () {
    $user = User::factory()->create();

    session()->flash('organization-invitation-accepted', true);

    $this->actingAs($user);

    Livewire::test('pages::organizations.pending-invitations-modal')
        ->assertDispatched('toast-show');
});

test('pending invitations excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create(['name' => 'Expired Organization']);

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitedUser);

    Livewire::test('pages::organizations.pending-invitations-modal')
        ->assertDontSee('Expired Organization');

    $this->assertDatabaseHas('organization_invitations', [
        'id' => $invitation->id,
    ]);
});

test('organization invitations cannot be accepted by user that wasnt invited', function () {
    $owner = User::factory()->create();
    $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($uninvitedUser);

    $response = Livewire::test('pages::organizations.accept-invitation', [
        'invitation' => $invitation,
    ]);

    $response->assertHasErrors(['invitation']);

    expect($uninvitedUser->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('expired invitations cannot be accepted', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => Role::Admin->value]);

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitedUser);

    $response = Livewire::test('pages::organizations.accept-invitation', [
        'invitation' => $invitation,
    ]);

    $response->assertHasErrors(['invitation']);

    expect($invitedUser->fresh()->belongsToOrganization($organization))->toBeFalse();
});
