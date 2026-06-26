<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\KnowledgeItem;
use App\Models\User;

class KnowledgeItemPolicy
{
    /**
     * Only an Admin of the item's organization may curate it.
     */
    public function curate(User $user, KnowledgeItem $knowledgeItem): bool
    {
        return $user->organizationRole($knowledgeItem->organization) === Role::Admin;
    }

    /**
     * Editing / complementing requires curation rights.
     */
    public function update(User $user, KnowledgeItem $knowledgeItem): bool
    {
        return $this->curate($user, $knowledgeItem);
    }

    /**
     * Removing requires curation rights.
     */
    public function delete(User $user, KnowledgeItem $knowledgeItem): bool
    {
        return $this->curate($user, $knowledgeItem);
    }
}
