<?php

use App\Enums\Role;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SwitchableResource extends Model
{
    use BelongsToOrganization;

    protected $table = 'switchable_resources';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('switchable_resources', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organization_id');
        $table->string('name');
        $table->timestamps();
    });
});

it('switches to an organization the user belongs to', function () {
    $user = User::factory()->create();
    $target = Organization::factory()->create();
    $target->members()->attach($user, ['role' => Role::Colaborador->value]);

    expect($user->switchOrganization($target))->toBeTrue()
        ->and($user->fresh()->current_organization_id)->toBe($target->id);
});

it('rejects switching to an organization the user does not belong to', function () {
    $user = User::factory()->create();
    $original = $user->current_organization_id;
    $foreign = Organization::factory()->create();

    expect($user->switchOrganization($foreign))->toBeFalse()
        ->and($user->fresh()->current_organization_id)->toBe($original);
});

it('re-scopes data after switching', function () {
    $user = User::factory()->create();
    $orgA = $user->currentOrganization;

    $orgB = Organization::factory()->create();
    $orgB->members()->attach($user, ['role' => Role::Admin->value]);

    $this->actingAs($user);

    $resourceA = SwitchableResource::create(['name' => 'Belongs to A']);
    expect($resourceA->organization_id)->toBe($orgA->id);

    $user->switchOrganization($orgB);

    expect(SwitchableResource::count())->toBe(0)
        ->and(SwitchableResource::find($resourceA->id))->toBeNull();

    $resourceB = SwitchableResource::create(['name' => 'Belongs to B']);
    expect($resourceB->organization_id)->toBe($orgB->id)
        ->and(SwitchableResource::pluck('name')->all())->toBe(['Belongs to B']);

    $user->switchOrganization($orgA);

    expect(SwitchableResource::pluck('name')->all())->toBe(['Belongs to A']);
});
