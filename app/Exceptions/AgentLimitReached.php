<?php

namespace App\Exceptions;

use Exception;

class AgentLimitReached extends Exception
{
    public static function forLimit(int $limit): self
    {
        return new self("This organization has reached its plan limit of {$limit} agents.");
    }
}
