<?php

namespace App\Livewire\Forms;

use App\Enums\HttpMethod;
use App\Models\Agent;
use App\Models\AgentTool;
use Livewire\Attributes\Validate;
use Livewire\Form;

class AgentToolForm extends Form
{
    public ?int $toolId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|url')]
    public string $endpoint = '';

    #[Validate('required|string')]
    public string $method = HttpMethod::Get->value;

    #[Validate('nullable|json')]
    public string $headers = '';

    #[Validate('nullable|json')]
    public string $auth = '';

    #[Validate('nullable|json')]
    public string $inputSchema = '';

    #[Validate('nullable|json')]
    public string $outputSchema = '';

    public bool $enabled = true;

    /**
     * Populate the form from an existing tool.
     */
    public function setTool(AgentTool $tool): void
    {
        $this->toolId = $tool->id;
        $this->name = $tool->name;
        $this->description = (string) $tool->description;
        $this->endpoint = $tool->endpoint;
        $this->method = $tool->method->value;
        $this->headers = $this->encode($tool->headers);
        $this->auth = $this->encode($tool->auth);
        $this->inputSchema = $this->encode($tool->input_schema);
        $this->outputSchema = $this->encode($tool->output_schema);
        $this->enabled = $tool->enabled;
    }

    /**
     * Create or update the tool for the given agent.
     */
    public function save(Agent $agent): AgentTool
    {
        $this->validate();

        $data = [
            'organization_id' => $agent->organization_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'endpoint' => $this->endpoint,
            'method' => HttpMethod::from($this->method),
            'headers' => $this->decode($this->headers),
            'auth' => $this->decode($this->auth),
            'input_schema' => $this->decode($this->inputSchema),
            'output_schema' => $this->decode($this->outputSchema),
            'enabled' => $this->enabled,
        ];

        if ($this->toolId) {
            $tool = $agent->agentTools()->findOrFail($this->toolId);
            $tool->update($data);

            return $tool;
        }

        return $agent->agentTools()->create($data);
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function encode(?array $value): string
    {
        return filled($value) ? (json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '') : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decode(string $json): ?array
    {
        return filled($json) ? json_decode($json, true) : null;
    }
}
