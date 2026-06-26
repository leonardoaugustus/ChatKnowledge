<?php

use App\Enums\HttpMethod;
use App\Enums\Role;
use App\Livewire\Forms\AgentToolForm;
use App\Models\Agent;
use App\Models\AgentTool;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tools')] class extends Component
{
    public Agent $agent;

    public AgentToolForm $form;

    public function mount(Agent $agent): void
    {
        $this->ensureAdmin($agent);

        $this->agent = $agent;
    }

    protected function ensureAdmin(Agent $agent): void
    {
        abort_unless(auth()->user()->organizationRole($agent->organization) === Role::Admin, 403);
    }

    /**
     * @return Collection<int, AgentTool>
     */
    #[Computed]
    public function tools(): Collection
    {
        return $this->agent->agentTools()->latest()->get();
    }

    public function create(): void
    {
        $this->form->reset();

        Flux::modal('tool-form')->show();
    }

    public function edit(int $id): void
    {
        $this->form->reset();
        $this->form->setTool($this->agent->agentTools()->findOrFail($id));

        Flux::modal('tool-form')->show();
    }

    public function save(): void
    {
        $this->ensureAdmin($this->agent);

        $this->form->save($this->agent);

        $this->form->reset();
        unset($this->tools);

        Flux::modal('tool-form')->close();
        Flux::toast(variant: 'success', text: __('Tool salva.'));
    }

    public function delete(int $id): void
    {
        $this->ensureAdmin($this->agent);

        $this->agent->agentTools()->findOrFail($id)->delete();

        unset($this->tools);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function methods(): array
    {
        return array_map(fn (HttpMethod $method) => $method->value, HttpMethod::cases());
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Tools')" :description="$agent->name">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create" data-test="tool-create">{{ __('Nova tool') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mt-6 flex flex-col gap-2">
        @forelse ($this->tools as $tool)
            <div class="flex items-center justify-between rounded-card border border-zinc-200 p-4 dark:border-zinc-700" wire:key="tool-{{ $tool->id }}">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="zinc">{{ $tool->method->value }}</flux:badge>
                        <flux:heading size="sm" class="truncate">{{ $tool->name }}</flux:heading>
                    </div>
                    <flux:text class="mt-1 truncate text-zinc-500 dark:text-zinc-400">{{ $tool->endpoint }}</flux:text>
                </div>
                <div class="flex shrink-0 gap-2">
                    <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $tool->id }})">{{ __('Editar') }}</flux:button>
                    <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $tool->id }})">{{ __('Remover') }}</flux:button>
                </div>
            </div>
        @empty
            <x-empty-state icon="wrench-screwdriver" :heading="__('Nenhuma tool')" :description="__('Conecte o agente a APIs externas (ERP, CRM, sistemas internos).')" />
        @endforelse
    </div>

    <flux:modal name="tool-form" class="max-w-xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $form->toolId ? __('Editar tool') : __('Nova tool') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Nome')" data-test="tool-name" />
            @error('form.name') <flux:error :message="$message" /> @enderror

            <flux:input wire:model="form.description" :label="__('Descrição')" />

            <div class="flex gap-2">
                <flux:select wire:model="form.method" :label="__('Método')" class="w-36" data-test="tool-method">
                    @foreach ($this->methods as $method)
                        <flux:select.option :value="$method">{{ $method }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.endpoint" :label="__('Endpoint')" class="flex-1" data-test="tool-endpoint" />
            </div>
            @error('form.endpoint') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="form.headers" :label="__('Headers (JSON)')" rows="2" />
            @error('form.headers') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="form.auth" :label="__('Auth (JSON)')" rows="2" />
            @error('form.auth') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="form.inputSchema" :label="__('Input schema (JSON)')" rows="3" data-test="tool-input-schema" />
            @error('form.inputSchema') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="form.outputSchema" :label="__('Output schema (JSON)')" rows="3" />
            @error('form.outputSchema') <flux:error :message="$message" /> @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="tool-save">{{ __('Salvar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
