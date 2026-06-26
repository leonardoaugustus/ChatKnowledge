<?php

use App\Ai\Agents\ChatAgent;
use App\Enums\CurationStatus;
use App\Enums\KnowledgeType;
use App\Models\Agent;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\Ai\ChatService;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->withConfig()->create([
        'vector_store_id' => 'vs_chat',
    ]);

    ChatAgent::fake(fn () => ChatService::NO_KNOWLEDGE_MESSAGE);
});

function askUnanswered(Agent $agent, string $question): void
{
    Livewire::test('pages::chat.index', ['agent' => $agent])
        ->set('draft', $question)
        ->call('send')
        ->assertHasNoErrors();
}

it('records an unanswered question', function () {
    Log::spy();

    askUnanswered($this->agent, 'Qual o horário de funcionamento aos domingos?');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $context['question'] === 'Qual o horário de funcionamento aos domingos?')
        ->once();
});

it('creates a curation gap item scoped to the agent', function () {
    askUnanswered($this->agent, 'Vocês entregam no exterior?');

    $gap = KnowledgeItem::where('agent_id', $this->agent->id)->sole();

    expect($gap->curation_status)->toBe(CurationStatus::Pending)
        ->and($gap->type)->toBe(KnowledgeType::Faq)
        ->and($gap->title)->toBe('Vocês entregam no exterior?')
        ->and($gap->organization_id)->toBe($this->organization->id)
        ->and($gap->metadata['gap'])->toBeTrue()
        ->and($gap->metadata['asked_count'])->toBe(1);
});

it('does not duplicate identical gaps', function () {
    askUnanswered($this->agent, '  Vocês  ENTREGAM no exterior? ');
    askUnanswered($this->agent, 'Vocês entregam no exterior?');

    $gaps = KnowledgeItem::where('agent_id', $this->agent->id)->get();

    expect($gaps)->toHaveCount(1)
        ->and($gaps->first()->metadata['asked_count'])->toBe(2);
});
