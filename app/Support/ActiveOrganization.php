<?php

namespace App\Support;

use App\Models\Organization;

class ActiveOrganization
{
    /**
     * Get the id of the authenticated user's active organization.
     */
    public function id(): ?int
    {
        return auth()->user()?->current_organization_id;
    }

    /**
     * Get the authenticated user's active organization.
     */
    public function organization(): ?Organization
    {
        return auth()->user()?->currentOrganization;
    }
}
