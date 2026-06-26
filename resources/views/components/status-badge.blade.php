@props([
    'status',
    'size' => 'sm',
])

{{-- Accepts any enum exposing label(): string and color(): string
     (AgentStatus, DocumentStatus, CurationStatus, Role). --}}
<flux:badge :color="$status->color()" :size="$size" {{ $attributes }}>
    {{ $status->label() }}
</flux:badge>
