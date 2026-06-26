<?php

namespace App\Jobs;

use App\Models\Agent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Stores;

class ProvisionAgentVectorStore implements ShouldQueue
{
    use Queueable;

    public function __construct(public Agent $agent) {}

    /**
     * Provision a dedicated OpenAI vector store for the agent and store its id.
     */
    public function handle(): void
    {
        if ($this->agent->vector_store_id) {
            return;
        }

        $store = Stores::create($this->storeName());

        $this->agent->update(['vector_store_id' => $store->id]);
    }

    /**
     * A name unique to this agent so each agent gets its own store.
     */
    protected function storeName(): string
    {
        return "agent-{$this->agent->id}-org-{$this->agent->organization_id}";
    }
}
