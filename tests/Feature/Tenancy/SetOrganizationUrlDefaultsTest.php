<?php

use App\Http\Middleware\SetOrganizationUrlDefaults;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

function runUrlDefaults(User $user): void
{
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn () => $user);

    app(SetOrganizationUrlDefaults::class)->handle($request, fn () => new Response('ok'));
}

it('sets the current organization as the url default', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    URL::defaults(['current_organization' => 'stale']);
    runUrlDefaults($user->fresh());

    expect(route('dashboard', absolute: false))->toBe("/{$organization->slug}/dashboard");
});

it('falls back to a valid organization when the active one is unresolved', function () {
    $user = User::factory()->create();
    $organization = $user->currentOrganization;

    // Active organization is unset (e.g. removed / soft-deleted) — this is what
    // produced UrlGenerationException for org-scoped routes like the sidebar.
    $user->forceFill(['current_organization_id' => null])->save();

    URL::defaults(['current_organization' => 'stale']);
    runUrlDefaults($user->fresh());

    expect(route('dashboard', absolute: false))->toBe("/{$organization->slug}/dashboard");
});

it('keeps the url default working when the active org points to one the user left', function () {
    $user = User::factory()->create();
    $personal = $user->currentOrganization;

    // Pointed at an organization the user does not belong to.
    $foreign = Organization::factory()->create();
    $user->forceFill(['current_organization_id' => $foreign->id])->save();

    URL::defaults(['current_organization' => 'stale']);
    runUrlDefaults($user->fresh());

    expect(route('dashboard', absolute: false))->toBe("/{$personal->slug}/dashboard");
});
