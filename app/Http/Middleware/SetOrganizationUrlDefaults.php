<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationUrlDefaults
{
    /**
     * Set the default URL parameters for organization-based routes.
     *
     * Falls back to any organization the user belongs to when their active one
     * is unset or no longer resolvable, so organization-scoped routes can always
     * be generated for an authenticated user (avoiding UrlGenerationException).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (($user = $request->user()) && ($organization = $this->resolveOrganization($user))) {
            URL::defaults([
                'current_organization' => $organization->slug,
                'organization' => $organization->slug,
            ]);
        }

        return $next($request);
    }

    /**
     * Resolve a usable organization for URL generation.
     */
    protected function resolveOrganization(User $user): ?Organization
    {
        $organization = $user->currentOrganization;

        if ($organization && $user->belongsToOrganization($organization)) {
            return $organization;
        }

        return $user->fallbackOrganization();
    }
}
