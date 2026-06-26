<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionService;
use App\Support\ActiveOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    /**
     * Gate organization-scoped feature routes behind an active subscription.
     *
     * The active organization must hold a valid subscription to the fixed plan.
     * When it does not, the user is sent back to the dashboard, where the
     * subscribe call-to-action lives. The dashboard and billing routes must NOT
     * be placed behind this middleware.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = app(ActiveOrganization::class)->organization();

        if ($organization && $organization->subscribed(SubscriptionService::TYPE)) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
