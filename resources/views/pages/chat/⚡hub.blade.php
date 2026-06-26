<?php

use App\Models\Agent;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chat')] class extends Component
{
    /**
     * @return Collection<int, Agent>
     */
    #[Computed]
    public function agents(): Collection
    {
        return Agent::latest()->get();
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Chat')" :description="__('Converse com os agentes da organização.')" />

    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->agents as $agent)
            <a
                href="{{ route('chat.show', ['agent' => $agent]) }}"
                wire:navigate
                class="group flex items-center justify-between rounded-card border border-zinc-200 p-4 transition hover:border-brand-400 dark:border-zinc-700 dark:hover:border-brand-500"
                wire:key="chat-agent-{{ $agent->id }}"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="chat-bubble-left-right" class="size-5 text-zinc-400 group-hover:text-brand-500" />
                    <div>
                        <flux:heading size="sm">{{ $agent->name }}</flux:heading>
                        <x-status-badge :status="$agent->status" />
                    </div>
                </div>
                <flux:icon name="chevron-right" class="size-4 text-zinc-400" />
            </a>
        @empty
            <x-empty-state
                class="sm:col-span-2 lg:col-span-3"
                icon="chat-bubble-left-right"
                :heading="__('Nenhum agente disponível')"
                :description="__('Peça a um administrador para criar e treinar um agente.')"
            />
        @endforelse
    </div>
</section>
