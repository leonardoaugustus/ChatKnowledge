<?php

namespace App\Models;

use App\Services\Ai\SystemPromptCompiler;
use Database\Factories\AgentConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $agent_id
 * @property string|null $identity
 * @property string|null $soul
 * @property string|null $objective
 * @property string|null $tone
 * @property string|null $rules
 * @property string|null $user
 * @property string|null $bootstrap
 * @property string|null $heartbeat
 * @property string|null $tools
 * @property string|null $compiled_system_prompt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 */
#[Fillable(['agent_id', 'identity', 'soul', 'objective', 'tone', 'rules', 'user', 'bootstrap', 'heartbeat', 'tools'])]
class AgentConfig extends Model
{
    /** @use HasFactory<AgentConfigFactory> */
    use HasFactory;

    /**
     * Recompile the system prompt whenever a section changes.
     */
    protected static function booted(): void
    {
        static::saving(function (AgentConfig $config) {
            $sectionsChanged = collect(array_keys(SystemPromptCompiler::SECTIONS))
                ->some(fn (string $section) => $config->isDirty($section));

            if (! $config->exists || $sectionsChanged) {
                $config->compiled_system_prompt = app(SystemPromptCompiler::class)->compile($config);
            }
        });
    }

    /**
     * Get the agent that owns the config.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
