<?php

use App\Enums\AiLogType;
use App\Enums\MessageRole;
use App\Enums\UsageType;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Ai\AiAuditLogger;
use App\Services\Ai\ChatService;
use App\Services\Billing\UsageRecorder;
use App\Services\Curation\CurationService;
use App\Support\ActiveOrganization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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

        if ($id = request()->integer('c')) {
            $this->selectConversation($id);
        }
    }

    protected function confirmAgentBelongsToActiveOrganization(Agent $agent): void
    {
        abort_unless($agent->organization_id === app(ActiveOrganization::class)->id(), 403);
    }

    public function canManageAgent(): bool
    {
        return Gate::allows('update', $this->agent);
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

    public function newChat(): void
    {
        $this->reset('conversationId', 'draft', 'progress');
        unset($this->conversation, $this->chatMessages);
    }

    #[On('conversation-deleted')]
    public function onConversationDeleted(int $id): void
    {
        if ($this->conversationId === $id) {
            $this->newChat();
        }
    }

    public function selectConversation(int $id): void
    {
        $conversation = Conversation::where('id', $id)
            ->where('agent_id', $this->agent->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($conversation) {
            $this->conversationId = $conversation->id;
            $this->reset('draft', 'progress');
            unset($this->conversation, $this->chatMessages);
        }
    }

    public function send(ChatService $chat, UsageRecorder $usage, CurationService $curation, AiAuditLogger $audit): void
    {
        $this->validate();
        $this->confirmAgentBelongsToActiveOrganization($this->agent);

        $isNewConversation = $this->conversation === null;

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

        // Name the conversation from its first answer; bump recency so it sorts
        // to the top of "Recentes".
        if ($isNewConversation && ! $failed) {
            $conversation->update(['title' => $chat->titleFromAnswer($text, $question)]);
        } else {
            $conversation->touch();
        }

        $this->reset('draft', 'progress');
        unset($this->conversation, $this->chatMessages);

        // Refresh the "Recentes" list in the sidebar when a new chat begins.
        if ($isNewConversation) {
            $this->dispatch('conversation-started');
        }

        if ($failed) {
            return;
        }

        $usage->record($this->agent->organization, UsageType::Question, agentId: $this->agent->id);

        // When the agent couldn't answer from its knowledge base, log the gap
        // and push it into the curation queue.
        if (trim($text) === ChatService::NO_KNOWLEDGE_MESSAGE) {
            Log::info('Unanswered agent question', ['agent_id' => $this->agent->id, 'question' => $question]);

            $curation->recordGap($this->agent, $question);
        }
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

<section class="flex h-[calc(100dvh-7rem)] min-h-[30rem] w-full">
    {{-- Thread (conversation history lives in the app sidebar — "Recentes") --}}
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-card border border-zinc-200 dark:border-zinc-700">
        <header class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    <flux:icon name="cpu-chip" class="size-4" />
                </div>
                <div>
                    <flux:heading size="sm">{{ $agent->name }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">{{ __('Assistente de conhecimento') }}</flux:text>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" icon="plus" wire:click="newChat" data-test="new-chat">{{ __('Novo chat') }}</flux:button>
                @if ($this->canManageAgent())
                    <flux:button size="sm" variant="ghost" icon="adjustments-horizontal" :href="route('agents.edit', ['agent' => $agent])" wire:navigate data-test="edit-agent">
                        {{ __('Editar agente') }}
                    </flux:button>
                @endif
            </div>
        </header>

        <div
            class="flex-1 overflow-y-auto px-4 py-6"
            data-test="chat-messages"
            x-data
            x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
            x-on:livewire:updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
        >
            @if ($this->chatMessages->isEmpty() && ! $this->progress)
                <div class="flex h-full flex-col items-center justify-center text-center">
                    <div class="flex size-12 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <flux:icon name="sparkles" class="size-6" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('Como posso ajudar?') }}</flux:heading>
                    <flux:text class="mt-1 max-w-sm text-zinc-500">
                        {{ __('Pergunte qualquer coisa sobre a base de conhecimento de :name.', ['name' => $agent->name]) }}
                    </flux:text>
                </div>
            @else
                <div class="mx-auto flex max-w-3xl flex-col gap-6">
                    @foreach ($this->chatMessages as $message)
                        <div @class([
                            'flex gap-3',
                            'flex-row-reverse' => $message->role === MessageRole::User,
                        ]) wire:key="msg-{{ $message->id }}">
                            <div @class([
                                'flex size-8 shrink-0 items-center justify-center rounded-full',
                                'bg-brand-600 text-white' => $message->role === MessageRole::User,
                                'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => $message->role === MessageRole::Assistant,
                            ])>
                                <flux:icon :name="$message->role === MessageRole::User ? 'user' : 'cpu-chip'" class="size-4" />
                            </div>

                            <div @class([
                                'max-w-[80%] rounded-2xl px-4 py-3',
                                'bg-brand-600 text-white' => $message->role === MessageRole::User,
                                'bg-zinc-100 dark:bg-zinc-800' => $message->role === MessageRole::Assistant,
                            ])>
                                <div @class([
                                    'prose prose-sm max-w-none',
                                    'prose-invert' => $message->role === MessageRole::User,
                                    'dark:prose-invert' => $message->role === MessageRole::Assistant,
                                ])>{!! str()->markdown($message->content) !!}</div>

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
                        </div>
                    @endforeach

                    @if ($this->progress)
                        <div class="flex gap-3">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                <flux:icon name="cpu-chip" class="size-4" />
                            </div>
                            <div class="max-w-[80%] rounded-2xl bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                                <div class="flex items-center gap-2 text-sm text-zinc-500" data-test="chat-progress">
                                    <flux:icon name="arrow-path" class="size-4 animate-spin" />
                                    <span wire:stream="progress">{{ end($this->progress) }}</span>
                                </div>
                                <div class="prose prose-sm mt-1 max-w-none dark:prose-invert" wire:stream="answer"></div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
            <form wire:submit="send" class="mx-auto flex max-w-3xl items-end gap-2">
                <flux:input wire:model="draft" :placeholder="__('Pergunte algo ao agente...')" class="flex-1" data-test="chat-input" />
                <flux:button type="submit" variant="primary" icon="paper-airplane" :aria-label="__('Enviar')" data-test="chat-send" />
            </form>
        </div>
    </div>
</section>
