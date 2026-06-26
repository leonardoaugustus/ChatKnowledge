<?php

use App\Livewire\Forms\AgentForm;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Services\Ai\SystemPromptCompiler;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Personality Builder')] class extends Component
{
    public Agent $agent;

    public AgentForm $form;

    public function mount(Agent $agent): void
    {
        $this->agent = $agent;
        $this->form->setAgent($agent);
    }

    /**
     * Live composition of the system prompt from the current form sections.
     */
    #[Computed]
    public function compiledPreview(): string
    {
        return app(SystemPromptCompiler::class)->compile(new AgentConfig($this->form->sections));
    }

    /**
     * The ordered sections rendered in the form.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function sectionFields(): array
    {
        return SystemPromptCompiler::SECTIONS;
    }

    public function save(): void
    {
        $this->form->saveTo($this->agent);

        unset($this->compiledPreview);

        Flux::toast(variant: 'success', text: __('Agent updated.'));
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Personality Builder')" :description="$agent->name">
        <x-slot:actions>
            <x-status-badge :status="$agent->status" />
        </x-slot:actions>
    </x-page-header>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:input wire:model="form.name" :label="__('Nome do agente')" required data-test="agent-name" />
            @error('form.name') <flux:error :message="$message" /> @enderror

            @foreach ($this->sectionFields as $section => $heading)
                <flux:textarea
                    wire:model.live.blur="form.sections.{{ $section }}"
                    :label="__($heading)"
                    rows="3"
                    :data-test="'agent-section-'.$section"
                />
                @error('form.sections.'.$section) <flux:error :message="$message" /> @enderror
            @endforeach

            <div>
                <flux:button type="submit" variant="primary" data-test="agent-save">{{ __('Salvar') }}</flux:button>
            </div>
        </form>

        <div class="lg:sticky lg:top-6">
            <flux:heading size="sm" class="mb-2">{{ __('Pré-visualização do system prompt') }}</flux:heading>

            <div class="rounded-card border border-zinc-200 p-4 dark:border-zinc-700" data-test="agent-preview">
                @if (filled($this->compiledPreview))
                    <div class="prose prose-sm max-w-none dark:prose-invert">
                        {!! str()->markdown($this->compiledPreview) !!}
                    </div>
                @else
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Preencha as seções para gerar o prompt.') }}</flux:text>
                @endif
            </div>
        </div>
    </div>
</section>
