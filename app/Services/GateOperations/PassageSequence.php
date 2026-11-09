<?php

namespace App\Services\GateOperations;

use App\Models\AccessEvent;
use Carbon\CarbonInterface;

class PassageSequence
{
    public function notice(?AccessEvent $previous, string $direction, CarbonInterface $at): ?string
    {
        if (! $previous) {
            return $direction === 'exit' ? 'exit_without_recorded_entry' : null;
        }
        $earlierDay = $previous->confirmed_at->copy()->setTimezone('Asia/Manila')->toDateString()
            < $at->copy()->setTimezone('Asia/Manila')->toDateString();
        if ($earlierDay && $previous->direction === 'entry') {
            return $direction === 'entry' ? 'previous_day_unclosed_entry' : 'exit_after_earlier_day_entry';
        }
        if ($previous->direction === $direction) {
            return $earlierDay && $direction === 'exit' ? 'exit_without_entry_today' : 'repeated_'.$direction;
        }

        return null;
    }
}
