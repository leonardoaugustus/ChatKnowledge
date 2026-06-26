<?php

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'Você',
            self::Assistant => 'Agente',
        };
    }
}
