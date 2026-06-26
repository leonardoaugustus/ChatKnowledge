{{-- Guest layout for unauthenticated / public screens. Wraps the starter-kit
     auth shell so pages may reference `layouts::guest` per the design system. --}}
<x-layouts::auth :title="$title ?? null">
    {{ $slot }}
</x-layouts::auth>
