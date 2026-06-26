<?php

use App\Http\Middleware\EnsureSubscribed;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', EnsureSubscribed::class])
        ->get('/_test/gated', fn () => response('feature'));
});

function subscribeOrganization(User $user): void
{
    $user->currentOrganization->subscriptions()->create([
        'type' => SubscriptionService::TYPE,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => config('plan.price_id'),
        'quantity' => 1,
    ]);
}

it('blocks gated routes without an active subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/_test/gated')
        ->assertRedirect(route('dashboard'));
});

it('allows access while subscribed', function () {
    $user = User::factory()->create();
    subscribeOrganization($user);

    $this->actingAs($user)
        ->get('/_test/gated')
        ->assertOk()
        ->assertSee('feature');
});
