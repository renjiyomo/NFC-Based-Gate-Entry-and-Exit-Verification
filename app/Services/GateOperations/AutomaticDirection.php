<?php

namespace App\Services\GateOperations;

use App\Models\AccessEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Pure policy only. The transaction must supply the latest effective event
 * for the verified person/source across all gates, under the person's lock.
 */
final class AutomaticDirection
{
    public function __construct(private OperatingDay $days) {}

    public function decide(?AccessEvent $latest, CarbonInterface $at, string $cutoff, int $duplicateSeconds): array
    {
        if ($duplicateSeconds < 1 || $duplicateSeconds > 120) {
            throw new \LogicException('Invalid duplicate policy.');
        }
        $day = $this->days->containing($at, $cutoff);
        $previousAt = $latest?->confirmed_at;
        if ($latest && (! in_array($latest->direction, ['entry', 'exit'], true) || ! $previousAt || $previousAt->greaterThan($at))) {
            throw new \LogicException('The previous effective passage is invalid or in the future.');
        }

        // Suppress before toggling, even across gates or an operating-day boundary.
        $duplicate = $previousAt && $previousAt->greaterThan(CarbonImmutable::instance($at)->subSeconds($duplicateSeconds));
        $inDay = $previousAt && $previousAt->greaterThanOrEqualTo($day['starts_at']);

        return [
            'operating_day' => $day['label'],
            'starts_at' => $day['starts_at'],
            'ends_at' => $day['ends_at'],
            'duplicate' => (bool) $duplicate,
            'direction' => $duplicate ? $latest->direction : ($inDay && $latest->direction === 'entry' ? 'exit' : 'entry'),
            // Candidate for append-only anomaly creation, never a fabricated Exit.
            'missing_exit_event_id' => ! $duplicate && $latest?->direction === 'entry' && ! $inDay ? $latest->id : null,
        ];
    }
}
