<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('invites a user by email with a role', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->members()->attach($admin, ['role' => Role::Admin->value]);

    $this->actingAs($admin);

    Livewire::test('pages::organizations.invite-member-modal', ['organization' => $organization])
        ->set('inviteEmail', 'invited@example.com')
        ->set('inviteRole', Role::Colaborador->value)
        ->call('createInvitation')
        ->assertHasNoErrors();

    $invitation = OrganizationInvitation::where('email', 'invited@example.com')->sole();

    expect($invitation->organization_id)->toBe($organization->id)
        ->and($invitation->role)->toBe(Role::Colaborador)
        ->and($invitation->invited_by)->toBe($admin->id);
});

it('attaches the user on acceptance', function () {
    $organization = Organization::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => $invited->email,
        'role' => Role::Colaborador,
        'invited_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($invited)
        ->get(route('invitations.accept', $invitation))
        ->assertRedirect(route('dashboard', absolute: false));

    expect($invited->belongsToOrganization($organization))->toBeTrue()
        ->and($invited->organizationRole($organization))->toBe(Role::Colaborador)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('only allows an Admin to invite', function () {
    $organization = Organization::factory()->create();
    $collaborator = User::factory()->create();
    $organization->members()->attach($collaborator, ['role' => Role::Colaborador->value]);

    $this->actingAs($collaborator);

    Livewire::test('pages::organizations.invite-member-modal', ['organization' => $organization])
        ->set('inviteEmail', 'invited@example.com')
        ->set('inviteRole', Role::Colaborador->value)
        ->call('createInvitation')
        ->assertForbidden();

    expect(OrganizationInvitation::where('email', 'invited@example.com')->exists())->toBeFalse();
});

it('scopes an invitation to a single organization', function () {
    $inviting = Organization::factory()->create();
    $other = Organization::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $inviting->id,
        'email' => $invited->email,
        'role' => Role::Admin,
        'invited_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($invited)->get(route('invitations.accept', $invitation));

    expect($invitation->organization->is($inviting))->toBeTrue()
        ->and($invited->belongsToOrganization($inviting))->toBeTrue()
        ->and($invited->belongsToOrganization($other))->toBeFalse();
});
