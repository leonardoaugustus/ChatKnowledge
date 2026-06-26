<?php

use App\Ai\Agents\ChatAgent;
use App\Enums\MessageRole;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Ai\ChatService;
use Laravel\Ai\Providers\Tools\FileSearch;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->withConfig()->create([
        'vector_store_id' => 'vs_chat',
    ]);
});

it('renders the source(s) for an answer', function () {
    $conversation = Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)->create();

    Message::factory()->for($this->organization)->for($conversation)->create([
        'role' => MessageRole::Assistant,
        'content' => 'We are open 9 to 5.',
        'sources' => [['title' => 'Política de horários']],
    ]);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('conversationId', $conversation->id)
        ->assertSee('Política de horários');
});

it('returns the no-knowledge message when FileSearch finds nothing', function () {
    ChatAgent::fake([ChatService::NO_KNOWLEDGE_MESSAGE]);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('draft', 'Qual a cor favorita do CEO?')
        ->call('send')
        ->assertHasNoErrors();

    $assistant = Message::where('role', MessageRole::Assistant->value)->sole();

    expect($assistant->content)->toBe(ChatService::NO_KNOWLEDGE_MESSAGE)
        ->and($assistant->sources)->toBe([]);
});

it('instructs the agent to use the exact no-knowledge message', function () {
    expect((new ChatAgent($this->agent))->instructions())
        ->toContain(ChatService::NO_KNOWLEDGE_MESSAGE);
});

it('never answers from outside the vector store', function () {
    $chatAgent = new ChatAgent($this->agent);

    // The guardrail constrains answers to the knowledge base only.
    expect($chatAgent->instructions())
        ->toContain('EXCLUSIVAMENTE')
        ->toContain('Nunca invente');

    // The only tool is FileSearch over the agent's own store — no web access.
    $tools = collect($chatAgent->tools());
    expect($tools)->toHaveCount(1)
        ->and($tools->first())->toBeInstanceOf(FileSearch::class)
        ->and($tools->first()->ids())->toBe(['vs_chat']);
});
