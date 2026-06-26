@props(['agent'])

@php
    $tabs = [
        ['route' => 'agents.edit', 'label' => __('Builder'), 'icon' => 'adjustments-horizontal'],
        ['route' => 'training.upload', 'label' => __('Treinamento'), 'icon' => 'document-arrow-up'],
        ['route' => 'curation.queue', 'label' => __('Curadoria'), 'icon' => 'clipboard-document-check'],
        ['route' => 'chat.show', 'label' => __('Chat'), 'icon' => 'chat-bubble-left-right'],
        ['route' => 'tools.show', 'label' => __('Tools'), 'icon' => 'wrench-screwdriver'],
    ];
@endphp

<nav {{ $attributes->class('mt-4 flex flex-wrap gap-1 border-b border-zinc-200 dark:border-zinc-700') }}>
    @foreach ($tabs as $tab)
        @php($active = request()->routeIs($tab['route']))
        <a
            href="{{ route($tab['route'], ['agent' => $agent]) }}"
            wire:navigate
            @class([
                '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium',
                'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300' => $active,
                'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200' => ! $active,
            ])
        >
            <flux:icon :name="$tab['icon']" class="size-4" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
