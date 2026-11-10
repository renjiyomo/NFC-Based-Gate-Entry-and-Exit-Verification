<?php

namespace App\Services\Summaries;

use App\Models\SummaryRequest;
use App\Models\SummaryReview;
use App\Models\SummaryTask;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ManagementGuard;
use App\Support\DatabaseLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Review
{
    public function append(User $actor, SummaryRequest $target, string $source, array $data): void
    {
        DB::transaction(function () use ($actor, $target, $source, $data): void {
            $admin = app(ManagementGuard::class)->actor($actor);
            abort_unless($admin->auth_version === $actor->auth_version, 403);
            abort_unless($target->source === $source, 404);
            SummaryTask::where('summary_request_id', $target->id)->lockForUpdate()->firstOrFail();
            abort_unless($target->result()->value('status') === 'ready', 422, 'Only validated ready drafts can be reviewed.');
            $last = $target->reviews()->tap([DatabaseLocks::class, 'immutableRead'])->first();
            if ((int) ($last?->id ?? 0) !== (int) $data['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'A newer review was saved. Reload before continuing.']);
            }
            SummaryReview::create(['summary_request_id' => $target->id, 'actor_id' => $admin->id, 'decision' => $data['decision'], 'reason' => trim($data['reason']), 'created_at' => now()]);
            app(AuditLogger::class)->record('summary.reviewed', $admin, $target, ['source' => $source, 'status' => $data['decision']]);
        }, 3);
    }
}
