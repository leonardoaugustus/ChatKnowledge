<?php

use App\Actions\Training\StoreDocument;
use App\Models\Agent;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Treinamento')] class extends Component
{
    use WithFileUploads;

    public Agent $agent;

    #[Validate('required|file|extensions:pdf,doc,docx,txt,md,markdown|max:20480')]
    public $file;

    public function mount(Agent $agent): void
    {
        $this->agent = $agent;
    }

    /**
     * @return Collection<int, \App\Models\Document>
     */
    #[Computed]
    public function documents(): Collection
    {
        return $this->agent->documents()->latest()->get();
    }

    public function save(StoreDocument $storeDocument): void
    {
        $this->validate();

        $storeDocument->handle($this->agent, $this->file);

        $this->reset('file');
        unset($this->documents);

        Flux::toast(variant: 'success', text: __('Documento enviado.'));
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Treinamento')" :description="$agent->name" />

    <form wire:submit="save" class="mt-6 flex flex-col gap-4" enctype="multipart/form-data">
        <flux:input
            type="file"
            wire:model="file"
            :label="__('Material bruto')"
            :description="__('PDF, DOCX, TXT ou Markdown (até 20 MB).')"
            accept=".pdf,.doc,.docx,.txt,.md,.markdown"
            data-test="document-file"
        />
        @error('file') <flux:error :message="$message" /> @enderror

        <div>
            <flux:button type="submit" variant="primary" icon="arrow-up-tray" data-test="document-upload">
                {{ __('Enviar') }}
            </flux:button>
        </div>
    </form>

    <div class="mt-8">
        <flux:heading size="sm" class="mb-3">{{ __('Documentos') }}</flux:heading>

        @forelse ($this->documents as $document)
            <div class="flex items-center justify-between border-b border-zinc-200 py-2 dark:border-zinc-700" wire:key="doc-{{ $document->id }}">
                <span class="truncate text-sm text-zinc-700 dark:text-zinc-300">{{ $document->name }}</span>
                <x-status-badge :status="$document->status" />
            </div>
        @empty
            <x-empty-state icon="document-text" :heading="__('Nenhum documento ainda')" :description="__('Envie o primeiro material para treinar este agente.')" />
        @endforelse
    </div>
</section>
