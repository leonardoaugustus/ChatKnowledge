<?php

namespace App\Enums;

enum AiLogType: string
{
    case Extraction = 'extraction';
    case Publishing = 'publishing';
    case Chat = 'chat';
    case ToolExecution = 'tool_execution';

    /**
     * Get the display label for the log type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Extraction => 'Extração',
            self::Publishing => 'Publicação',
            self::Chat => 'Chat',
            self::ToolExecution => 'Execução de tool',
        };
    }
}
