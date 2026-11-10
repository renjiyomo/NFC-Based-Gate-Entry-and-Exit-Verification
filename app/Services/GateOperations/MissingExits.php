<?php

namespace App\Services\GateOperations;

use App\Models\AccessEvent;
use App\Models\AnomalyFlag;
use App\Models\Person;
use App\Services\AuditLogger;
use App\Support\DatabaseLocks;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MissingExits
{
    public const VERSION = 'missing-exit-v1';

    public function supported(AccessEvent $entry, CarbonInterface $at): bool
    {
        if ($entry->direction !== 'entry' || ! preg_match('/^gate-state-v1-([0-9]{2})([0-9]{2})$/D', $entry->policy_version, $parts)) {
            return false;
        }
        $day = app(OperatingDay::class)->containing($entry->confirmed_at, $parts[1].':'.$parts[2]);
        if ($at->lessThan($day['ends_at']) || ! AccessEvent::effective()->whereKey($entry->id)->tap([DatabaseLocks::class, 'immutableRead'])->exists()) {
            return false;
        }
        $clock = $entry->confirmed_at->format('Y-m-d H:i:s.u');
        $next = AccessEvent::effective()->where('source', $entry->source)->where('person_id', $entry->person_id)
            ->where('confirmed_at', '<', $day['ends_at']->format('Y-m-d H:i:s.u'))
            ->where(fn ($q) => $q->where('confirmed_at', '>', $clock)->orWhere(fn ($tie) => $tie->where('confirmed_at', $clock)->where('id', '>', $entry->id)))
            ->orderBy('confirmed_at')->orderBy('id')->tap([DatabaseLocks::class, 'immutableRead'])->first();

        return $next?->direction !== 'exit';
    }

    /** Caller holds the person lock; history and its missing Exit remain immutable. */
    public function recordLocked(AccessEvent $entry, CarbonInterface $at): ?AnomalyFlag
    {
        if (! $this->supported($entry, $at)) {
            return null;
        }
        $identity = ['source' => $entry->source, 'rule_code' => 'missing_exit', 'rule_version' => hash('sha256', self::VERSION), 'anchor_attempt_id' => $entry->scan_attempt_id];
        $existing = AnomalyFlag::where($identity)->tap([DatabaseLocks::class, 'immutableRead'])->first();
        if ($existing) {
            return $existing;
        }
        $cutoff = substr($entry->policy_version, -4, 2).':'.substr($entry->policy_version, -2);
        $day = app(OperatingDay::class)->containing($entry->confirmed_at, $cutoff);
        try {
            return DB::transaction(function () use ($entry, $identity, $cutoff, $day, $at): AnomalyFlag {
                $flag = AnomalyFlag::create([...$identity, 'public_id' => (string) Str::uuid(), 'policy' => ['algorithm' => self::VERSION, 'cutoff' => $cutoff, 'operating_day' => $day['label'], 'ends_at' => $day['ends_at']->toISOString()], 'severity' => 'low', 'explanation' => 'No effective Exit followed this Entry before its operating-day cutoff. The Entry is retained; no Exit time was invented. This indicates an incomplete scan sequence, not verified occupancy or misconduct.', 'occurred_at' => $day['ends_at'], 'created_at' => $at]);
                $flag->evidence()->attach($entry->scan_attempt_id);
                app(AuditLogger::class)->record('anomaly.detected', target: $flag, metadata: ['source' => $entry->source, 'reason' => 'missing_exit', 'policy_version' => self::VERSION]);

                return $flag;
            });
        } catch (UniqueConstraintViolationException $error) {
            return AnomalyFlag::where($identity)->tap([DatabaseLocks::class, 'immutableRead'])->first() ?? throw $error;
        }
    }

    /** A bounded resumable page. No person has to return for detection to run. */
    public function sweep(string $source, int $after = 0, int $limit = 100): array
    {
        if (! in_array($source, ['physical', 'simulated'], true) || $after < 0 || $limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Invalid missing-exit sweep bounds.');
        }
        $entries = AccessEvent::where('source', $source)->where('direction', 'entry')->where('policy_version', 'like', 'gate-state-v1-%')->where('id', '>', $after)->orderBy('id')->limit($limit)->get();
        $created = 0;
        foreach ($entries as $entry) {
            $created += DB::transaction(function () use ($entry): int {
                Person::withTrashed()->whereKey($entry->person_id)->lockForUpdate()->firstOrFail();
                $flag = $this->recordLocked($entry, now());

                return $flag?->wasRecentlyCreated ? 1 : 0;
            }, 3);
        }

        return ['checked' => $entries->count(), 'created' => $created, 'next' => $entries->count() === $limit ? $entries->last()->id : 0];
    }
}
