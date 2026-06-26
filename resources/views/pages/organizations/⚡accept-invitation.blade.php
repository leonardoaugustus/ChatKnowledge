<?php

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Organizations')] class extends Component {
    public OrganizationInvitation $invitation;

    public function mount(OrganizationInvitation $invitation): void
    {
        $this->invitation = $invitation;

        $this->acceptInvitation();
    }

    public function acceptInvitation(): void
    {
        $user = Auth::user();

        $this->validateInvitation($user, $this->invitation);

        DB::transaction(function () use ($user) {
            $organization = $this->invitation->organization;

            $membership = $organization->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $this->invitation->role]
            );

            $this->invitation->update(['accepted_at' => now()]);

            $user->switchOrganization($organization);
        });

        session()->flash('organization-invitation-accepted', true);

        $this->redirectRoute('dashboard', navigate: true);
    }

    private function validateInvitation(User $user, OrganizationInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation has already been accepted.')],
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation has expired.')],
            ]);
        }

        if (Str::lower($invitation->email) !== Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation was sent to a different email address.')],
            ]);
        }
    }
}; ?>

<div></div>
