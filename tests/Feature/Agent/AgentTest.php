<?php

use App\Actions\Agents\CreateAgent;
use App\Enums\AgentStatus;
use App\Exceptions\AgentLimitReached;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ai\SystemPromptCompiler;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);
});

it('creates an agent scoped to the active organization', function () {
    $agent = app(CreateAgent::class)->handle($this->organization, ['name' => 'Suporte']);

    expect($agent->organization_id)->toBe($this->organization->id)
        ->and($agent->status)->toBe(AgentStatus::Draft)
        ->and($agent->vector_store_id)->toBeNull()
        ->and(Agent::find($agent->id))->not->toBeNull();
});

it('stores all personality sections', function () {
    $sections = [
        'name' => 'Suporte',
        'identity' => 'You are Ada.',
        'soul' => 'Warm and concise.',
        'user' => 'Employees.',
        'bootstrap' => 'Say hello.',
        'heartbeat' => 'Stay on topic.',
        'tools' => 'Use file search.',
    ];

    $agent = app(CreateAgent::class)->handle($this->organization, $sections);
    $config = $agent->config;

    foreach (array_keys(SystemPromptCompiler::SECTIONS) as $section) {
        expect($config->{$section})->toBe($sections[$section]);
    }
});

it('does not show agents from another organization', function () {
    $other = Organization::factory()->create();
    $foreignAgent = Agent::factory()->for($other)->create();

    expect(Agent::find($foreignAgent->id))->toBeNull()
        ->and(Agent::count())->toBe(0);
});

it('caps agents per organization if a limit is set', function () {
    config()->set('plan.limits.agents', 2);

    app(CreateAgent::class)->handle($this->organization, ['name' => 'A']);
    app(CreateAgent::class)->handle($this->organization, ['name' => 'B']);

    expect(fn () => app(CreateAgent::class)->handle($this->organization, ['name' => 'C']))
        ->toThrow(AgentLimitReached::class);

    expect(Agent::count())->toBe(2);
});

it('allows unlimited agents when no limit is set', function () {
    config()->set('plan.limits.agents', null);

    app(CreateAgent::class)->handle($this->organization, ['name' => 'A']);
    app(CreateAgent::class)->handle($this->organization, ['name' => 'B']);
    app(CreateAgent::class)->handle($this->organization, ['name' => 'C']);

    expect(Agent::count())->toBe(3);
});

it('recompiles compiled_system_prompt when any section changes', function () {
    $agent = app(CreateAgent::class)->handle($this->organization, [
        'name' => 'Suporte',
        'identity' => 'Original identity.',
    ]);

    $original = $agent->config->compiled_system_prompt;
    expect($original)->toContain('Original identity.');

    $agent->config->update(['soul' => 'Now with a soul.']);

    expect($agent->config->fresh()->compiled_system_prompt)
        ->not->toBe($original)
        ->toContain('Now with a soul.')
        ->toContain('Original identity.');
});

it('matches compiled_system_prompt to the composition of the sections', function () {
    $agent = app(CreateAgent::class)->handle($this->organization, [
        'name' => 'Suporte',
        'identity' => 'Identity text.',
        'soul' => 'Soul text.',
        'tools' => 'Tools text.',
    ]);

    $expected = app(SystemPromptCompiler::class)->compile($agent->config);

    expect($agent->config->compiled_system_prompt)->toBe($expected)
        ->and($expected)->toBe(
            "## Identity\n\nIdentity text.\n\n## Soul\n\nSoul text.\n\n## Tools\n\nTools text."
        );
});
