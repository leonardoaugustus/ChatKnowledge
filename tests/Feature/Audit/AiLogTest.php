<?php

use App\Ai\Agents\ChatAgent;
use App\Ai\Agents\KnowledgeExtractor;
use App\Ai\Tools\HttpToolBridge;
use App\Enums\AiLogType;
use App\Enums\HttpMethod;
use App\Enums\PublicationStatus;
use App\Models\Agent;
use App\Models\AgentTool;
use App\Models\AiLog;
use App\Models\Document;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\Ai\KnowledgeExtractionService;
use App\Services\Ai\PublishingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    fakeVectorStore();

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->withConfig()->create(['vector_store_id' => 'vs_log']);
});

it('logs an extraction event with latency, tokens and estimated cost', function () {
    KnowledgeExtractor::fake([['items' => [['type' => 'faq', 'title' => 'A', 'content' => 'x']]]]);

    Storage::disk('local')->put('documents/raw.txt', 'raw');
    $document = Document::factory()->for($this->organization)->for($this->agent)->create([
        'disk' => 'local', 'path' => 'documents/raw.txt',
    ]);

    app(KnowledgeExtractionService::class)->extract($document);

    $log = AiLog::where('type', AiLogType::Extraction->value)->sole();

    expect($log->agent_id)->toBe($this->agent->id)
        ->and($log->latency_ms)->toBeGreaterThanOrEqual(0)
        ->and($log->tokens)->toBeGreaterThanOrEqual(0)
        ->and($log->estimated_cost)->toBeGreaterThanOrEqual(0.0)
        ->and($log->error)->toBeNull();
});

it('logs a publishing event', function () {
    $item = KnowledgeItem::factory()->for($this->organization)->for($this->agent)->approved()->create([
        'publication_status' => PublicationStatus::Unpublished,
    ]);

    app(PublishingService::class)->publish($item);

    expect(AiLog::where('type', AiLogType::Publishing->value)->exists())->toBeTrue();
});

it('logs a chat event', function () {
    ChatAgent::fake(['An answer.']);

    Livewire::test('pages::chat.index', ['agent' => $this->agent])
        ->set('draft', 'Question?')
        ->call('send')
        ->assertHasNoErrors();

    $log = AiLog::where('type', AiLogType::Chat->value)->sole();

    expect($log->agent_id)->toBe($this->agent->id)
        ->and($log->latency_ms)->toBeGreaterThanOrEqual(0);
});

it('logs a tool execution event', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $tool = AgentTool::factory()->for($this->organization)->for($this->agent)->create([
        'method' => HttpMethod::Post,
        'endpoint' => 'https://api.example.com/x',
        'input_schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
    ]);

    (new HttpToolBridge($tool, $this->agent))->run(['id' => '1']);

    expect(AiLog::where('type', AiLogType::ToolExecution->value)->exists())->toBeTrue();
});

it('logs errors', function () {
    KnowledgeExtractor::fake(fn () => throw new RuntimeException('provider failure'));

    Storage::disk('local')->put('documents/raw.txt', 'raw');
    $document = Document::factory()->for($this->organization)->for($this->agent)->create([
        'disk' => 'local', 'path' => 'documents/raw.txt',
    ]);

    try {
        app(KnowledgeExtractionService::class)->extract($document);
    } catch (RuntimeException) {
        // expected
    }

    $log = AiLog::where('type', AiLogType::Extraction->value)
        ->whereNotNull('error')
        ->sole();

    expect($log->error)->toBe('provider failure');
});
