<?php

namespace App\Observers;

use App\Jobs\DeleteAgentVectorStore;
use App\Models\Agent;

class AgentObserver
{
    /**
     * Clean up the agent's dedicated vector store when the agent is deleted.
     */
    public function deleted(Agent $agent): void
    {
        if ($agent->vector_store_id) {
            DeleteAgentVectorStore::dispatch($agent->vector_store_id);
        }
    }
}
