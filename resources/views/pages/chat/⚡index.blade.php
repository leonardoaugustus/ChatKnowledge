<?php

use App\Enums\MessageRole;
use App\Enums\UsageType;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Enums\AiLogType;
use App\Services\Ai\AiAuditLogger;
use App\Services\Ai\ChatService;
use App\Services\Billing\UsageRecorder;
use App\Services\Curation\CurationService;
use App\Support\ActiveOrganization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Chat')] class extends Component
{
    public Agent $agent;

    public ?int $conversationId = null;

    #[Validate('required|string|max:4000')]
    public string $draft = '';

    /** @var array<int, string> */
    public array $progress = [];

    public function mount(Agent $agent): void
    {
        $this->confirmAgentBelongsToActiveOrganization($agent);

        $this->agent = $agent;
    }

    protected function confirmAgentBelongsToActiveOrganization(Agent $agent): void
    {
        abort_unless($agent->organization_id === app(ActiveOrganization::class)->id(), 403);
    }

    #[Computed]
    public function conversation(): ?Conversation
    {
        return $this->conversationId ? Conversation::find($this->conversationId) : null;
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function chatMessages(): Collection
    {
        return $this->conversation?->messages()->oldest()->get() ?? new Collection;
    }

    public function send(ChatService $chat, UsageRecorder $usage, CurationService $curation, AiAuditLogger $audit): void
    {
        $this->validate();
        $this->confirmAgentBelongsToActiveOrganization($this->agent);

        $conversation = $this->conversation ?? Conversation::create([
            'agent_id' => $this->agent->id,
            'user_id' => auth()->id(),
            'title' => str($this->draft)->limit(40)->value(),
        ]);
        $this->conversationId = $conversation->id;

        $question = $this->draft;

        // History is the prior turns (before this question).
        $history = $conversation->messages()->oldest()->get()
            ->map(fn (Message $message) => ['role' => $message->role->value, 'content' => $message->content])
            ->all();

        $conversation->messages()->create(['role' => MessageRole::User, 'content' => $question]);

        $this->progress = [];
        $events = [];
        $text = '';
        $failed = false;
        $startedAt = microtime(true);

        try {
            foreach ($chat->answer($this->agent, $question, $history) as $event) {
                $events[] = $event;

                if ($label = $chat->progressLabel($event)) {
                    $this->progress[] = $label;
                    $this->stream(to: 'progress', content: $label, replace: true);
                }

                if ($event instanceof TextDelta) {
                    $text .= $event->delta;
                    $this->stream(to: 'answer', content: $text, replace: true);
                }
            }
        } catch (\Throwable $e) {
            // Never surface raw provider errors to the user — log and reply with
            // a friendly message instead.
            Log::error('Chat answer failed', [
                'agent_id' => $this->agent->id,
                'code' => $e->getCode(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $failed = true;
            $text = ChatService::FAILURE_MESSAGE;
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $usageData = StreamEnd::combineUsage($events);
        $tokens = $usageData ? ($usageData->promptTokens + $usageData->completionTokens) : 0;

        $audit->record(
            AiLogType::Chat,
            $this->agent->organization_id,
            $this->agent->id,
            $latencyMs,
            $tokens,
            $failed ? __('Falha ao gerar a resposta.') : null,
            ['conversation_id' => $conversation->id],
        );

        $conversation->messages()->create([
            'role' => MessageRole::Assistant,
            'content' => $text,
            'sources' => $failed ? [] : $this->sourcesFrom($events),
        ]);

        if ($failed) {
            $this->reset('draft', 'progress');
            unset($this->conversation, $this->chatMessages);

            return;
        }

        $usage->record($this->agent->organization, UsageType::Question, agentId: $this->agent->id);

        // When the agent couldn't answer from its knowledge base, log the gap
        // and push it into the curation queue.
        if (trim($text) === ChatService::NO_KNOWLEDGE_MESSAGE) {
            Log::info('Unanswered agent question', ['agent_id' => $this->agent->id, 'question' => $question]);

            $curation->recordGap($this->agent, $question);
        }

        $this->reset('draft', 'progress');
        unset($this->conversation, $this->chatMessages);
    }

    /**
     * Collect source citations surfaced by the stream.
     *
     * @param  array<int, object>  $events
     * @return array<int, mixed>
     */
    protected function sourcesFrom(array $events): array
    {
        return collect($events)
            ->filter(fn ($event) => $event instanceof Citation)
            ->map(fn (Citation $event) => $event->toArray())
            ->values()
            ->all();
    }
}; ?>

<section class="flex h-full w-full flex-col">
    <x-page-header :title="__('Chat')" :description="$agent->name" />

    <x-agent-nav :agent="$agent" />

    <div class="mt-6 flex-1 space-y-4 overflow-y-auto" data-test="chat-messages">
        @foreach ($this->chatMessages as $message)
            <div @class([
                'rounded-card border p-4',
                'border-zinc-200 dark:border-zinc-700' => $message->role === MessageRole::Assistant,
                'border-brand-200 bg-brand-50 dark:border-brand-900 dark:bg-brand-950/40' => $message->role === MessageRole::User,
            ]) wire:key="msg-{{ $message->id }}">
                <flux:text class="text-2xs font-semibold uppercase tracking-wide text-zinc-500">{{ $message->role->label() }}</flux:text>
                <div class="prose prose-sm mt-1 max-w-none dark:prose-invert">{!! str()->markdown($message->content) !!}</div>

                @if ($message->role === MessageRole::Assistant && filled($message->sources))
                    <div class="mt-3 border-t border-zinc-200 pt-2 dark:border-zinc-700" data-test="message-sources">
                        <flux:text class="text-2xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Fontes') }}</flux:text>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($message->sources as $source)
                                <flux:badge size="sm" color="zinc">{{ $source['title'] ?? __('Fonte') }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        @if ($this->progress)
            <div class="flex items-center gap-2 text-sm text-zinc-500" data-test="chat-progress">
                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                <span wire:stream="progress">{{ end($this->progress) }}</span>
            </div>
            <div class="prose prose-sm max-w-none dark:prose-invert" wire:stream="answer"></div>
        @endif
    </div>

    <form wire:submit="send" class="mt-4 flex items-end gap-2">
        <flux:input wire:model="draft" :placeholder="__('Pergunte algo ao agente...')" class="flex-1" data-test="chat-input" />
        <flux:button type="submit" variant="primary" icon="paper-airplane" data-test="chat-send">{{ __('Enviar') }}</flux:button>
    </form>
</section>
