<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Laravel\Cashier\Billable;

function activeSubscriptionFor(Organization $organization, string $stripeId = 'sub_test_123'): void
{
    $organization->subscriptions()->create([
        'type' => SubscriptionService::TYPE,
        'stripe_id' => $stripeId,
        'stripe_status' => 'active',
        'stripe_price' => config('plan.price_id'),
        'quantity' => 1,
    ]);
}

it('subscribes the organization to the fixed plan', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    activeSubscriptionFor($organization);

    $service = app(SubscriptionService::class);

    expect($service->subscribed($organization->fresh()))->toBeTrue()
        ->and($service->onFixedPlan($organization->fresh()))->toBeTrue();
});

it('reflects subscription status from webhooks', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);
    activeSubscriptionFor($organization, 'sub_test_999');

    expect(app(SubscriptionService::class)->subscribed($organization->fresh()))->toBeTrue();

    $this->postJson('/stripe/webhook', [
        'id' => 'evt_test_1',
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_test_999',
                'customer' => 'cus_test_123',
            ],
        ],
    ])->assertOk();

    expect(app(SubscriptionService::class)->subscribed($organization->fresh()))->toBeFalse();

    $this->assertDatabaseHas('subscriptions', [
        'stripe_id' => 'sub_test_999',
        'stripe_status' => 'canceled',
    ]);
});

it('keeps the Billable on Organization not User', function () {
    expect(in_array(Billable::class, class_uses_recursive(Organization::class), true))->toBeTrue()
        ->and(in_array(Billable::class, class_uses_recursive(User::class), true))->toBeFalse();

    expect(method_exists(Organization::class, 'newSubscription'))->toBeTrue()
        ->and(method_exists(User::class, 'newSubscription'))->toBeFalse();
});

it('reads plan limits from configuration', function () {
    config()->set('plan.limits', [
        'users' => 25,
        'agents' => 7,
        'questions' => 5000,
        'documents' => null,
    ]);

    $service = app(SubscriptionService::class);

    expect($service->limit('agents'))->toBe(7)
        ->and($service->limit('users'))->toBe(25)
        ->and($service->limit('documents'))->toBeNull()
        ->and($service->limits())->toHaveKeys(['users', 'agents', 'questions', 'documents']);
});
