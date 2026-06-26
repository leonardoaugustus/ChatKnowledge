<?php

use App\Models\Agent;
use App\Models\User;
use App\Services\Ai\SystemPromptCompiler;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->withConfig()->create();
});

it('validates required sections', function () {
    Livewire::test('pages::agent.edit', ['agent' => $this->agent])
        ->set('form.name', '')
        ->set('form.sections.identity', '')
        ->set('form.sections.soul', '')
        ->call('save')
        ->assertHasErrors([
            'form.name' => 'required',
            'form.sections.identity' => 'required',
            'form.sections.soul' => 'required',
        ]);
});

it('persists each section', function () {
    Livewire::test('pages::agent.edit', ['agent' => $this->agent])
        ->set('form.name', 'Suporte Premium')
        ->set('form.sections.identity', 'You are Ada.')
        ->set('form.sections.soul', 'Warm and concise.')
        ->set('form.sections.objective', 'Resolve doubts.')
        ->set('form.sections.tone', 'Friendly.')
        ->set('form.sections.rules', 'Never invent answers.')
        ->set('form.sections.heartbeat', 'Stay on topic.')
        ->set('form.sections.bootstrap', 'Say hello.')
        ->set('form.sections.user', 'Employees.')
        ->set('form.sections.tools', 'Use file search.')
        ->call('save')
        ->assertHasNoErrors();

    $config = $this->agent->fresh()->config;

    expect($this->agent->fresh()->name)->toBe('Suporte Premium')
        ->and($config->identity)->toBe('You are Ada.')
        ->and($config->objective)->toBe('Resolve doubts.')
        ->and($config->tone)->toBe('Friendly.')
        ->and($config->rules)->toBe('Never invent answers.')
        ->and($config->tools)->toBe('Use file search.');
});

it('assembles a system prompt from the sections', function () {
    Livewire::test('pages::agent.edit', ['agent' => $this->agent])
        ->set('form.name', 'Suporte')
        ->set('form.sections.identity', 'You are Ada.')
        ->set('form.sections.soul', 'Warm and concise.')
        ->set('form.sections.tools', 'Use file search.')
        ->call('save')
        ->assertHasNoErrors();

    $config = $this->agent->fresh()->config;
    $expected = app(SystemPromptCompiler::class)->compile($config);

    expect($config->compiled_system_prompt)->toBe($expected)
        ->toContain('## Identity', 'You are Ada.')
        ->toContain('## Tools', 'Use file search.');
});

it('renders sections as markdown preview', function () {
    Livewire::test('pages::agent.edit', ['agent' => $this->agent])
        ->set('form.sections.identity', 'Grounded helpful assistant.')
        ->assertSee('Grounded helpful assistant.')
        ->assertSeeHtml('<h2>Identity</h2>');
});

it('recompiles compiled_system_prompt after editing a section', function () {
    $component = Livewire::test('pages::agent.edit', ['agent' => $this->agent])
        ->set('form.name', 'Suporte')
        ->set('form.sections.identity', 'First identity.')
        ->set('form.sections.soul', 'A soul.')
        ->call('save');

    $first = $this->agent->fresh()->config->compiled_system_prompt;
    expect($first)->toContain('First identity.');

    $component->set('form.sections.identity', 'Second identity.')->call('save');

    expect($this->agent->fresh()->config->compiled_system_prompt)
        ->not->toBe($first)
        ->toContain('Second identity.');
});
