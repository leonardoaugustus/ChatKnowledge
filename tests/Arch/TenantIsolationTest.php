<?php

use App\Models\Agent;
use App\Models\AgentTool;
use App\Models\AiLog;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\KnowledgeItemVersion;
use App\Models\Message;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * Every tenant-owned model that is covered by an isolation test below.
 *
 * @return array<int, class-string>
 */
function testedTenantModels(): array
{
    return [
        Agent::class,
        AgentTool::class,
        AiLog::class,
        Conversation::class,
        Document::class,
        KnowledgeItem::class,
        KnowledgeItemVersion::class,
        Message::class,
        UsageRecord::class,
    ];
}

/**
 * Discover every model that uses the BelongsToOrganization trait.
 *
 * @return array<int, class-string>
 */
function tenantOwnedModels(): array
{
    return collect(File::files(app_path('Models')))
        ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->filter(fn (string $class) => class_exists($class)
            && in_array(BelongsToOrganization::class, class_uses_recursive($class), true))
        ->values()
        ->all();
}

it('covers every tenant-owned model with an isolation test', function () {
    expect(tenantOwnedModels())->toEqualCanonicalizing(testedTenantModels());
});

it('never leaks records across organizations', function (string $model) {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $foreign = $model::factory()->for($orgB)->create();

    $user = User::factory()->create();
    withActiveOrganization($user, $orgA);
    $this->actingAs($user);

    expect($model::find($foreign->id))->toBeNull()
        ->and($model::count())->toBe(0);
})->with(testedTenantModels());
