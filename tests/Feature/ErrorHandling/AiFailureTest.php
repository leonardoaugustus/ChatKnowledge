<?php

use App\Ai\Agents\KnowledgeExtractor;
use App\Enums\DocumentStatus;
use App\Jobs\PublishKnowledgeItem;
use App\Models\Agent;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\Ai\KnowledgeExtractionService;
use App\Services\Ai\PublishingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create();

    Storage::disk('local')->put('documents/raw.txt', 'raw material');

    $this->document = Document::factory()->for($this->organization)->for($this->agent)->create([
        'disk' => 'local',
        'path' => 'documents/raw.txt',
        'status' => DocumentStatus::Uploaded,
    ]);
});

it('surfaces a friendly message on extraction failure', function () {
    KnowledgeExtractor::fake(fn () => throw new RuntimeException('OpenAI 503 upstream connect error'));

    expect(fn () => app(KnowledgeExtractionService::class)->extract($this->document))
        ->toThrow(RuntimeException::class);

    $document = $this->document->fresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toBe(KnowledgeExtractionService::FAILURE_MESSAGE);
});

it('logs but does not display raw provider errors', function () {
    Log::spy();

    KnowledgeExtractor::fake(fn () => throw new RuntimeException('OpenAI 503 upstream connect error'));

    try {
        app(KnowledgeExtractionService::class)->extract($this->document);
    } catch (RuntimeException) {
        // expected
    }

    // The raw provider error is logged...
    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context) => $context['message'] === 'OpenAI 503 upstream connect error')
        ->once();

    // ...but never shown to the user.
    expect($this->document->fresh()->error)->not->toContain('OpenAI 503');
});

it('retries the publish job on transient failure', function () {
    $item = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->approved()->create([
        'vector_file_id' => 'vf_1',
    ]);

    $job = new PublishKnowledgeItem($item);

    expect($job->tries)->toBeGreaterThanOrEqual(3)
        ->and($job->backoff())->not->toBeEmpty();

    // The job propagates transient failures so the queue retries it.
    $this->mock(PublishingService::class)
        ->shouldReceive('publish')
        ->andThrow(new RuntimeException('transient provider error'));

    expect(fn () => $job->handle(app(PublishingService::class)))
        ->toThrow(RuntimeException::class);
});
