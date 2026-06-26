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
