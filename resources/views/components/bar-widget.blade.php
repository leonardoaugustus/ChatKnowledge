@props([
    'title' => null,
    'items' => [],
    'color' => 'brand',
    'emptyText' => 'Sem dados para exibir.',
])

@php
    $items = collect($items)->map(fn ($item) => [
        'label' => $item['label'] ?? '',
        'value' => (float) ($item['value'] ?? 0),
        'color' => $item['color'] ?? $color,
    ]);

    $max = (float) $items->max('value') ?: 1;
@endphp

<div {{ $attributes->class('rounded-card border border-zinc-200 p-4 dark:border-zinc-700') }}>
    @if ($title)
        <flux:heading size="sm" class="mb-4">{{ $title }}</flux:heading>
    @endif

    @if ($items->isEmpty())
        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $emptyText }}</flux:text>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($items as $item)
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                        <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $item['label'] }}</span>
                        <span class="shrink-0 font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($item['value']) }}</span>
                    </div>

                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div
                            class="h-full rounded-full bg-{{ $item['color'] }}-500"
                            style="width: {{ max(2, round(($item['value'] / $max) * 100)) }}%"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
