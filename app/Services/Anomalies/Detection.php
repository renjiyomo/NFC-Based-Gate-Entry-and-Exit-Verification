<?php

namespace App\Services\Anomalies;

use App\Models\AccessEvent;
use App\Models\AnomalyFlag;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GateOperations\MissingExits;
use App\Services\GateOperations\OperatingDay;
use App\Services\ManagementGuard;
use App\Services\Monitoring\ReportFilters;
use App\Support\DatabaseLocks;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Detection
{
    public function run(ReportFilters $f, string $source, User $actor): array
    {
        return DB::transaction(function () use ($f, $source, $actor): array {
            $admin = app(ManagementGuard::class)->actor($actor);
            abort_unless($actor->auth_version === $admin->auth_version, 403);
            // One administrator row serializes this actor; the unique detection key
            // handles overlapping runs by different administrators atomically.
            $q = ScanAttempt::where('source', $source)->where('received_at', '>=', $f->from->format('Y-m-d H:i:s.u'))->where('received_at', '<', $f->until->format('Y-m-d H:i:s.u'))->where('received_at', '<=', $f->asOf->format('Y-m-d H:i:s.u'));
            if ($f->from->diffInDays($f->until) > 31 || (clone $q)->count() > 1000) {
                throw ValidationException::withMessages(['detection' => 'Choose at most 31 days and 1,000 attempts. Narrow the dates; nothing was skipped.']);
            }
            $rules = app(Rules::class);
            $policy = $rules->policy();
            $version = $rules->version($policy);
            $created = 0;
            $checked = 0;
            foreach ($q->orderBy('received_at')->orderBy('id')->get() as $a) {
                $checked++;
                $created += $this->inspect($a, $admin, $policy);
            }
            $created += $this->missingExits($f, $source);
            app(AuditLogger::class)->record('anomaly.evaluated', $admin, metadata: ['source' => $source, 'row_count' => $checked, 'period_start' => $f->values['from'], 'period_end' => $f->values['to'], 'policy_version' => $version]);

            return ['checked' => $checked, 'created' => $created];
        }, 3);
    }

    public function inspect(ScanAttempt $attempt, User $actor, ?array $policy = null): int
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Activity checks require the scan transaction.');
        }
        if ($attempt->person_id) {
            Person::withTrashed()->whereKey($attempt->person_id)->lockForUpdate()->firstOrFail();
        }
        $rules = app(Rules::class);
        $policy ??= $rules->policy();
        $version = $rules->version($policy);
        $created = 0;
        foreach ($rules->evaluate($attempt, $policy) as $code => $candidate) {
            $identity = ['source' => $attempt->source, 'rule_code' => $code, 'rule_version' => $version, 'anchor_attempt_id' => $attempt->id];
            $flag = AnomalyFlag::where($identity)->tap([DatabaseLocks::class, 'immutableRead'])->first();
            if ($flag) {
                continue;
            }
            if (str_starts_with($attempt->policy_version, 'gate-state-v1-')
                && in_array($code, ['rapid_scans', 'revoked_use', 'unavailable_credential', 'denied_burst', 'duplicate_burst'], true)) {
                $recent = AnomalyFlag::where('source', $attempt->source)->where('rule_code', $code)->where('rule_version', $version)
                    ->where('occurred_at', '>', $attempt->received_at->subSeconds($policy['window_seconds'])->format('Y-m-d H:i:s.u'))
                    ->where('occurred_at', '<=', $attempt->received_at->format('Y-m-d H:i:s.u'))
                    ->whereHas('anchor', function ($query) use ($attempt, $code): void {
                        $query->where('gate_id', $attempt->gate_id);
                        if ($code !== 'denied_burst') {
                            $query->where('person_id', $attempt->person_id);
                        }
                    })->tap([DatabaseLocks::class, 'immutableRead'])->first();
                if ($recent) {
                    continue;
                }
            }
            try {
                $flag = DB::transaction(fn () => AnomalyFlag::create([...$identity, 'public_id' => (string) Str::uuid(), 'policy' => $policy, 'severity' => $candidate['severity'], 'explanation' => $candidate['explanation'], 'occurred_at' => $attempt->received_at, 'created_at' => now()]));
            } catch (UniqueConstraintViolationException $error) {
                $flag = AnomalyFlag::where($identity)->tap([DatabaseLocks::class, 'immutableRead'])->first();
                if (! $flag) {
                    throw $error;
                }
            }
            if ($flag->wasRecentlyCreated) {
                $flag->evidence()->attach($candidate['ids']);
                $created++;
                app(AuditLogger::class)->record('anomaly.detected', $actor, $flag, ['source' => $attempt->source, 'reason' => $code, 'policy_version' => $version]);
            }
        }

        return $created;
    }

    private function missingExits(ReportFilters $filters, string $source): int
    {
        $entries = AccessEvent::effective()->where('source', $source)->where('direction', 'entry')->where('policy_version', 'like', 'gate-state-v1-%')
            ->where('confirmed_at', '>=', $filters->from->subDay()->format('Y-m-d H:i:s.u'))
            ->where('confirmed_at', '<', $filters->until->format('Y-m-d H:i:s.u'))->orderBy('id')->limit(1001)->get();
        if ($entries->count() > 1000) {
            throw ValidationException::withMessages(['detection' => 'Narrow the dates to at most 1,000 Entry records.']);
        }
        $created = 0;
        foreach ($entries as $entry) {
            $cutoff = substr($entry->policy_version, -4, 2).':'.substr($entry->policy_version, -2);
            $day = app(OperatingDay::class)->containing($entry->confirmed_at, $cutoff);
            if ($day['ends_at']->lessThan($filters->from) || ! $day['ends_at']->lessThan($filters->until) || $day['ends_at']->greaterThan($filters->asOf)) {
                continue;
            }
            Person::withTrashed()->whereKey($entry->person_id)->lockForUpdate()->firstOrFail();
            $flag = app(MissingExits::class)->recordLocked($entry, $filters->asOf);
            $created += $flag?->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    public function context(AnomalyFlag $flag): string
    {
        if ($flag->rule_code === 'missing_exit') {
            return DB::transaction(function () use ($flag): string {
                $event = AccessEvent::where('scan_attempt_id', $flag->anchor_attempt_id)->firstOrFail();
                Person::withTrashed()->whereKey($event->person_id)->lockForUpdate()->firstOrFail();

                return app(MissingExits::class)->supported($event, now()) ? 'still_supported' : 'evidence_changed';
            }, 3);
        }
        $current = app(Rules::class)->evaluate($flag->anchor, $flag->policy)[$flag->rule_code] ?? null;
        $original = $flag->evidence()->pluck('scan_attempts.id')->sort()->values()->all();

        return $current && $original === $current['ids'] ? 'still_supported' : 'evidence_changed';
    }
}
