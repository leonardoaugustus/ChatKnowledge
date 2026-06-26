<?php

use App\Enums\Role;
use App\Models\User;

it('creates a default organization on first registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->organizations)->toHaveCount(1);

    $organization = $user->organizations->first();

    expect($organization->is_personal)->toBeTrue()
        ->and($user->organizationRole($organization))->toBe(Role::Admin);
});

it('sets current_organization_id to the new organization', function () {
    $this->post(route('register.store'), [
        'name' => 'John Roe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'john@example.com')->sole();

    expect($user->current_organization_id)
        ->not->toBeNull()
        ->toBe($user->organizations->first()->id);
});
