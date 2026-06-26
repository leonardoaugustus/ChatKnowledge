<?php

namespace App\Services\Ai;

use App\Ai\Agents\ChatAgent;
use App\Models\Agent;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolCall;

class ChatService
{
    /**
     * The exact message the agent must return when its knowledge base does not
     * contain enough information to answer.
     */
    public const NO_KNOWLEDGE_MESSAGE = 'Não encontrei essa informação na base de conhecimento deste agente.';

    /**
     * The user-facing message shown when the chat fails. Raw provider errors are
     * never surfaced to the user — only logged.
     */
    public const FAILURE_MESSAGE = 'Ocorreu um erro ao gerar a resposta. Tente novamente em instantes.';

    /**
     * Stream an answer from the agent over its own vector store (native
     * streaming — never synchronous).
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function answer(Agent $agent, string $message, array $history = []): StreamableAgentResponse
    {
        return (new ChatAgent($agent, $history))->stream($message);
    }

    /**
     * Derive a concise, natural conversation title from the first answer,
     * falling back to the question when the answer isn't usable (e.g. the
     * no-knowledge / failure message).
     */
    public function titleFromAnswer(string $answer, string $question): string
    {
        $answer = trim($answer);

        $source = ($answer === '' || in_array($answer, [self::NO_KNOWLEDGE_MESSAGE, self::FAILURE_MESSAGE], true))
            ? $question
            : $answer;

        $firstLine = trim((string) strtok($source, "\n"));
        $firstLine = trim((string) preg_replace('/[*_`#>]+/', '', $firstLine));

        return str($firstLine !== '' ? $firstLine : $question)->squish()->limit(48)->value();
    }

    /**
     * Map a streamed execution event to a human progress label, or null when
     * the event carries no execution progress worth surfacing.
     */
    public function progressLabel(StreamEvent $event): ?string
    {
        return match (true) {
            $event instanceof StreamStart => __('Consultando a base de conhecimento'),
            $event instanceof ProviderToolEvent => __('Buscando nos documentos (File Search)'),
            $event instanceof ToolCall => __('Usando ferramentas'),
            $event instanceof ReasoningStart => __('Analisando a pergunta'),
            $event instanceof TextStart => __('Gerando a resposta'),
            default => null,
        };
    }
}
