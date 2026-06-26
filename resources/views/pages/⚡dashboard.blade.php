<?php

use App\Enums\CurationStatus;
use App\Enums\DocumentStatus;
use App\Enums\PublicationStatus;
use App\Enums\UsageType;
use App\Models\Agent;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\UsageRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    /**
     * Headline counts for the active organization (auto-scoped by the global
     * scope on each tenant-owned model).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'agents' => Agent::count(),
            'documents' => Document::count(),
            'processing' => Document::where('status', DocumentStatus::Processing->value)->count(),
            'pending_curation' => KnowledgeItem::where('curation_status', CurationStatus::Pending->value)->count(),
            'questions' => (int) UsageRecord::where('type', UsageType::Question->value)->sum('quantity'),
            'unanswered' => KnowledgeItem::where('metadata->gap', true)->count(),
            'pending_publication' => KnowledgeItem::where('curation_status', CurationStatus::Approved->value)
                ->where('publication_status', PublicationStatus::Unpublished->value)
                ->count(),
            'published_documents' => Document::where('status', DocumentStatus::Published->value)->count(),
            'processing_failures' => Document::where('status', DocumentStatus::Failed->value)->count(),
        ];
    }

    /**
     * Question usage per agent for the bar widget.
     *
     * @return array<int, array{label: string, value: int}>
     */
    #[Computed]
    public function usagePerAgent(): array
    {
        $totals = UsageRecord::query()
            ->selectRaw('agent_id, sum(quantity) as total')
            ->whereNotNull('agent_id')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        return Agent::whereIn('id', $totals->keys())
            ->get()
            ->map(fn (Agent $agent) => [
                'label' => $agent->name,
                'value' => (int) $totals[$agent->id],
            ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Document status breakdown for the bar widget.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    #[Computed]
    public function documentsByStatus(): Collection
    {
        $counts = Document::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(DocumentStatus::cases())
            ->map(fn (DocumentStatus $status) => [
                'label' => $status->label(),
                'value' => (int) ($counts[$status->value] ?? 0),
                'color' => $status->color(),
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values();
    }

    public function planQuestionsUsed(): int
    {
        return $this->stats()['questions'];
    }

    public function planQuestionsLimit(): ?int
    {
        $limit = config('plan.limits.questions');

        return $limit === null ? null : (int) $limit;
    }
}; ?>

<section class="w-full">
    <livewire:pages::organizations.pending-invitations-modal />

    <x-page-header :title="__('Dashboard')" :description="auth()->user()->currentOrganization?->name" />

    @php
        $cards = [
            ['key' => 'agents', 'label' => __('Agentes'), 'icon' => 'cpu-chip'],
            ['key' => 'documents', 'label' => __('Documentos'), 'icon' => 'document-text'],
            ['key' => 'processing', 'label' => __('Processando'), 'icon' => 'arrow-path'],
            ['key' => 'pending_curation', 'label' => __('Curadoria pendente'), 'icon' => 'clipboard-document-check'],
            ['key' => 'questions', 'label' => __('Perguntas'), 'icon' => 'chat-bubble-left-right'],
            ['key' => 'unanswered', 'label' => __('Não respondidas'), 'icon' => 'question-mark-circle'],
            ['key' => 'pending_publication', 'label' => __('A publicar'), 'icon' => 'cloud-arrow-up'],
            ['key' => 'published_documents', 'label' => __('Documentos publicados'), 'icon' => 'check-badge'],
            ['key' => 'processing_failures', 'label' => __('Falhas'), 'icon' => 'exclamation-triangle'],
        ];
    @endphp

    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-3">
        @foreach ($cards as $card)
            <div class="rounded-card border border-zinc-200 p-4 dark:border-zinc-700" data-test="stat-{{ $card['key'] }}">
                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                    <flux:icon :name="$card['icon']" class="size-4" />
                    <flux:text class="text-2xs font-semibold uppercase tracking-wide">{{ $card['label'] }}</flux:text>
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($this->stats[$card['key']]) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-plan-usage
            class="lg:col-span-1"
            plan="ChatKnowledge"
            label="perguntas"
            :used="$this->planQuestionsUsed()"
            :limit="$this->planQuestionsLimit()"
        />

        <x-bar-widget
            class="lg:col-span-1"
            :title="__('Uso por agente')"
            :items="$this->usagePerAgent"
            :empty-text="__('Sem uso registrado.')"
        />

        <x-bar-widget
            class="lg:col-span-1"
            :title="__('Documentos por status')"
            :items="$this->documentsByStatus->all()"
            :empty-text="__('Nenhum documento.')"
        />
    </div>
</section>
