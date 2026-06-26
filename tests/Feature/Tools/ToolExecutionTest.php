<?php

use App\Ai\Agents\ChatAgent;
use App\Ai\Tools\HttpToolBridge;
use App\Enums\HttpMethod;
use App\Exceptions\ToolInputValidationException;
use App\Models\Agent;
use App\Models\AgentTool;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tools\HttpToolExecutor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->withConfig()->create(['vector_store_id' => 'vs_chat']);

    $this->tool = AgentTool::factory()->for($this->organization)->for($this->agent)->create([
        'name' => 'Lookup order',
        'endpoint' => 'https://erp.example.com/orders',
        'method' => HttpMethod::Post,
        'headers' => ['X-Api-Key' => 'secret'],
        'auth' => ['type' => 'bearer', 'token' => 'tok_123'],
        'input_schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']],
    ]);
});

it('invokes the configured endpoint with the right method/headers', function () {
    Http::fake(['erp.example.com/*' => Http::response(['status' => 'shipped'], 200)]);

    $result = app(HttpToolExecutor::class)->execute($this->tool, ['id' => 'ORD-9']);

    expect($result)->toBe(['status' => 'shipped']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://erp.example.com/orders'
        && $request->hasHeader('X-Api-Key', 'secret')
        && $request->hasHeader('Authorization', 'Bearer tok_123')
        && $request['id'] === 'ORD-9');
});

it('validates input against the schema', function () {
    Http::fake();

    expect(fn () => app(HttpToolExecutor::class)->execute($this->tool, []))
        ->toThrow(ToolInputValidationException::class);

    Http::assertNothingSent();
});

it('surfaces tool errors without crashing the chat', function () {
    Http::fake(['erp.example.com/*' => Http::response(['error' => 'boom'], 500)]);

    $output = (new HttpToolBridge($this->tool, $this->agent))->run(['id' => 'ORD-9']);

    expect($output)->toBeString()
        ->toStartWith('Erro ao executar a ferramenta');
});

it('never executes a tool from another organization', function () {
    Http::fake();

    $other = Organization::factory()->create();
    $foreignTool = AgentTool::factory()->for($other)->create([
        'endpoint' => 'https://evil.example.com/exfiltrate',
    ]);

    $output = (new HttpToolBridge($foreignTool, $this->agent))->run(['id' => 'x']);

    expect($output)->toBe(__('Ferramenta indisponível para este agente.'));

    Http::assertNothingSent();
});

it('exposes the agent\'s enabled tools to the chat agent', function () {
    $tools = collect((new ChatAgent($this->agent))->tools());

    expect($tools->whereInstanceOf(HttpToolBridge::class))->toHaveCount(1)
        ->and($tools->whereInstanceOf(HttpToolBridge::class)->first()->tool->is($this->tool))->toBeTrue();
});
