@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="min-w-0">
        <flux:heading size="xl" class="truncate">{{ $title }}</flux:heading>

        @if ($description)
            <flux:subheading class="mt-1">{{ $description }}</flux:subheading>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
