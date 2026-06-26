<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Extracted = 'extracted';
    case PendingCuration = 'pending_curation';
    case Approved = 'approved';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Enviado',
            self::Processing => 'Processando',
            self::Extracted => 'Extraído',
            self::PendingCuration => 'Pendente de curadoria',
            self::Approved => 'Aprovado',
            self::Publishing => 'Publicando',
            self::Published => 'Publicado',
            self::Failed => 'Falhou',
        };
    }

    /**
     * Get the Flux color token for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Uploaded => 'zinc',
            self::Processing => 'blue',
            self::Extracted => 'cyan',
            self::PendingCuration => 'amber',
            self::Approved => 'lime',
            self::Publishing => 'blue',
            self::Published => 'green',
            self::Failed => 'red',
        };
    }
}
