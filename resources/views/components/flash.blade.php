{{-- Renders session flash messages as Flux callouts. Drop <x-flash /> near the
     top of a page. Set flashes with session()->flash('success'|'error'|'warning'|'info', '...'). --}}
@php
    $variants = [
        'success' => ['color' => 'green', 'icon' => 'check-circle'],
        'error' => ['color' => 'red', 'icon' => 'x-circle'],
        'warning' => ['color' => 'amber', 'icon' => 'exclamation-triangle'],
        'info' => ['color' => 'blue', 'icon' => 'information-circle'],
    ];
@endphp

<div {{ $attributes->class('flex flex-col gap-2') }}>
    @foreach ($variants as $key => $variant)
        @session($key)
            <flux:callout :color="$variant['color']" :icon="$variant['icon']" inline>
                <flux:callout.text>{{ session($key) }}</flux:callout.text>
            </flux:callout>
        @endsession
    @endforeach
</div>
