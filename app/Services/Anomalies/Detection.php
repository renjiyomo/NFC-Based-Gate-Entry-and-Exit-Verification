<?php

namespace App\Services\Anomalies;

use App\Models\AnomalyFlag;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ManagementGuard;
use App\Services\Monitoring\ReportFilters;
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
                foreach ($rules->evaluate($a, $policy) as $code => $candidate) {
                    $identity = ['source' => $source, 'rule_code' => $code, 'rule_version' => $version, 'anchor_attempt_id' => $a->id];
                    $flag = AnomalyFlag::where($identity)->first();
                    if (! $flag) {
                        try {
                            $flag = AnomalyFlag::create([...$identity, 'public_id' => (string) Str::uuid(), 'policy' => $policy, 'severity' => $candidate['severity'], 'explanation' => $candidate['explanation'], 'occurred_at' => $a->received_at, 'created_at' => now()]);
                        } catch (UniqueConstraintViolationException $error) {
                            // Under MySQL REPEATABLE READ the earlier snapshot can
                            // miss another administrator's committed winner. A
                            // shared locking read sees it without UPDATE grants.
                            $flag = AnomalyFlag::where($identity)->sharedLock()->first();
                            if (! $flag) {
                                throw $error;
                            }
                        }
                    }
                    if ($flag->wasRecentlyCreated) {
                        $flag->evidence()->attach($candidate['ids']);
                        $created++;
                        app(AuditLogger::class)->record('anomaly.detected', $admin, $flag, ['source' => $source, 'reason' => $code, 'policy_version' => $version]);
                    }
                }
            }
            app(AuditLogger::class)->record('anomaly.evaluated', $admin, metadata: ['source' => $source, 'row_count' => $checked, 'period_start' => $f->values['from'], 'period_end' => $f->values['to'], 'policy_version' => $version]);

            return ['checked' => $checked, 'created' => $created];
        }, 3);
    }

    public function context(AnomalyFlag $flag): string
    {
        $current = app(Rules::class)->evaluate($flag->anchor, $flag->policy)[$flag->rule_code] ?? null;
        $original = $flag->evidence()->pluck('scan_attempts.id')->sort()->values()->all();

        return $current && $original === $current['ids'] ? 'still_supported' : 'evidence_changed';
    }
}
