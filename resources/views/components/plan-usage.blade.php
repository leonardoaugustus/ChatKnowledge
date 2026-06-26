@props([
    'plan' => 'Free',
    'label' => 'Perguntas',
    'used' => 0,
    'limit' => null,
])

@php
    $hasLimit = filled($limit) && $limit > 0;
    $percent = $hasLimit ? min(100, (int) round(($used / $limit) * 100)) : 0;
    $near = $percent >= 80;
@endphp

<div {{ $attributes->class('rounded-card border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800 in-data-flux-sidebar-collapsed-desktop:hidden') }}>
    <div class="mb-2 flex items-center justify-between gap-2">
        <span class="text-2xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            {{ __('Plano') }} {{ $plan }}
        </span>

        @if ($hasLimit)
            <span class="text-2xs font-medium {{ $near ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                {{ $percent }}%
            </span>
        @endif
    </div>

    @if ($hasLimit)
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
            <div
                class="h-full rounded-full {{ $near ? 'bg-amber-500' : 'bg-brand-500' }}"
                style="width: {{ $percent }}%"
            ></div>
        </div>

        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            {{ number_format($used) }} / {{ number_format($limit) }} {{ $label }}
        </p>
    @else
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Uso ilimitado') }}</p>
    @endif
</div>
