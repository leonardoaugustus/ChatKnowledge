<?php

namespace App\Jobs\Concerns;

trait RetriesAiFailures
{
    /**
     * The number of times the job may be attempted (retried on transient
     * AI/OpenAI failures).
     */
    public int $tries = 3;

    /**
     * The seconds to wait before retrying the job, growing per attempt.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
