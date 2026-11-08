<?php

namespace App\Services;

use App\Models\AccessAuthorization;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessManagement
{
    public function __construct(private ManagementGuard $guard, private AuditLogger $audit) {}

    public function time(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, 'Asia/Manila')->utc();
    }

    public function grant(User $actor, Person $target, array $data): AccessAuthorization
    {
        return DB::transaction(function () use ($actor, $target, $data): AccessAuthorization {
            $actor = $this->guard->actor($actor);
            $person = Person::lockForUpdate()->findOrFail($target->id);
            $this->guard->version($person, (int) $data['record_version']);
            if ($person->status->value !== 'active') {
                throw ValidationException::withMessages(['record' => 'Activate this person record before granting access.']);
            }
            $start = $this->time($data['starts_at']);
            $end = empty($data['ends_at']) ? null : $this->time($data['ends_at']);
            $overlap = $person->authorizations()->where('status', 'active')->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $start));
            if ($end) {
                $overlap->where('valid_from', '<', $end);
            }
            if ($overlap->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This period overlaps an existing access grant. Revoke the old grant or choose another period.']);
            }
            $grant = $person->authorizations()->create(['status' => 'active', 'valid_from' => $start, 'valid_until' => $end, 'reason' => $data['reason'], 'granted_by' => $actor->id]);
            $person->forceFill(['record_version' => (int) $person->record_version + 1])->save();
            $this->audit->record('access.granted', $actor, $grant);

            return $grant;
        }, 3);
    }

    public function revokeGrant(User $actor, Person $target, AccessAuthorization $targetGrant): void
    {
        DB::transaction(function () use ($actor, $target, $targetGrant): void {
            $actor = $this->guard->actor($actor);
            $person = Person::withTrashed()->lockForUpdate()->findOrFail($target->id);
            $grant = $person->authorizations()->lockForUpdate()->findOrFail($targetGrant->id);
            if ($grant->revoked_at) {
                return;
            }
            $grant->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            $person->forceFill(['record_version' => (int) $person->record_version + 1])->save();
            $this->audit->record('access.revoked', $actor, $grant);
        }, 3);
    }

    public function assign(User $actor, array $data, ?GuardGateAssignment $replace = null): GuardGateAssignment
    {
        return DB::transaction(function () use ($actor, $data, $replace): GuardGateAssignment {
            $actor = $this->guard->actor($actor);
            $user = User::lockForUpdate()->findOrFail($data['guard_id']);
            $gate = Gate::lockForUpdate()->findOrFail($data['gate_id']);
            if (! $user->isGuard() || $gate->status->value !== 'active') {
                throw ValidationException::withMessages(['guard_id' => 'Assignments require an active guard and active gate.']);
            }
            $start = $this->time($data['starts_at']);
            $end = empty($data['ends_at']) ? null : $this->time($data['ends_at']);
            if ($replace) {
                $previous = GuardGateAssignment::lockForUpdate()->findOrFail($replace->id);
                abort_unless($previous->guard_id === $user->id && $previous->gate_id === $gate->id, 404);
                if ($previous->revoked_at) {
                    throw ValidationException::withMessages(['record' => 'This assignment was already revoked. Reload the page.']);
                }
                $previous->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            }
            $overlap = GuardGateAssignment::where('guard_id', $user->id)->where('gate_id', $gate->id)->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $start));
            if ($end) {
                $overlap->where('starts_at', '<', $end);
            }
            if ($overlap->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This guard already has an overlapping assignment at this gate.']);
            }
            $assignment = GuardGateAssignment::create(['guard_id' => $user->id, 'gate_id' => $gate->id, 'starts_at' => $start, 'ends_at' => $end, 'assigned_by' => $actor->id]);
            $this->audit->record($replace ? 'assignment.rescheduled' : 'assignment.created', $actor, $assignment);

            return $assignment;
        }, 3);
    }

    public function revokeAssignment(User $actor, GuardGateAssignment $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $actor = $this->guard->actor($actor);
            User::withTrashed()->lockForUpdate()->findOrFail($target->guard_id);
            Gate::withTrashed()->lockForUpdate()->findOrFail($target->gate_id);
            $assignment = GuardGateAssignment::lockForUpdate()->findOrFail($target->id);
            if ($assignment->revoked_at) {
                return;
            }
            $assignment->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            $this->audit->record('assignment.revoked', $actor, $assignment);
        }, 3);
    }
}
