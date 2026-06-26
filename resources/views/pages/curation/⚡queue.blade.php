<?php

use App\Enums\CurationStatus;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Curadoria')] class extends Component
{
    public Agent $agent;

    public function mount(Agent $agent): void
    {
        $this->agent = $agent;
    }

    /**
     * Pending knowledge items for the agent, grouped by KnowledgeType.
     *
     * @return Collection<string, Collection<int, KnowledgeItem>>
     */
    #[Computed]
    public function groupedItems(): Collection
    {
        return $this->agent->knowledgeItems()
            ->where('curation_status', CurationStatus::Pending->value)
            ->latest()
            ->get()
            ->groupBy(fn (KnowledgeItem $item) => $item->type->value);
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Curadoria')" :description="$agent->name" />

    @forelse ($this->groupedItems as $typeValue => $items)
        @php($type = App\Enums\KnowledgeType::from($typeValue))
        <div class="mt-6" wire:key="group-{{ $typeValue }}">
            <div class="mb-3 flex items-center gap-2">
                <flux:heading size="sm">{{ $type->label() }}</flux:heading>
                <flux:badge :color="$type->color()" size="sm">{{ $items->count() }}</flux:badge>
            </div>

            <div class="flex flex-col gap-2">
                @foreach ($items as $item)
                    <div class="rounded-card border border-zinc-200 p-4 dark:border-zinc-700" wire:key="item-{{ $item->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <flux:heading size="sm" class="truncate">{{ $item->title }}</flux:heading>
                                @if ($item->summary)
                                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $item->summary }}</flux:text>
                                @endif
                            </div>
                            <x-status-badge :status="$item->curation_status" />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <x-empty-state
            class="mt-6"
            icon="clipboard-document-check"
            :heading="__('Nada para curar')"
            :description="__('Os itens extraídos aparecerão aqui para revisão.')"
        />
    @endforelse
</section>
