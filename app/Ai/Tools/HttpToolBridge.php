<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\AgentTool;
use App\Services\Tools\HttpToolExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class HttpToolBridge implements Tool
{
    public function __construct(
        public AgentTool $tool,
        public Agent $agent,
    ) {}

    public function description(): Stringable|string
    {
        return $this->tool->description ?: $this->tool->name;
    }

    /**
     * Build the tool's input schema from the stored JSON Schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = data_get($this->tool->input_schema, 'properties', []);
        $required = data_get($this->tool->input_schema, 'required', []);

        $definition = [];

        foreach ($properties as $key => $property) {
            $type = match ($property['type'] ?? 'string') {
                'integer' => $schema->integer(),
                'number' => $schema->number(),
                'boolean' => $schema->boolean(),
                default => $schema->string(),
            };

            $definition[$key] = in_array($key, $required, true) ? $type->required() : $type;
        }

        return $definition;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->run($request->toArray());
    }

    /**
     * Execute the tool, surfacing errors as a message instead of crashing the
     * chat. Refuses to run a tool that does not belong to the agent.
     *
     * @param  array<string, mixed>  $input
     */
    public function run(array $input): string
    {
        if (! $this->belongsToAgent()) {
            return __('Ferramenta indisponível para este agente.');
        }

        try {
            return (string) json_encode(
                app(HttpToolExecutor::class)->execute($this->tool, $input),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (Throwable $e) {
            return 'Erro ao executar a ferramenta: '.$e->getMessage();
        }
    }

    /**
     * Cross-tenant / cross-agent safety: the tool must belong to this agent.
     */
    protected function belongsToAgent(): bool
    {
        return $this->tool->agent_id === $this->agent->id
            && $this->tool->organization_id === $this->agent->organization_id;
    }
}
