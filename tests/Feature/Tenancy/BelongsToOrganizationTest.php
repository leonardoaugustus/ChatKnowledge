<?php

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TenantResource extends Model
{
    use BelongsToOrganization;

    protected $table = 'tenant_resources';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('tenant_resources', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organization_id');
        $table->string('name');
        $table->timestamps();
    });
});

it('auto-fills organization_id on create', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $resource = TenantResource::create(['name' => 'Example']);

    expect($resource->organization_id)->toBe($user->current_organization_id);
});

it('scopes queries to the active organization', function () {
    $userA = User::factory()->create();
    $this->actingAs($userA);
    $resourceA = TenantResource::create(['name' => 'A']);

    $userB = User::factory()->create();
    $this->actingAs($userB);
    TenantResource::create(['name' => 'B']);

    expect(TenantResource::pluck('name')->all())->toBe(['B']);

    $this->actingAs($userA);

    expect(TenantResource::pluck('name')->all())->toBe(['A'])
        ->and(TenantResource::find($resourceA->id))->not->toBeNull();
});

it('never returns records from another organization', function () {
    $userA = User::factory()->create();
    $this->actingAs($userA);
    $resourceA = TenantResource::create(['name' => 'A']);

    $userB = User::factory()->create();
    $this->actingAs($userB);

    expect(TenantResource::find($resourceA->id))->toBeNull()
        ->and(TenantResource::count())->toBe(0);
});
