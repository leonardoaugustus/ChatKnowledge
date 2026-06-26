<?php

namespace App\Services\Billing;

use App\Models\Organization;
use Laravel\Cashier\Checkout;
use RuntimeException;

class SubscriptionService
{
    /**
     * The Cashier subscription type for the single fixed plan.
     */
    public const TYPE = 'default';

    /**
     * Start a Stripe Checkout session subscribing the organization to the fixed plan.
     */
    public function checkout(Organization $organization, string $successUrl, string $cancelUrl): Checkout
    {
        return $organization
            ->newSubscription(self::TYPE, $this->priceId())
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
    }

    /**
     * Determine if the organization is actively subscribed to the fixed plan.
     */
    public function subscribed(Organization $organization): bool
    {
        return $organization->subscribed(self::TYPE);
    }

    /**
     * Determine if the organization is on the configured fixed price.
     */
    public function onFixedPlan(Organization $organization): bool
    {
        return $organization->subscribedToPrice($this->priceId(), self::TYPE);
    }

    /**
     * Get the plan limits from configuration (admin-configurable, never hardcoded).
     *
     * @return array<string, int|null>
     */
    public function limits(): array
    {
        return config('plan.limits', []);
    }

    /**
     * Get a single plan limit by key. Null means unlimited.
     */
    public function limit(string $key): ?int
    {
        $value = config('plan.limits.'.$key);

        return $value === null ? null : (int) $value;
    }

    /**
     * Get the configured Stripe price id for the fixed plan.
     */
    protected function priceId(): string
    {
        return config('plan.price_id')
            ?? throw new RuntimeException('The fixed plan price id is not configured (plan.price_id).');
    }
}
