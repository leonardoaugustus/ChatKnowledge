<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Gateway\FakeStoreGateway;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Promptable;
use Laravel\Ai\Stores;

class HelperProbeAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a probe used in tests.';
    }
}

it('authenticates as a manager of the active organization', function () {
    $user = actingAsManager();

    expect(auth()->id())->toBe($user->id)
        ->and($user->currentOrganization)->not->toBeNull()
        ->and($user->ownsOrganization($user->currentOrganization))->toBeTrue();
});

it('authenticates as a manager of a given organization', function () {
    $organization = Organization::factory()->create();

    $user = actingAsManager($organization);

    expect($user->current_organization_id)->toBe($organization->id)
        ->and($user->organizationRole($organization))->toBe(Role::Admin);
});

it('authenticates as a collaborator of the active organization', function () {
    $user = actingAsCollaborator();

    expect(auth()->id())->toBe($user->id)
        ->and($user->organizationRole($user->currentOrganization))->toBe(Role::Colaborador);
});

it('attaches a user to an organization and makes it active', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    withActiveOrganization($user, $organization, Role::Colaborador);

    expect($user->belongsToOrganization($organization))->toBeTrue()
        ->and($user->current_organization_id)->toBe($organization->id)
        ->and($user->organizationRole($organization))->toBe(Role::Colaborador);
});

it('binds the AI agent fake', function () {
    $fake = fakeAi(HelperProbeAgent::class, ['Faked answer.']);

    expect($fake)->toBeInstanceOf(FakeTextGateway::class);

    HelperProbeAgent::assertNeverPrompted();

    (new HelperProbeAgent)->prompt('Question?');

    HelperProbeAgent::assertPrompted('Question?');
});

it('binds the vector store fake', function () {
    $fake = fakeVectorStore();

    expect($fake)->toBeInstanceOf(FakeStoreGateway::class);

    Stores::create('Knowledge Base');

    Stores::assertCreated('Knowledge Base');
});
