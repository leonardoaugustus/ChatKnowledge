<?php

use App\Enums\CurationStatus;
use App\Enums\Role;
use App\Livewire\Forms\ManualFaqForm;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Services\Ai\PublishingService;
use App\Services\Curation\CurationService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Curadoria')] class extends Component
{
    public Agent $agent;

    public ManualFaqForm $faqForm;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $editTitle = '';

    #[Validate('required|string')]
    public string $editContent = '';

    #[Validate('nullable|string')]
    public string $editSummary = '';

    public function mount(Agent $agent): void
    {
        $this->agent = $agent;
    }

    protected function item(int $id): KnowledgeItem
    {
        return $this->agent->knowledgeItems()->findOrFail($id);
    }

    public function edit(int $id): void
    {
        $item = $this->item($id);
        Gate::authorize('update', $item);

        $this->editingId = $item->id;
        $this->editTitle = $item->title;
        $this->editContent = $item->content;
        $this->editSummary = (string) $item->summary;

        Flux::modal('edit-knowledge-item')->show();
    }

    public function saveEdit(CurationService $curationService): void
    {
        $item = $this->item($this->editingId);
        Gate::authorize('update', $item);

        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editContent' => 'required|string',
            'editSummary' => 'nullable|string',
        ]);

        $curationService->update($item, [
            'title' => $this->editTitle,
            'content' => $this->editContent,
            'summary' => $this->editSummary,
        ]);

        $this->reset('editingId', 'editTitle', 'editContent', 'editSummary');
        unset($this->groupedItems);

        Flux::modal('edit-knowledge-item')->close();
        Flux::toast(variant: 'success', text: __('Item atualizado.'));
    }

    public function approve(int $id, CurationService $curationService): void
    {
        $item = $this->item($id);
        Gate::authorize('curate', $item);

        $curationService->approve($item, auth()->user());
        unset($this->groupedItems);
    }

    public function reject(int $id, CurationService $curationService): void
    {
        $item = $this->item($id);
        Gate::authorize('curate', $item);

        $curationService->reject($item);
        unset($this->groupedItems);
    }

    public function remove(int $id, CurationService $curationService): void
    {
        $item = $this->item($id);
        Gate::authorize('delete', $item);

        $curationService->remove($item);
        unset($this->groupedItems);
    }

    public function publishApproved(PublishingService $publishingService): void
    {
        abort_unless(
            auth()->user()->organizationRole($this->agent->organization) === Role::Admin,
            403,
        );

        $publishingService->publishApproved($this->agent);

        Flux::toast(variant: 'success', text: __('Publicando itens aprovados.'));
    }

    public function createManualFaq(CurationService $curationService): void
    {
        abort_unless(
            auth()->user()->organizationRole($this->agent->organization) === Role::Admin,
            403,
        );

        $this->faqForm->validate();

        $curationService->createManualFaq($this->agent, auth()->user(), $this->faqForm->all());

        $this->faqForm->reset();
        unset($this->groupedItems);

        Flux::modal('manual-faq')->close();
        Flux::toast(variant: 'success', text: __('FAQ criada.'));
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
    <x-page-header :title="__('Curadoria')" :description="$agent->name">
        <x-slot:actions>
            <flux:button icon="cloud-arrow-up" wire:click="publishApproved" data-test="publish-approved">{{ __('Publicar aprovados') }}</flux:button>
            <flux:modal.trigger name="manual-faq">
                <flux:button variant="primary" icon="plus" data-test="manual-faq-trigger">{{ __('FAQ manual') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

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

                        <div class="mt-3 flex flex-wrap gap-2">
                            <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $item->id }})">{{ __('Editar') }}</flux:button>
                            <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $item->id }})">{{ __('Aprovar') }}</flux:button>
                            <flux:button size="sm" icon="x-mark" wire:click="reject({{ $item->id }})">{{ __('Rejeitar') }}</flux:button>
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="remove({{ $item->id }})">{{ __('Remover') }}</flux:button>
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

    <flux:modal name="manual-faq" class="max-w-lg">
        <form wire:submit="createManualFaq" class="space-y-4">
            <flux:heading size="lg">{{ __('Nova FAQ manual') }}</flux:heading>
            <flux:subheading>{{ __('Criada já aprovada, pronta para publicação.') }}</flux:subheading>

            <flux:input wire:model="faqForm.title" :label="__('Pergunta / Título')" data-test="faq-title" />
            @error('faqForm.title') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="faqForm.content" :label="__('Resposta')" rows="5" data-test="faq-content" />
            @error('faqForm.content') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="faqForm.summary" :label="__('Resumo')" rows="2" data-test="faq-summary" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="faq-save">{{ __('Criar FAQ') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-knowledge-item" class="max-w-lg">
        <form wire:submit="saveEdit" class="space-y-4">
            <flux:heading size="lg">{{ __('Editar item') }}</flux:heading>

            <flux:input wire:model="editTitle" :label="__('Título')" data-test="edit-title" />
            @error('editTitle') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="editContent" :label="__('Conteúdo')" rows="5" data-test="edit-content" />
            @error('editContent') <flux:error :message="$message" /> @enderror

            <flux:textarea wire:model="editSummary" :label="__('Resumo')" rows="2" data-test="edit-summary" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="edit-save">{{ __('Salvar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
