<?php

namespace App\Services\Summaries;

interface Provider
{
    /** Configuration readiness only, not an assertion of network availability. */
    public function configuration(): array;

    /** Return output (structured IDs) and bounded token usage; never raw provider text. */
    public function generate(array $snapshot): array;
}
