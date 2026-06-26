<?php

namespace App\Http\Responses\Concerns;

use App\Actions\Organizations\CreateOrganization;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentOrganization
{
    protected function redirectPathForCurrentOrganization(Request $request, string $redirect): string
    {
        $organization = $this->currentOrganization($request);

        URL::defaults(['current_organization' => $organization->slug]);

        return "/{$organization->slug}{$redirect}";
    }

    protected function currentOrganization(Request $request): Organization
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $organization = $user->currentOrganization;

        if (! $organization || ! $user->belongsToOrganization($organization)) {
            $organization = $user->personalOrganization() ?? $user->fallbackOrganization();
        }

        // Self-heal users who somehow have no organization (e.g. created outside
        // the onboarding flow) by provisioning their personal organization.
        $organization ??= app(CreateOrganization::class)->handle(
            $user,
            $user->name."'s Organization",
            isPersonal: true,
        );

        if (! $user->isCurrentOrganization($organization)) {
            $user->switchOrganization($organization);
        }

        return $organization;
    }
}
