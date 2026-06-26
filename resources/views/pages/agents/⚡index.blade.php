<?php

use App\Actions\Agents\CreateAgent;
use App\Exceptions\AgentLimitReached;
use App\Models\Agent;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Agentes')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    /**
     * @return Collection<int, Agent>
     */
    #[Computed]
    public function agents(): Collection
    {
        return Agent::with('config')->latest()->get();
    }

    public function create(CreateAgent $createAgent): void
    {
        Gate::authorize('create', Agent::class);

        $this->validate();

        try {
            $agent = $createAgent->handle(auth()->user()->currentOrganization, ['name' => $this->name]);
        } catch (AgentLimitReached $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->reset('name');

        Flux::modal('create-agent')->close();

        $this->redirectRoute('agents.edit', ['agent' => $agent], navigate: true);
    }

    public function canManage(): bool
    {
        return Gate::allows('create', Agent::class);
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Agentes')" :description="__('Crie e gerencie os agentes de IA da organização.')">
        <x-slot:actions>
            @if ($this->canManage())
                <flux:modal.trigger name="create-agent">
                    <flux:button variant="primary" icon="plus" data-test="agent-create">{{ __('Novo agente') }}</flux:button>
                </flux:modal.trigger>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mt-6 flex flex-col gap-3">
        @forelse ($this->agents as $agent)
            <div class="rounded-card border border-zinc-200 p-4 dark:border-zinc-700" wire:key="agent-{{ $agent->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <flux:heading size="sm">{{ $agent->name }}</flux:heading>
                        <x-status-badge :status="$agent->status" />
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button size="sm" icon="adjustments-horizontal" :href="route('agents.edit', ['agent' => $agent])" wire:navigate>{{ __('Builder') }}</flux:button>
                    <flux:button size="sm" icon="document-arrow-up" :href="route('training.upload', ['agent' => $agent])" wire:navigate>{{ __('Treinamento') }}</flux:button>
                    <flux:button size="sm" icon="clipboard-document-check" :href="route('curation.queue', ['agent' => $agent])" wire:navigate>{{ __('Curadoria') }}</flux:button>
                    <flux:button size="sm" icon="chat-bubble-left-right" :href="route('chat.show', ['agent' => $agent])" wire:navigate>{{ __('Chat') }}</flux:button>
                    <flux:button size="sm" icon="wrench-screwdriver" :href="route('tools.show', ['agent' => $agent])" wire:navigate>{{ __('Tools') }}</flux:button>
                </div>
            </div>
        @empty
            <x-empty-state
                icon="cpu-chip"
                :heading="__('Nenhum agente ainda')"
                :description="__('Crie seu primeiro agente para começar a treinar a base de conhecimento.')"
            />
        @endforelse
    </div>

    <flux:modal name="create-agent" class="max-w-md">
        <form wire:submit="create" class="space-y-4">
            <flux:heading size="lg">{{ __('Novo agente') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Nome do agente')" autofocus data-test="agent-name" />
            @error('name') <flux:error :message="$message" /> @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="agent-save">{{ __('Criar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
