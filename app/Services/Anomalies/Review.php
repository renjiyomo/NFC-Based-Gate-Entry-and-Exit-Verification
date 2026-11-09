<?php

namespace App\Services\Anomalies;

use App\Models\AnomalyFlag;
use App\Models\AnomalyReview;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ManagementGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Review
{
    public function append(User $actor, AnomalyFlag $target, string $source, array $data): AnomalyReview
    {
        return DB::transaction(function () use ($actor, $target, $source, $data): AnomalyReview {
            $admin = app(ManagementGuard::class)->actor($actor);
            abort_unless($actor->auth_version === $admin->auth_version, 403);
            abort_unless($target->source === $source, 404);
            // Attempts already allow tightly scoped review-state UPDATE privileges;
            // use their stable row as mutex without granting flag mutation rights.
            ScanAttempt::whereKey($target->anchor_attempt_id)->lockForUpdate()->firstOrFail();
            $flag = AnomalyFlag::whereKey($target->id)->where('source', $source)->sharedLock()->firstOrFail();
            $last = $flag->reviews()->sharedLock()->first();
            if ((int) ($last?->id ?? 0) !== (int) $data['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'Another review was saved. Reload before continuing.']);
            }
            $status = $last?->status ?? 'open';
            if (in_array($status, ['resolved', 'false_alarm'], true) && $data['status'] !== 'open') {
                throw ValidationException::withMessages(['status' => 'Reopen this flag before adding another decision.']);
            }
            $review = AnomalyReview::create(['anomaly_flag_id' => $flag->id, 'actor_id' => $admin->id, 'status' => $data['status'], 'reason' => trim($data['reason']), 'created_at' => now()]);
            app(AuditLogger::class)->record('anomaly.reviewed', $admin, $flag, ['status' => $data['status'], 'source' => $source]);

            return $review;
        }, 3);
    }
}
