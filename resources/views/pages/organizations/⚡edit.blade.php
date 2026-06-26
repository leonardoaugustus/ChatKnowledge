<?php

use App\Data\OrganizationPermissions;
use App\Enums\Role;
use App\Models\Organization;
use App\Rules\OrganizationName;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Organization $organizationModel;

    public string $organizationName = '';

    public array $organizationData = [];

    public array $members = [];

    public array $invitations = [];

    public array $availableRoles = [];

    public bool $isCurrentOrganization = false;

    public function mount(Organization $organization): void
    {
        $this->organizationModel = $organization;
        $this->organizationName = $organization->name;

        $this->populateOrganizationData();
    }

    public function updateOrganization(): void
    {
        Gate::authorize('update', $this->organizationModel);

        $validated = $this->validate([
            'organizationName' => ['required', 'string', 'max:255', new OrganizationName],
        ]);

        $organization = DB::transaction(function () use ($validated) {
            $organization = Organization::whereKey($this->organizationModel->id)->lockForUpdate()->firstOrFail();

            $organization->update(['name' => $validated['organizationName']]);

            return $organization;
        });

        $this->organizationModel = $organization;

        $this->populateOrganizationData();

        Flux::toast(variant: 'success', text: __('Organization updated.'));

        $this->redirectRoute('organizations.edit', ['organization' => $this->organizationModel->fresh()->slug], navigate: true);
    }

    public function updateMember(int $userId, string $role): void
    {
        Gate::authorize('updateMember', $this->organizationModel);

        $validated = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::enum(Role::class)],
        ])->validate();

        $this->organizationModel->memberships()
            ->where('user_id', $userId)
            ->firstOrFail()
            ->update(['role' => Role::from($validated['role'])]);

        $this->populateOrganizationData();

        Flux::toast(variant: 'success', text: __('Member role updated.'));
    }

    private function populateOrganizationData(): void
    {
        $user = Auth::user();

        $organization = $this->organizationModel->fresh();

        $this->organizationData = [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'is_personal' => $organization->is_personal,
        ];

        $this->members = $organization->members()->get()->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->avatar ?? null,
            'initials' => $member->initials(),
            'role' => $member->pivot->role->value,
            'role_label' => $member->pivot->role->label(),
        ])->toArray();

        $this->invitations = $organization->invitations()
            ->whereNull('accepted_at')
            ->get()
            ->map(fn ($invitation) => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'created_at' => $invitation->created_at->toISOString(),
            ])->toArray();

        $this->availableRoles = Role::assignable();

        $this->isCurrentOrganization = $user->isCurrentOrganization($organization);
    }

    public function render()
    {
        $organizationName = $this->organizationData['name'] ?? $this->organizationModel->name;

        $title = $this->permissions->canUpdateOrganization
            ? __('Edit :name', ['name' => $organizationName])
            : __('View :name', ['name' => $organizationName]);

        return $this->view()->title($title);
    }

    #[Computed]
    public function permissions(): OrganizationPermissions
    {
        return Auth::user()->toOrganizationPermissions($this->organizationModel);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Organizations') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Organizations')" :subheading="__('Manage your organization settings')">
        <div class="space-y-10">
            <div class="space-y-6">
                @if ($this->permissions->canUpdateOrganization)
                    <div class="space-y-4">
                        <form wire:submit="updateOrganization" class="space-y-6">
                            <flux:input wire:model="organizationName" :label="__('Organization name')" required data-test="organization-name-input" />

                            <flux:button variant="primary" type="submit" data-test="organization-save-button">
                                {{ __('Save') }}
                            </flux:button>
                        </form>
                    </div>
                @else
                    <div>
                        <flux:heading>{{ $organizationData['name'] }}</flux:heading>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading>{{ __('Organization members') }}</flux:heading>
                        @if ($this->permissions->canAddMember || $this->permissions->canUpdateMember || $this->permissions->canRemoveMember)
                            <flux:subheading>{{ __('Manage who belongs to this organization') }}</flux:subheading>
                        @endif
                    </div>

                    @if ($this->permissions->canCreateInvitation)
                        <flux:modal.trigger name="invite-member">
                            <flux:button variant="primary" icon="user-plus" data-test="invite-member-button">
                                {{ __('Invite member') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>

                <div class="space-y-3">
                    @foreach ($members as $member)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="member-row">
                            <div class="flex items-center gap-4">
                                <flux:avatar :name="$member['name']" :initials="$member['initials']" />
                                <div>
                                    <div class="font-medium">{{ $member['name'] }}</div>
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $member['email'] }}</flux:text>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($member['role'] !== Role::Admin->value && $this->permissions->canUpdateMember)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="outline" size="sm" icon:trailing="chevron-down" data-test="member-role-trigger">
                                            {{ $member['role_label'] }}
                                        </flux:button>
                                        <flux:menu>
                                            @foreach ($availableRoles as $role)
                                                <flux:menu.item
                                                    as="button"
                                                    type="button"
                                                    wire:click="updateMember({{ $member['id'] }}, '{{ $role['value'] }}')"
                                                    data-test="member-role-option"
                                                >
                                                    {{ $role['label'] }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:badge color="zinc">{{ $member['role_label'] }}</flux:badge>
                                @endif

                                @if ($member['role'] !== Role::Admin->value && $this->permissions->canRemoveMember)
                                    <flux:modal.trigger name="remove-member-{{ $member['id'] }}">
                                        <flux:tooltip :content="__('Remove member')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                data-test="member-remove-button"
                                            />
                                        </flux:tooltip>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        </div>

                        @if ($member['role'] !== Role::Admin->value && $this->permissions->canRemoveMember)
                            <livewire:pages::organizations.remove-member-modal
                                :organization="$organizationModel"
                                :member-id="$member['id']"
                                :member-name="$member['name']"
                                :modal-name="'remove-member-'.$member['id']"
                                :key="'remove-member-modal-'.$member['id']"
                            />
                        @endif
                    @endforeach
                </div>
            </div>

            @if (count($invitations) > 0)
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Pending invitations') }}</flux:heading>
                        <flux:subheading>{{ __('Invitations that have not been accepted yet') }}</flux:subheading>
                    </div>

                    <div class="space-y-3">
                        @foreach ($invitations as $invitation)
                            <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="invitation-row">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="envelope" class="text-zinc-500" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $invitation['email'] }}</div>
                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $invitation['role_label'] }}</flux:text>
                                    </div>
                                </div>

                                @if ($this->permissions->canCancelInvitation)
                                    <flux:modal.trigger name="cancel-invitation-{{ $invitation['code'] }}">
                                        <flux:tooltip :content="__('Cancel invitation')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                data-test="invitation-cancel-button"
                                            />
                                        </flux:tooltip>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                            @if ($this->permissions->canCancelInvitation)
                                <livewire:pages::organizations.cancel-invitation-modal
                                    :organization="$organizationModel"
                                    :invitation-code="$invitation['code']"
                                    :invitation-email="$invitation['email']"
                                    :modal-name="'cancel-invitation-'.$invitation['code']"
                                    :key="'cancel-invitation-modal-'.$invitation['code']"
                                />
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->permissions->canDeleteOrganization && ! $organizationData['is_personal'])
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Delete organization') }}</flux:heading>
                        <flux:subheading>{{ __('Permanently delete your organization') }}</flux:subheading>
                    </div>

                    <div class="space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-200/10 dark:bg-red-900/20 dark:text-red-100">
                        <div>
                            <p class="font-medium">{{ __('Warning') }}</p>
                            <p class="text-sm">{{ __('Please proceed with caution, this cannot be undone.') }}</p>
                        </div>

                        <flux:modal.trigger name="delete-organization">
                            <flux:button variant="danger" data-test="delete-organization-button">
                                {{ __('Delete organization') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>

    @if ($this->permissions->canCreateInvitation)
        <livewire:pages::organizations.invite-member-modal :organization="$organizationModel" />
    @endif

    @if ($this->permissions->canDeleteOrganization && ! $organizationData['is_personal'])
        <livewire:pages::organizations.delete-organization-modal :organization="$organizationModel" />
    @endif
</section>
