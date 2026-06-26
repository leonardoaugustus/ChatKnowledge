@props([
    'name',
    'title' => null,
    'description' => null,
    'confirm' => null,
    'confirmLabel' => 'Confirmar',
    'cancelLabel' => 'Cancelar',
    'variant' => 'danger',
    'icon' => 'exclamation-triangle',
])

{{-- Reusable confirmation dialog. Open it from a trigger:

       <flux:modal.trigger name="delete-agent">
           <flux:button variant="danger">Excluir</flux:button>
       </flux:modal.trigger>

     and wire the confirm action via the `confirm` prop (a Livewire method name)
     or by passing custom body content through the default slot. --}}
<flux:modal :name="$name" class="max-w-md" {{ $attributes }}>
    <div class="space-y-6">
        <div class="flex gap-4">
            <div @class([
                'flex size-10 shrink-0 items-center justify-center rounded-full',
                'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' => $variant === 'danger',
                'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' => $variant === 'warning',
                'bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' => $variant === 'primary',
            ])>
                <flux:icon :name="$icon" class="size-5" />
            </div>

            <div class="min-w-0">
                @if ($title)
                    <flux:heading size="lg">{{ $title }}</flux:heading>
                @endif

                @if ($description)
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $description }}</flux:text>
                @endif

                {{ $slot }}
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ $cancelLabel }}</flux:button>
            </flux:modal.close>

            @if ($confirm)
                <flux:button :variant="$variant === 'warning' ? 'primary' : $variant" wire:click="{{ $confirm }}">
                    {{ $confirmLabel }}
                </flux:button>
            @endif
        </div>
    </div>
</flux:modal>
