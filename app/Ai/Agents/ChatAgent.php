<?php

namespace App\Ai\Agents;

use App\Models\Agent as BusinessAgent;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\FileSearch;

class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function __construct(
        public BusinessAgent $agent,
        public array $history = [],
    ) {}

    /**
     * The agent's persona/rules come from its compiled system prompt.
     */
    public function instructions(): string
    {
        return (string) ($this->agent->config?->compiled_system_prompt ?? '');
    }

    /**
     * Prior conversation messages.
     */
    public function messages(): iterable
    {
        return collect($this->history)
            ->map(fn (array $message) => new Message($message['role'], $message['content']))
            ->all();
    }

    /**
     * RAG over the agent's own dedicated vector store only.
     *
     * @return array<int, FileSearch>
     */
    public function tools(): iterable
    {
        if (! $this->agent->vector_store_id) {
            return [];
        }

        return [
            new FileSearch(stores: [$this->agent->vector_store_id]),
        ];
    }
}
