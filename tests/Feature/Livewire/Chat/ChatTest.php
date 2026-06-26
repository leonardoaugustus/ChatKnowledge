<?php

use App\Ai\Agents\ChatAgent;
use App\Enums\MessageRole;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ai\ChatService;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextStart;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->withConfig()->create([
        'vector_store_id' => 'vs_chat',
    ]);
});

it('answers using the agent\'s vector store', function () {
    ChatAgent::fake(['A grounded answer.']);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('draft', 'What are the hours?')
        ->call('send')
        ->assertHasNoErrors();

    ChatAgent::assertPrompted('What are the hours?');

    $tools = collect((new ChatAgent($this->agent))->tools());
    expect($tools)->toHaveCount(1)
        ->and($tools->first())->toBeInstanceOf(FileSearch::class)
        ->and($tools->first()->ids())->toBe(['vs_chat']);

    expect(Message::where('role', MessageRole::Assistant->value)->first()->content)->toBe('A grounded answer.');
});

it('confirms the agent belongs to the active organization before querying', function () {
    $other = Organization::factory()->create();
    $foreignAgent = Agent::factory()->for($other)->withConfig()->create();

    Livewire::test('pages::chat.index', ['agent' => $foreignAgent])
        ->assertForbidden();
});

it('persists the conversation and messages', function () {
    ChatAgent::fake(['Persisted answer.']);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('draft', 'Hello there')
        ->call('send')
        ->assertHasNoErrors();

    $conversation = Conversation::sole();

    expect($conversation->agent_id)->toBe($this->agent->id)
        ->and($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->organization_id)->toBe($this->organization->id)
        ->and($conversation->messages()->count())->toBe(2)
        ->and($conversation->messages()->where('role', MessageRole::User->value)->first()->content)->toBe('Hello there');
});

it('requires authentication', function () {
    auth()->logout();

    $this->get(route('chat.show', ['current_organization' => $this->organization->slug, 'agent' => $this->agent]))
        ->assertRedirect(route('login'));
});

it('streams responses via native streaming, not synchronously', function () {
    ChatAgent::fake(['Streamed answer.']);

    $stream = app(ChatService::class)->answer($this->agent, 'Question?');

    expect($stream)->toBeInstanceOf(StreamableAgentResponse::class);

    // Iterating yields streamed events whose deltas combine to the answer.
    $text = TextDelta::combine(iterator_to_array($stream));
    expect($text)->toBe('Streamed answer.');
});

it('surfaces execution progress events while streaming', function () {
    ChatAgent::fake(['Answer with progress.']);

    $component = Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('draft', 'Any progress?')
        ->call('send')
        ->assertHasNoErrors();

    // The service maps stream lifecycle events to human progress labels.
    $chat = app(ChatService::class);
    expect($chat->progressLabel(new StreamStart('id', 'openai', 'model', time())))->not->toBeNull()
        ->and($chat->progressLabel(new TextStart('id', 'mid', time())))->not->toBeNull();
});

it('names a new conversation from the first answer', function () {
    ChatAgent::fake(['Funcionamos das 9h às 18h.']);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('draft', 'qual o horario?')
        ->call('send')
        ->assertHasNoErrors();

    expect(Conversation::sole()->title)->toBe('Funcionamos das 9h às 18h.');
});
