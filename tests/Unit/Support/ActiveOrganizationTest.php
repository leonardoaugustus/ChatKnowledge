<?php

use App\Models\User;
use App\Support\ActiveOrganization;
use Tests\TestCase;

uses(TestCase::class);

it('resolves the active organization id from the user', function () {
    $user = new User;
    $user->current_organization_id = 42;

    $this->actingAs($user);

    expect(app(ActiveOrganization::class)->id())->toBe(42);
});

it('returns null when no user/organization', function () {
    expect(app(ActiveOrganization::class)->id())->toBeNull();
    expect(app(ActiveOrganization::class)->organization())->toBeNull();
});
