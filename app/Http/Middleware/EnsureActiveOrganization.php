<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOrganization
{
    /**
     * Ensure the authenticated user has a valid active organization.
     *
     * Validates membership of `current_organization_id` via the pivot. When it
     * is stale or missing, falls back to another organization the user belongs
     * to, or redirects to the organization-selection screen when none exists.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($this->belongsToActiveOrganization($user)) {
            return $next($request);
        }

        if ($fallback = $user->fallbackOrganization()) {
            $user->switchOrganization($fallback);

            return $next($request);
        }

        return redirect()->route('home');
    }

    /**
     * Determine if the user belongs to their active organization via the pivot.
     */
    protected function belongsToActiveOrganization(User $user): bool
    {
        if (! $user->current_organization_id) {
            return false;
        }

        return $user->organizations()
            ->where('organizations.id', $user->current_organization_id)
            ->exists();
    }
}
