<?php

use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int $perPage = 15;

    public int $limit = 15;

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
            <a
                href="{{ route('chat.show', ['agent' => $conversation->agent_id]) }}?c={{ $conversation->id }}"
                wire:navigate
                wire:key="recent-{{ $conversation->id }}"
                @class([
                    'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm',
                    'bg-zinc-100 font-medium text-zinc-900 dark:bg-zinc-800 dark:text-white' => $this->activeId() === $conversation->id,
                    'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800/60' => $this->activeId() !== $conversation->id,
                ])
                data-test="recent-item"
            >
                <flux:icon name="chat-bubble-left" class="size-4 shrink-0 text-zinc-400" />
                <span class="truncate">{{ $conversation->title ?: __('Conversa') }}</span>
            </a>
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
</div>
