<?php

use App\Ai\Agents\ChatAgent;
use App\Enums\MessageRole;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->withConfig()->create([
        'name' => 'Suporte',
        'vector_store_id' => 'vs_chat',
    ]);
});

it('shows the conversation history for the current user', function () {
    $mine = Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)
        ->create(['title' => 'Minha conversa']);

    $someoneElse = User::factory()->create();
    Conversation::factory()->for($this->organization)->for($this->agent)->for($someoneElse)
        ->create(['title' => 'Conversa de outro']);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->assertSee('Minha conversa')
        ->assertDontSee('Conversa de outro');
});

it('starts a new conversation with the new chat action', function () {
    $conversation = Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)->create();

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('conversationId', $conversation->id)
        ->call('newChat')
        ->assertSet('conversationId', null);
});

it('loads a selected conversation', function () {
    $conversation = Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)->create();
    Message::factory()->for($this->organization)->for($conversation)->create([
        'role' => MessageRole::Assistant,
        'content' => 'Resposta anterior.',
    ]);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->call('selectConversation', $conversation->id)
        ->assertSet('conversationId', $conversation->id)
        ->assertSee('Resposta anterior.');
});

it('does not load another user\'s conversation', function () {
    $someoneElse = User::factory()->create();
    $foreign = Conversation::factory()->for($this->organization)->for($this->agent)->for($someoneElse)->create();

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->call('selectConversation', $foreign->id)
        ->assertSet('conversationId', null);
});

it('persists the new conversation in history after sending', function () {
    ChatAgent::fake(['Olá!']);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('draft', 'Primeira pergunta sobre o produto')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee('Primeira pergunta sobre o produto'); // conversation title in history
});

it('shows the edit-agent entry only to admins', function () {
    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->assertSee('Editar agente');

    $collaborator = User::factory()->create();
    $this->organization->members()->attach($collaborator, ['role' => Role::Colaborador->value]);
    $collaborator->switchOrganization($this->organization);
    $this->actingAs($collaborator);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->assertDontSee('Editar agente');
});
