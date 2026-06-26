<?php

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create();
});

it('lists the current user\'s recent conversations only', function () {
    Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)
        ->create(['title' => 'Minha conversa']);

    $someoneElse = User::factory()->create();
    Conversation::factory()->for($this->organization)->for($this->agent)->for($someoneElse)
        ->create(['title' => 'Conversa de outro']);

    Livewire::test('recent-conversations')
        ->assertSee('Minha conversa')
        ->assertDontSee('Conversa de outro');
});

it('loads more conversations on scroll (infinite pagination)', function () {
    Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)->count(20)->create();

    $component = Livewire::test('recent-conversations');

    expect($component->instance()->hasMore())->toBeTrue();

    $component->call('loadMore');

    expect($component->instance()->hasMore())->toBeFalse()
        ->and($component->get('limit'))->toBe(30);
});

it('refreshes when a new conversation starts', function () {
    Livewire::test('recent-conversations')
        ->assertDontSee('Nova pergunta')
        ->tap(fn () => Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)->create(['title' => 'Nova pergunta']))
        ->dispatch('conversation-started')
        ->assertSee('Nova pergunta');
});

it('renames a conversation', function () {
    $conversation = Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)
        ->create(['title' => 'Título antigo']);

    Livewire::test('recent-conversations')
        ->call('rename', $conversation->id)
        ->assertSet('editTitle', 'Título antigo')
        ->set('editTitle', 'Título novo')
        ->call('saveRename')
        ->assertHasNoErrors();

    expect($conversation->fresh()->title)->toBe('Título novo');
});

it('validates the rename title', function () {
    $conversation = Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)
        ->create(['title' => 'Mantém']);

    Livewire::test('recent-conversations')
        ->call('rename', $conversation->id)
        ->set('editTitle', '')
        ->call('saveRename')
        ->assertHasErrors(['editTitle']);

    expect($conversation->fresh()->title)->toBe('Mantém');
});

it('deletes a conversation and announces it', function () {
    $conversation = Conversation::factory()->for($this->organization)->for($this->agent)->for($this->user)->create();

    Livewire::test('recent-conversations')
        ->call('confirmDelete', $conversation->id)
        ->assertSet('deletingId', $conversation->id)
        ->call('delete')
        ->assertDispatched('conversation-deleted', id: $conversation->id);

    expect(Conversation::find($conversation->id))->toBeNull();
});

it('does not rename or delete another user\'s conversation', function () {
    $someoneElse = User::factory()->create();
    $foreign = Conversation::factory()->for($this->organization)->for($this->agent)->for($someoneElse)
        ->create(['title' => 'Do outro']);

    Livewire::test('recent-conversations')
        ->call('rename', $foreign->id)
        ->assertSet('editingId', null);

    Livewire::test('recent-conversations')
        ->set('deletingId', $foreign->id)
        ->call('delete');

    expect($foreign->fresh())->not->toBeNull()
        ->and($foreign->fresh()->title)->toBe('Do outro');
});
