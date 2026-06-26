<?php

use App\Actions\Organizations\CreateOrganization;
use App\Data\UserOrganization;
use App\Models\Organization;
use App\Rules\OrganizationName;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Organizations')] class extends Component {
    public string $name = '';

    public function createOrganization(CreateOrganization $createOrganization): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new OrganizationName],
        ]);

        $organization = $createOrganization->handle(Auth::user(), $validated['name']);

        $this->dispatch('close-modal', name: 'create-organization');

        $this->reset('name');

        Flux::toast(variant: 'success', text: __('Organization created.'));

        $this->redirectRoute('organizations.edit', ['organization' => $organization->slug], navigate: true);
    }

    public function leaveOrganization(int $organizationId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $user = Auth::user();

        Gate::authorize('leave', $organization);

        $fallbackOrganization = $user->isCurrentOrganization($organization)
            ? $user->fallbackOrganization($organization)
            : null;

        $organization->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($fallbackOrganization) {
            $user->switchOrganization($fallbackOrganization);
        }

        $this->dispatch('close-modal', name: "leave-organization-{$organizationId}");

        Flux::toast(variant: 'success', text: __('You left the organization ":name"', ['name' => $organization->name]));

        $this->redirectRoute('organizations.index', navigate: true);
    }

    /**
     * @return Collection<int, UserOrganization>
     */
    #[Computed]
    public function organizations(): Collection
    {
        return Auth::user()->toUserOrganizations(includeCurrent: true);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Organizations') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Organizations')" :subheading="__('Manage your organizations and organization memberships')">
        <div class="flex items-center justify-end">
            <flux:modal.trigger name="create-organization">
                <flux:button variant="primary" icon="plus" x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-organization')" data-test="organizations-new-organization-button">
                    {{ __('New organization') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <div class="mt-6 space-y-3">
            @forelse ($this->organizations as $organization)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="organization-row">
                    <div class="flex items-center gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $organization->name }}</span>
                                @if ($organization->isPersonal)
                                    <flux:badge color="zinc">{{ __('Personal') }}</flux:badge>
                                @endif
                            </div>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $organization->roleLabel }}</flux:text>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        @if (! $organization->isPersonal && $organization->role !== 'owner')
                            <flux:modal.trigger :name="'leave-organization-'.$organization->id">
                                <flux:tooltip :content="__('Leave organization')">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="arrow-right-start-on-rectangle"
                                        x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'leave-organization-{{ $organization->id }}')"
                                        data-test="organization-leave-button"
                                    />
                                </flux:tooltip>
                            </flux:modal.trigger>
                        @endif

                        <flux:tooltip :content="$organization->role === 'member' ? __('View organization') : __('Edit organization')">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                :icon="$organization->role === 'member' ? 'eye' : 'pencil'"
                                :href="route('organizations.edit', $organization->slug)"
                                wire:navigate
                                :data-test="$organization->role === 'member' ? 'organization-view-button' : 'organization-edit-button'"
                            />
                        </flux:tooltip>
                    </div>
                </div>

                @if (! $organization->isPersonal && $organization->role !== 'owner')
                    <flux:modal :name="'leave-organization-'.$organization->id" focusable class="max-w-lg">
                        <form wire:submit="leaveOrganization({{ $organization->id }})" class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Leave organization') }}</flux:heading>
                                <flux:subheading>
                                    {{ __('Are you sure you want to leave :name?', ['name' => $organization->name]) }}
                                </flux:subheading>
                            </div>

                            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                                <flux:modal.close>
                                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>

                                <flux:button variant="danger" type="submit" data-test="leave-organization-confirm">
                                    {{ __('Leave organization') }}
                                </flux:button>
                            </div>
                        </form>
                    </flux:modal>
                @endif
            @empty
                <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    {{ __('You don\'t belong to any organizations yet.') }}
                </flux:text>
            @endforelse
        </div>
    </x-pages::settings.layout>

    <flux:modal name="create-organization" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="createOrganization" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create a new organization') }}</flux:heading>
                <flux:subheading>{{ __('Give your organization a name to get started.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Organization name')" type="text" required autofocus data-test="create-organization-name" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="create-organization-submit">
                    {{ __('Create organization') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
