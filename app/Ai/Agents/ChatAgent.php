<?php

namespace App\Ai\Agents;

use App\Ai\Tools\HttpToolBridge;
use App\Models\Agent as BusinessAgent;
use App\Models\AgentTool;
use App\Services\Ai\ChatService;
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
     * The agent's persona/rules (compiled system prompt) plus a mandatory
     * anti-hallucination guardrail constraining answers to the vector store.
     */
    public function instructions(): string
    {
        $persona = trim((string) ($this->agent->config?->compiled_system_prompt ?? ''));

        $guardrail = 'Responda EXCLUSIVAMENTE com base nas informações encontradas na base de '
            .'conhecimento (File Search) deste agente. Sempre cite a fonte utilizada. Se a base não '
            .'contiver informação suficiente para responder, responda EXATAMENTE com a frase: "'
            .ChatService::NO_KNOWLEDGE_MESSAGE.'". Nunca invente informações nem utilize '
            .'conhecimento externo à base de conhecimento deste agente.';

        return trim($persona."\n\n".$guardrail);
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
     * RAG over the agent's own dedicated vector store, plus its configured,
     * enabled HTTP tools.
     *
     * @return array<int, FileSearch|HttpToolBridge>
     */
    public function tools(): iterable
    {
        $tools = [];

        if ($this->agent->vector_store_id) {
            $tools[] = new FileSearch(stores: [$this->agent->vector_store_id]);
        }

        $this->agent->agentTools()
            ->where('enabled', true)
            ->get()
            ->each(function (AgentTool $tool) use (&$tools) {
                $tools[] = new HttpToolBridge($tool, $this->agent);
            });

        return $tools;
    }
}
