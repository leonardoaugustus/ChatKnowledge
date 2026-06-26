<?php

use App\Enums\UsageType;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Billing\UsageRecorder;

it('records a usage row per AI question', function () {
    $organization = Organization::factory()->create();
    $recorder = app(UsageRecorder::class);

    $recorder->record($organization, UsageType::Question);
    $recorder->record($organization, UsageType::Question);

    $records = UsageRecord::withoutGlobalScopes()
        ->where('organization_id', $organization->id)
        ->where('type', UsageType::Question->value)
        ->get();

    expect($records)->toHaveCount(2)
        ->and($records->first()->type)->toBe(UsageType::Question)
        ->and($records->first()->quantity)->toBe(1)
        ->and($records->first()->organization_id)->toBe($organization->id);
});

it('scopes usage to the organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $recorder = app(UsageRecorder::class);

    $recorder->record($orgA, UsageType::Extraction);
    $recorder->record($orgB, UsageType::Question);

    $user = withActiveOrganization(User::factory()->create(), $orgB);
    $this->actingAs($user);

    // Global scope from BelongsToOrganization restricts reads to the active org.
    expect(UsageRecord::count())->toBe(1)
        ->and(UsageRecord::first()->organization_id)->toBe($orgB->id)
        ->and(UsageRecord::first()->type)->toBe(UsageType::Question);
});
