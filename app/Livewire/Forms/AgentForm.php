<?php

namespace App\Livewire\Forms;

use App\Models\Agent;
use App\Services\Ai\SystemPromptCompiler;
use Livewire\Form;

class AgentForm extends Form
{
    public string $name = '';

    /**
     * The personality sections keyed by section name. Stored in an array so a
     * section named "rules" cannot collide with Livewire's reserved $rules.
     *
     * @var array<string, string>
     */
    public array $sections = [];

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $rules = ['name' => 'required|string|max:255'];

        foreach (array_keys(SystemPromptCompiler::SECTIONS) as $section) {
            $rules['sections.'.$section] = in_array($section, ['identity', 'soul'], true)
                ? 'required|string'
                : 'nullable|string';
        }

        return $rules;
    }

    /**
     * Populate the form from an existing agent and its config.
     */
    public function setAgent(Agent $agent): void
    {
        $this->name = $agent->name;

        foreach (array_keys(SystemPromptCompiler::SECTIONS) as $section) {
            $this->sections[$section] = (string) ($agent->config?->{$section} ?? '');
        }
    }

    /**
     * Persist the form to the given agent and its config. The config's saving
     * hook recompiles compiled_system_prompt from the sections.
     */
    public function saveTo(Agent $agent): void
    {
        $this->validate();

        $agent->update(['name' => $this->name]);

        $agent->config()->updateOrCreate(
            ['agent_id' => $agent->id],
            $this->sections,
        );
    }
}
