<?php

use App\Actions\Organizations\CreateOrganization;
use App\Rules\OrganizationName;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public string $organizationName = '';

    public function createOrganization(CreateOrganization $createOrganization): void
    {
        $validated = $this->validate([
            'organizationName' => ['required', 'string', 'max:255', new OrganizationName],
        ]);

        $organization = $createOrganization->handle(Auth::user(), $validated['organizationName']);

        $this->dispatch('close-modal', name: 'create-organization-switcher');

        $this->reset('organizationName');

        Flux::toast(variant: 'success', text: __('Organization created.'));

        $this->redirectRoute('organizations.edit', ['organization' => $organization->slug], navigate: true);
    }
}; ?>

<flux:modal name="create-organization-switcher" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="createOrganization" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Create a new organization') }}</flux:heading>
            <flux:subheading>{{ __('Give your organization a name to get started.') }}</flux:subheading>
        </div>

        <flux:input wire:model="organizationName" :label="__('Organization name')" type="text" required autofocus data-test="switcher-create-organization-name" />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit" data-test="switcher-create-organization-submit">
                {{ __('Create organization') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
