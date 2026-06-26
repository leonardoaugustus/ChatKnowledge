<?php

namespace App\Services\Ai;

use App\Models\AgentConfig;

class SystemPromptCompiler
{
    /**
     * The ordered personality sections and their Markdown headings.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'identity' => 'Identity',
        'soul' => 'Soul',
        'user' => 'User',
        'bootstrap' => 'Bootstrap',
        'heartbeat' => 'Heartbeat',
        'tools' => 'Tools',
    ];

    /**
     * Compile the agent config's sections into a single Markdown system prompt.
     *
     * Sections are composed in a fixed order, each under its own heading. Empty
     * sections are skipped so the prompt stays clean.
     */
    public function compile(AgentConfig $config): string
    {
        $blocks = [];

        foreach (self::SECTIONS as $section => $heading) {
            $content = trim((string) $config->{$section});

            if ($content !== '') {
                $blocks[] = "## {$heading}\n\n{$content}";
            }
        }

        return implode("\n\n", $blocks);
    }
}
