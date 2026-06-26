<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Unpublished = 'unpublished';
    case Publishing = 'publishing';
    case Published = 'published';
    case Outdated = 'outdated';

    /**
     * Get the display label for the publication status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unpublished => 'Não publicado',
            self::Publishing => 'Publicando',
            self::Published => 'Publicado',
            self::Outdated => 'Desatualizado',
        };
    }

    /**
     * Get the Flux color token for the publication status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Unpublished => 'zinc',
            self::Publishing => 'blue',
            self::Published => 'green',
            self::Outdated => 'amber',
        };
    }
}
