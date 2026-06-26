<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class KnowledgeExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the extractor should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
        You are a knowledge extraction agent. Given raw training material, break it
        down into discrete, self-contained knowledge items. Each item must be one of
        these types: procedure, rule, policy, faq, ideal_answer, exception, glossary,
        flow, operational_step.

        For every item provide: the type, a short title, the full content, a one-line
        summary, the verbatim source excerpt it came from, and a confidence score
        between 0 and 1. Do not invent information that is not present in the material.
        PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema) => [
                    'type' => $schema->string()->required(),
                    'title' => $schema->string()->required(),
                    'content' => $schema->string()->required(),
                    'summary' => $schema->string(),
                    'source_excerpt' => $schema->string(),
                    'confidence' => $schema->number(),
                ])
            )->required(),
        ];
    }
}
