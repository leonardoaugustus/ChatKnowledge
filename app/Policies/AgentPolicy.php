<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    /**
     * Any member of the agent's organization may view it.
     */
    public function view(User $user, Agent $agent): bool
    {
        return $user->belongsToOrganization($agent->organization);
    }

    /**
     * Any member (Admin or Collaborator) may chat with the agent.
     */
    public function chat(User $user, Agent $agent): bool
    {
        return $user->belongsToOrganization($agent->organization);
    }

    /**
     * Only an Admin of the active organization may create agents.
     */
    public function create(User $user): bool
    {
        return $user->currentOrganization !== null
            && $user->ownsOrganization($user->currentOrganization);
    }

    /**
     * Only an Admin may manage (edit) the agent.
     */
    public function update(User $user, Agent $agent): bool
    {
        return $user->ownsOrganization($agent->organization);
    }

    /**
     * Only an Admin may delete the agent.
     */
    public function delete(User $user, Agent $agent): bool
    {
        return $this->update($user, $agent);
    }

    /**
     * Only an Admin may train the agent (upload documents).
     */
    public function train(User $user, Agent $agent): bool
    {
        return $this->update($user, $agent);
    }
}
