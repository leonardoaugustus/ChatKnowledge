<?php

use App\Models\User;

it('registers and lands authenticated with an organization', function () {
    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();

    $user = User::where('email', 'ada@example.com')->sole();

    expect($user->currentOrganization)->not->toBeNull()
        ->and($user->organizations)->toHaveCount(1);
});

it('logs in an existing user', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('requires authentication for app routes', function () {
    $organization = User::factory()->create()->currentOrganization;

    $this->get(route('dashboard', ['current_organization' => $organization->slug]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('provisions an organization for an org-less user on login', function () {
    $user = User::factory()->create();
    $user->organizations()->detach();
    $user->forceFill(['current_organization_id' => null])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirectContains('/dashboard')
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->current_organization_id)->not->toBeNull()
        ->and($user->organizations()->count())->toBe(1)
        ->and($user->currentOrganization)->not->toBeNull();
});
