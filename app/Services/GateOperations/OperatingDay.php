<?php

namespace App\Services\GateOperations;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class OperatingDay
{
    /** Half-open operating-day bounds in UTC; label uses Asia/Manila. */
    public function containing(CarbonInterface $at, string $cutoff): array
    {
        if (! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $cutoff)) {
            throw new \LogicException('Operating-day cutoff must be HH:MM in Asia/Manila.');
        }

        $local = CarbonImmutable::instance($at)->setTimezone('Asia/Manila');
        [$hour, $minute] = array_map('intval', explode(':', $cutoff));
        $start = $local->startOfDay()->setTime($hour, $minute);
        if ($local->lessThan($start)) {
            $start = $start->subDay();
        }

        return ['label' => $start->toDateString(), 'starts_at' => $start->utc(), 'ends_at' => $start->addDay()->utc()];
    }
}
