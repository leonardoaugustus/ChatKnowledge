<?php

use App\Actions\Organizations\CreateOrganization;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

it('creates an organization and attaches the creator as Admin', function () {
    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle($user, 'Acme Inc');

    expect($organization->name)->toBe('Acme Inc')
        ->and($organization->members)->toHaveCount(1)
        ->and($user->organizationRole($organization))->toBe(Role::Admin)
        ->and($user->fresh()->current_organization_id)->toBe($organization->id);
});

it('relates users and organizations many-to-many with a role', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $collaborator = User::factory()->create();

    $organization->members()->attach($admin, ['role' => Role::Admin->value]);
    $organization->members()->attach($collaborator, ['role' => Role::Colaborador->value]);

    expect($organization->members)->toHaveCount(2)
        ->and($admin->organizationRole($organization))->toBe(Role::Admin)
        ->and($collaborator->organizationRole($organization))->toBe(Role::Colaborador);

    $pivot = Membership::where('organization_id', $organization->id)
        ->where('user_id', $collaborator->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->role)->toBe(Role::Colaborador);
});

it('lets a user belong to multiple organizations', function () {
    $user = User::factory()->create();
    $personal = $user->currentOrganization;

    $second = Organization::factory()->create();
    $third = Organization::factory()->create();

    $second->members()->attach($user, ['role' => Role::Admin->value]);
    $third->members()->attach($user, ['role' => Role::Colaborador->value]);

    expect($user->organizations()->pluck('organizations.id'))
        ->toContain($personal->id, $second->id, $third->id)
        ->and($user->organizations)->toHaveCount(3);
});
