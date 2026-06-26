<?php

use App\Enums\HttpMethod;
use App\Models\Agent;
use App\Models\AgentTool;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create();
});

it('creates an HTTP tool scoped to the agent', function () {
    Livewire::test('pages::tools.index', ['agent' => $this->agent])
        ->set('form.name', 'Lookup order')
        ->set('form.endpoint', 'https://erp.example.com/orders')
        ->set('form.method', HttpMethod::Post->value)
        ->set('form.headers', '{"Accept":"application/json"}')
        ->set('form.inputSchema', '{"type":"object","properties":{"id":{"type":"string"}}}')
        ->call('save')
        ->assertHasNoErrors();

    $tool = AgentTool::sole();

    expect($tool->agent_id)->toBe($this->agent->id)
        ->and($tool->organization_id)->toBe($this->organization->id)
        ->and($tool->name)->toBe('Lookup order')
        ->and($tool->method)->toBe(HttpMethod::Post)
        ->and($tool->headers)->toBe(['Accept' => 'application/json'])
        ->and($tool->input_schema)->toBe(['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]);
});

it('validates the schema', function () {
    Livewire::test('pages::tools.index', ['agent' => $this->agent])
        ->set('form.name', 'Bad tool')
        ->set('form.endpoint', 'https://api.example.com')
        ->set('form.inputSchema', '{not valid json')
        ->set('form.outputSchema', 'also { not json')
        ->call('save')
        ->assertHasErrors(['form.inputSchema', 'form.outputSchema']);

    expect(AgentTool::count())->toBe(0);
});

it('validates required fields and endpoint url', function () {
    Livewire::test('pages::tools.index', ['agent' => $this->agent])
        ->set('form.name', '')
        ->set('form.endpoint', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['form.name', 'form.endpoint']);
});

it('does not show tools from another organization', function () {
    $other = Organization::factory()->create();
    $foreignTool = AgentTool::factory()->for($other)->create();

    expect(AgentTool::find($foreignTool->id))->toBeNull()
        ->and(AgentTool::count())->toBe(0);

    Livewire::test('pages::tools.index', ['agent' => $this->agent])
        ->assertDontSee($foreignTool->name);
});
