<?php

use App\Data\UserOrganization;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function currentOrganization(): ?array
    {
        $organization = Auth::user()->currentOrganization;

        return $organization ? [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ] : null;
    }

    /**
     * @return Collection<int, UserOrganization>
     */
    public function organizations(): Collection
    {
        return Auth::user()->toUserOrganizations(includeCurrent: true);
    }

    public function switchOrganization(string $slug): void
    {
        $user = Auth::user();

        abort_unless(
            $user->belongsToOrganization($organization = Organization::where('slug', $slug)->firstOrFail()),
            403
        );

        $currentOrganizationSlug = $user->currentOrganization?->slug;

        $user->switchOrganization($organization);

        if (! request()->header('Referer')) {
            $this->redirectRoute('dashboard', ['current_organization' => $organization->slug], navigate: true);

            return;
        }

        if (! $currentOrganizationSlug) {
            $this->redirect(request()->header('Referer'), navigate: true);

            return;
        }

        $redirectTo = $this->replaceCurrentOrganizationInReferer(
            request()->header('Referer'),
            $currentOrganizationSlug,
            $organization->slug,
        );

        $this->redirect($redirectTo ?? request()->header('Referer'), navigate: true);
    }

    protected function replaceCurrentOrganizationInReferer(string $referer, string $currentOrganizationSlug, string $newOrganizationSlug): ?string
    {
        $redirectTo = preg_replace(
            '#/'.preg_quote($currentOrganizationSlug, '#').'(?=/|\?|$)#',
            '/'.$newOrganizationSlug,
            $referer,
            1,
        );

        return preg_replace(
            '#([?&]current_organization=)'.preg_quote($currentOrganizationSlug, '#').'(?=&|$)#',
            '$1'.$newOrganizationSlug,
            $redirectTo ?? $referer,
            1,
        );
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="start">
        <flux:button variant="ghost" class="group w-full justify-start in-data-flux-sidebar-collapsed-desktop:justify-center" data-test="organization-switcher-trigger">
            <flux:icon name="users" class="hidden size-4 in-data-flux-sidebar-collapsed-desktop:block" />
            <span class="truncate font-semibold in-data-flux-sidebar-collapsed-desktop:hidden">{{ $this->currentOrganization()['name'] ?? __('Select organization') }}</span>
            <flux:icon
                name="chevrons-up-down"
                variant="micro"
                class="ms-auto size-4 in-data-flux-sidebar-collapsed-desktop:hidden"
            />
        </flux:button>

        <flux:menu class="min-w-56">
            <flux:menu.heading>{{ __('Organizations') }}</flux:menu.heading>

            @foreach ($this->organizations() as $organization)
                <flux:menu.item
                    wire:click="switchOrganization('{{ $organization->slug }}')"
                    class="cursor-pointer"
                    data-test="organization-switcher-item"
                >
                    <div class="flex w-full items-center justify-between">
                        <span>{{ $organization->name }}</span>
                        @if ($organization->isCurrent)
                            <flux:icon name="check" class="size-4" />
                        @endif
                    </div>
                </flux:menu.item>
            @endforeach

            <flux:menu.separator />

            <flux:modal.trigger name="create-organization-switcher">
                <flux:menu.item icon="plus" class="cursor-pointer" data-test="organization-switcher-new-organization">
                    {{ __('New organization') }}
                </flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>
</div>
