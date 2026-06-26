<?php

use App\Data\UserOrganization;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Organization $organization;

    public string $deleteName = '';

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
    }

    #[Computed]
    public function deleteConfirmLabel(): string
    {
        return __('Type ":name" to confirm', ['name' => $this->organization->name]);
    }

    public function deleteOrganization(): void
    {
        Gate::authorize('delete', $this->organization);

        $validated = $this->validate([
            'deleteName' => ['required', 'string'],
        ]);

        if ($validated['deleteName'] !== $this->organization->name) {
            $this->addError('deleteName', __('The organization name does not match.'));

            return;
        }

        $user = Auth::user();

        $fallbackOrganization = $user->isCurrentOrganization($this->organization)
            ? $user->fallbackOrganization($this->organization)
            : null;

        DB::transaction(function () use ($user) {
            User::where('current_organization_id', $this->organization->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchOrganization($affectedUser->personalOrganization()));

            $this->organization->invitations()->delete();
            $this->organization->memberships()->delete();
            $this->organization->delete();
        });

        if ($fallbackOrganization) {
            $user->switchOrganization($fallbackOrganization);
        }

        Flux::toast(variant: 'success', text: __('Organization deleted.'));

        $this->redirectRoute('organizations.index', navigate: true);
    }

    /**
     * @return Collection<int, UserOrganization>
     */
    #[Computed]
    public function otherOrganizations(): Collection
    {
        return Auth::user()->toUserOrganizations();
    }
}; ?>

<flux:modal name="delete-organization" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="deleteOrganization" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure?') }}</flux:heading>
            <flux:subheading>
                {{ __('This action cannot be undone. This will permanently delete the organization ":name".', ['name' => $organization->name]) }}
            </flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:input wire:model="deleteName" :label="$this->deleteConfirmLabel" required data-test="delete-organization-name" />
        </div>

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" data-test="delete-organization-confirm">
                {{ __('Delete organization') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
