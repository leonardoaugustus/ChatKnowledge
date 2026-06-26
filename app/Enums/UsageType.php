<?php

namespace App\Enums;

enum UsageType: string
{
    case Question = 'question';
    case Extraction = 'extraction';

    /**
     * Get the display label for the usage type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Question => 'Pergunta',
            self::Extraction => 'Extração',
        };
    }
}
