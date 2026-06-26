<?php

use App\Enums\Role;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['auth', EnsureActiveOrganization::class])
        ->get('/_test/active-organization', fn () => response('ok'));
});

it('passes when membership is valid', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/_test/active-organization')
        ->assertOk()
        ->assertSee('ok');
});

it('falls back when current_organization_id is stale', function () {
    $user = User::factory()->create();
    $validOrganization = $user->currentOrganization;

    $staleOrganization = Organization::factory()->create();
    $user->update(['current_organization_id' => $staleOrganization->id]);

    $this->actingAs($user)
        ->get('/_test/active-organization')
        ->assertOk()
        ->assertSee('ok');

    expect($user->fresh()->current_organization_id)->toBe($validOrganization->id);
});

it('never proceeds with an organization the user does not belong to', function () {
    $user = User::factory()->create();
    $user->organizations()->detach();

    $staleOrganization = Organization::factory()->create();
    $user->update(['current_organization_id' => $staleOrganization->id]);

    $this->actingAs($user)
        ->get('/_test/active-organization')
        ->assertRedirect(route('home'));

    expect($user->fresh()->current_organization_id)->toBe($staleOrganization->id);
});

it('switches the user away from an organization they no longer belong to', function () {
    $user = User::factory()->create();
    $personalOrganization = $user->currentOrganization;

    $otherOrganization = Organization::factory()->create();
    $otherOrganization->members()->attach($user, ['role' => Role::Colaborador->value]);

    $user->organizations()->detach($personalOrganization);
    $user->update(['current_organization_id' => $personalOrganization->id]);

    $this->actingAs($user)
        ->get('/_test/active-organization')
        ->assertOk();

    expect($user->fresh()->current_organization_id)->toBe($otherOrganization->id);
});
