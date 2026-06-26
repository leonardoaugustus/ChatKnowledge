<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Notifications\Organizations\OrganizationInvitation as OrganizationInvitationNotification;
use App\Rules\UniqueOrganizationInvitation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Organization $organization;

    public string $inviteEmail = '';

    public string $inviteRole = Role::Colaborador->value;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function createInvitation(): void
    {
        Gate::authorize('inviteMember', $this->organization);

        $validated = $this->validate([
            'inviteEmail' => ['required', 'string', 'email', 'max:255', new UniqueOrganizationInvitation($this->organization)],
            'inviteRole' => ['required', 'string', Rule::enum(Role::class)],
        ]);

        $invitation = $this->organization->invitations()->create([
            'email' => $validated['inviteEmail'],
            'role' => Role::from($validated['inviteRole']),
            'invited_by' => Auth::id(),
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation));

        $this->reset('inviteEmail', 'inviteRole');
        $this->dispatch('close-modal', name: 'invite-member');

        Flux::toast(variant: 'success', text: __('Invitation sent.'));

        $this->redirectRoute('organizations.edit', ['organization' => $this->organization->slug], navigate: true);
    }

    #[Computed]
    public function availableRoles(): array
    {
        return Role::assignable();
    }
}; ?>

<flux:modal name="invite-member" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="createInvitation" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Invite a organization member') }}</flux:heading>
            <flux:subheading>{{ __('Send an invitation to join this organization.') }}</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:input wire:model="inviteEmail" type="email" :label="__('Email address')" required data-test="invite-email" />

            <flux:select wire:model="inviteRole" :label="__('Role')" data-test="invite-role">
                @foreach ($this->availableRoles as $role)
                    <flux:select.option value="{{ $role['value'] }}">{{ $role['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" data-test="invite-submit">{{ __('Send invitation') }}</flux:button>
        </div>
    </form>
</flux:modal>
