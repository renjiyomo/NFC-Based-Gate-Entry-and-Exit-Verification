<?php

namespace App\Services\Monitoring;

use App\Models\AccessEvent;
use App\Models\PassageCorrection;
use App\Models\Person;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ManagementGuard;
use App\Support\DatabaseLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PassageCorrections
{
    public function append(User $actor, AccessEvent $target, array $data, string $source): PassageCorrection
    {
        return DB::transaction(function () use ($actor, $target, $data, $source): PassageCorrection {
            $admin = app(ManagementGuard::class)->actor($actor);
            abort_unless($admin->auth_version === $actor->auth_version, 403);
            abort_unless($target->source === $source, 404);
            Person::withTrashed()->whereKey($target->person_id)->lockForUpdate()->firstOrFail();
            $event = AccessEvent::whereKey($target->id)->tap([DatabaseLocks::class, 'immutableRead'])->firstOrFail();
            $previous = $event->corrections()->get();
            if ((int) ($previous->last()?->id ?? 0) !== (int) $data['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'The correction history changed. Reload before saving.']);
            }
            $void = $previous->whereIn('action', ['void', 'reinstate'])->last()?->action === 'void';
            if (($data['action'] === 'void' && $void) || ($data['action'] === 'reinstate' && ! $void)) {
                throw ValidationException::withMessages(['action' => 'This action does not match the current effective state.']);
            }
            $correction = PassageCorrection::create(['public_id' => (string) Str::uuid(), 'access_event_id' => $event->id, 'actor_id' => $admin->id, 'action' => $data['action'], 'reason' => trim($data['reason']), 'created_at' => now()]);
            app(AuditLogger::class)->record('passage.correction_added', $admin, $correction, ['reason' => $data['action'], 'source' => $source]);

            return $correction;
        }, 3);
    }
}
