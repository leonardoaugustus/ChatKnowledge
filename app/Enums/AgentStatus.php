<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Published => 'Publicado',
        };
    }

    /**
     * Get the Flux color token for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Published => 'green',
        };
    }
}
