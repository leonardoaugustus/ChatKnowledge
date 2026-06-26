<?php

use App\Models\Conversation;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public int $perPage = 15;

    public int $limit = 15;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    #[Validate('required|string|max:255')]
    public string $editTitle = '';

    public function loadMore(): void
    {
        $this->limit += $this->perPage;
        unset($this->items);
    }

    #[On('conversation-started')]
    public function refresh(): void
    {
        unset($this->items);
    }

    public function rename(int $id): void
    {
        $conversation = $this->ownedConversation($id);

        if (! $conversation) {
            return;
        }

        $this->editingId = $conversation->id;
        $this->editTitle = (string) $conversation->title;

        Flux::modal('rename-conversation')->show();
    }

    public function saveRename(): void
    {
        $this->validate();

        if ($conversation = $this->ownedConversation($this->editingId)) {
            $conversation->update(['title' => $this->editTitle]);
        }

        $this->reset('editingId', 'editTitle');
        unset($this->items);

        Flux::modal('rename-conversation')->close();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;

        Flux::modal('delete-conversation')->show();
    }

    public function delete(): void
    {
        if ($conversation = $this->ownedConversation($this->deletingId)) {
            $deletedId = $conversation->id;
            $conversation->delete();

            $this->dispatch('conversation-deleted', id: $deletedId);
        }

        $this->reset('deletingId');
        unset($this->items);

        Flux::modal('delete-conversation')->close();
    }

    protected function ownedConversation(?int $id): ?Conversation
    {
        return $id
            ? Conversation::where('id', $id)->where('user_id', Auth::id())->first()
            : null;
    }

    /**
     * The current user's recent conversations (one extra row signals "has more").
     *
     * @return Collection<int, Conversation>
     */
    #[Computed]
    public function items(): Collection
    {
        return Conversation::with('agent')
            ->where('user_id', Auth::id())
            ->latest()
            ->take($this->limit + 1)
            ->get();
    }

    public function hasMore(): bool
    {
        return $this->items->count() > $this->limit;
    }

    public function activeId(): ?int
    {
        return request()->integer('c') ?: null;
    }
}; ?>

<div class="flex min-h-0 flex-col">
    <div class="px-2 pb-1 text-2xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Recentes') }}</div>

    <div x-data x-ref="scroller" class="flex max-h-72 flex-col gap-0.5 overflow-y-auto px-1" data-test="recent-conversations">
        @forelse ($this->items->take($this->limit) as $conversation)
            <div
                wire:key="recent-{{ $conversation->id }}"
                @class([
                    'group flex items-center rounded-lg',
                    'bg-zinc-100 dark:bg-zinc-800' => $this->activeId() === $conversation->id,
                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => $this->activeId() !== $conversation->id,
                ])
                data-test="recent-item"
            >
                <a
                    href="{{ route('chat.show', ['agent' => $conversation->agent_id]) }}?c={{ $conversation->id }}"
                    wire:navigate
                    @class([
                        'flex min-w-0 flex-1 items-center gap-2 px-2 py-1.5 text-sm',
                        'font-medium text-zinc-900 dark:text-white' => $this->activeId() === $conversation->id,
                        'text-zinc-600 dark:text-zinc-300' => $this->activeId() !== $conversation->id,
                    ])
                >
                    <flux:icon name="chat-bubble-left" class="size-4 shrink-0 text-zinc-400" />
                    <span class="truncate">{{ $conversation->title ?: __('Conversa') }}</span>
                </a>

                <flux:dropdown position="bottom" align="end">
                    <flux:button
                        variant="ghost"
                        size="xs"
                        icon="ellipsis-horizontal"
                        class="me-1 opacity-0 group-hover:opacity-100"
                        :aria-label="__('Ações da conversa')"
                        data-test="conversation-menu"
                    />
                    <flux:menu>
                        <flux:menu.item icon="pencil-square" wire:click="rename({{ $conversation->id }})" data-test="conversation-rename">
                            {{ __('Renomear') }}
                        </flux:menu.item>
                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $conversation->id }})" data-test="conversation-delete">
                            {{ __('Excluir') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        @empty
            <flux:text class="px-2 py-3 text-xs text-zinc-400">{{ __('Sem conversas recentes.') }}</flux:text>
        @endforelse

        @if ($this->hasMore())
            <div
                wire:key="recents-sentinel"
                x-init="new IntersectionObserver((entries) => entries[0].isIntersecting && $wire.loadMore(), { root: $refs.scroller, rootMargin: '120px' }).observe($el)"
                class="flex justify-center py-2"
                data-test="recents-sentinel"
            >
                <flux:icon name="arrow-path" class="size-4 animate-spin text-zinc-300" />
            </div>
        @endif
    </div>

    <flux:modal name="rename-conversation" class="max-w-sm">
        <form wire:submit="saveRename" class="space-y-4">
            <flux:heading size="lg">{{ __('Renomear conversa') }}</flux:heading>

            <flux:input wire:model="editTitle" :label="__('Título')" autofocus data-test="rename-title" />
            @error('editTitle') <flux:error :message="$message" /> @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="rename-save">{{ __('Salvar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-confirm-modal
        name="delete-conversation"
        :title="__('Excluir conversa?')"
        :description="__('Esta ação não pode ser desfeita. Todas as mensagens desta conversa serão removidas.')"
        confirm="delete"
        :confirm-label="__('Excluir')"
        variant="danger"
    />
</div>
