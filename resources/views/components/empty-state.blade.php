@props([
    'icon' => 'inbox',
    'heading',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center rounded-card border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700') }}>
    <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
        <flux:icon :name="$icon" class="size-6" />
    </div>

    <flux:heading size="lg" class="mt-4">{{ $heading }}</flux:heading>

    @if ($description)
        <flux:text class="mt-1 max-w-prose text-zinc-500 dark:text-zinc-400">{{ $description }}</flux:text>
    @endif

    @isset($actions)
        <div class="mt-6 flex items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
