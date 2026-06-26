<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Gateway\FakeStoreGateway;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Stores;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Arch');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Attach the user to the organization with the given role and make it active.
 */
function withActiveOrganization(User $user, Organization $organization, Role $role = Role::Admin): User
{
    if (! $user->belongsToOrganization($organization)) {
        $organization->members()->attach($user, ['role' => $role->value]);
    }

    $user->switchOrganization($organization);

    return $user;
}

/**
 * Authenticate as an Admin (manager) of the given or a freshly created organization.
 */
function actingAsManager(?Organization $organization = null): User
{
    $user = User::factory()->create();

    if ($organization) {
        withActiveOrganization($user, $organization, Role::Admin);
    }

    test()->actingAs($user);

    return $user;
}

/**
 * Authenticate as a Colaborador of the given or a freshly created organization.
 */
function actingAsCollaborator(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $user = withActiveOrganization(User::factory()->create(), $organization, Role::Colaborador);

    test()->actingAs($user);

    return $user;
}

/**
 * Bind the Laravel AI SDK fake for the given agent class.
 *
 * @param  class-string  $agent
 * @param  Closure|array<int, mixed>  $responses
 */
function fakeAi(string $agent, Closure|array $responses = []): FakeTextGateway
{
    return $agent::fake($responses);
}

/**
 * Bind the Laravel AI SDK vector store fake (file operations are faked too).
 *
 * @param  Closure|array<int, mixed>  $responses
 */
function fakeVectorStore(Closure|array $responses = []): FakeStoreGateway
{
    return Stores::fake($responses);
}
