<?php

namespace App\Services\GateOperations;

use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\Person;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Credentials\CredentialCheck;
use App\Services\Credentials\CredentialVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GateOperations
{
    public function __construct(private CredentialVerifier $verifier, private AuditLogger $audit) {}

    public function scan(User $actor, Gate $gate, array $data, string $session, string $source): array
    {
        $received = now()->toImmutable();

        return DB::transaction(function () use ($actor, $gate, $data, $session, $source, $received): array {
            [$guard, $station, $assignment] = $this->scope($actor, $gate, $source);
            $fingerprint = hash_hmac('sha256', json_encode([$station->id, $data['direction'], $source, $data['payload']], JSON_THROW_ON_ERROR), (string) config('app.key'), true);
            $existing = ScanAttempt::where('guard_id', $guard->id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                if (! hash_equals($existing->payload_fingerprint, $fingerprint)) {
                    $this->fail('request_id', 'This request ID was already used with different scan details. Recover the original request.');
                }

                return $this->resolve($guard, $station, $existing, $session, $source);
            }
            $check = $this->verifier->lockedPayload($data['payload'], $source === 'simulated');
            $reason = $this->reason($check, $source);
            $allowed = $reason === 'eligible_for_review';
            $seconds = $this->setting('review_seconds', 5, 120);
            $attempt = ScanAttempt::create(['public_id' => (string) Str::uuid(), 'request_id' => $data['request_id'], 'guard_id' => $guard->id, 'guard_auth_version' => $guard->auth_version, 'session_digest' => $this->sessionDigest($session), 'gate_id' => $station->id, 'gate_assignment_id' => $assignment->id, 'payload_fingerprint' => $fingerprint,
                'direction' => $data['direction'], 'source' => $source, 'person_id' => $check->person?->id, 'person_version' => $check->person?->record_version, 'credential_id' => $check->credential?->id, 'credential_assignment_id' => $check->assignment?->id, 'authorization_id' => $check->grant?->id,
                'outcome' => $allowed ? 'allowed' : 'denied', 'reason' => $reason, 'policy_version' => 'gate-v1', 'duplicate_window_seconds' => $this->setting('duplicate_seconds', 1, 120), 'received_at' => $received, 'review_expires_at' => $allowed ? $received->addSeconds($seconds) : null, 'review_status' => $allowed ? 'pending' : 'not_applicable']);
            $this->audit->record('verification.received', $guard, $attempt, ['reason' => $reason, 'direction' => $attempt->direction, 'source' => $source], $allowed ? 'success' : 'denied');
            $this->settle($guard, $attempt, $check, $source);

            return $this->data($attempt, $check, $source);
        }, 3);
    }

    public function recover(User $actor, Gate $gate, string $requestId, string $session, string $source): array
    {
        return DB::transaction(function () use ($actor, $gate, $requestId, $session, $source): array {
            [$guard, $station] = $this->scope($actor, $gate, $source);
            $attempt = ScanAttempt::where('guard_id', $guard->id)->where('request_id', $requestId)->firstOrFail();

            return $this->resolve($guard, $station, $attempt, $session, $source);
        }, 3);
    }

    public function review(User $actor, Gate $gate, ScanAttempt $target, array $data, string $session, string $source): array
    {
        return DB::transaction(function () use ($actor, $gate, $target, $data, $session, $source): array {
            [$guard, $station] = $this->scope($actor, $gate, $source);
            $this->owner($guard, $station, $target, $session, $source);
            $check = $target->credential_id ? $this->verifier->lockedCredential($target->credential_id, $source === 'simulated') : new CredentialCheck('unknown');
            $attempt = ScanAttempt::lockForUpdate()->findOrFail($target->id);
            $this->settle($guard, $attempt, $check, $source);
            if ($attempt->review_status !== 'pending') {
                return $this->data($attempt, $check, $source);
            }
            if ($data['action'] !== 'confirm') {
                $this->transition($attempt, $data['action'] === 'identity_mismatch' ? 'identity_mismatch' : 'cancelled', $data['action'] === 'identity_mismatch' ? 'identity_mismatch' : 'operator_cancelled', $guard);

                return $this->data($attempt, $check, $source);
            }
            if (($data['identity_checked'] ?? false) !== true || ! in_array($data['identity_method'] ?? '', ['photo', 'campus_document'], true)) {
                $this->fail('identity_checked', 'Review the person and explicitly confirm the identity check.');
            }
            if ($data['identity_method'] === 'photo' && (! $check->person->photo_path || ! Storage::disk('local')->exists($check->person->photo_path))) {
                $this->fail('identity_method', 'The identity photograph is unavailable. Review a trusted campus identity document or cancel.');
            }
            // The stable person lock serializes different guards and request IDs too.
            $duplicate = AccessEvent::where('person_id', $attempt->person_id)->where('gate_id', $station->id)->where('direction', $attempt->direction)->where('source', $source)->where('confirmed_at', '>', now()->subSeconds($attempt->duplicate_window_seconds)->format('Y-m-d H:i:s.u'))->where('confirmed_at', '<=', now()->format('Y-m-d H:i:s.u'))->latest('confirmed_at')->latest('id')->sharedLock()->first();
            if ($duplicate) {
                $this->transition($attempt, 'cancelled', 'duplicate_passage', $guard, $duplicate);

                return $this->data($attempt, $check, $source);
            }
            $event = AccessEvent::create(['public_id' => (string) Str::uuid(), 'scan_attempt_id' => $attempt->id, 'person_id' => $attempt->person_id, 'credential_assignment_id' => $attempt->credential_assignment_id, 'guard_id' => $guard->id, 'gate_id' => $station->id, 'direction' => $attempt->direction, 'source' => $source, 'policy_version' => $attempt->policy_version, 'identity_method' => $data['identity_method'], 'sequence_notice' => $this->sequence($attempt->person_id, $attempt->direction, $source), 'confirmed_at' => now()]);
            $this->transition($attempt, 'confirmed', 'observed_passage', $guard);
            $this->audit->record('passage.recorded', $guard, $event, ['direction' => $attempt->direction, 'source' => $source]);

            return $this->data($attempt, $check, $source);
        }, 3);
    }

    public function photo(User $actor, Gate $gate, ScanAttempt $target, string $session, string $source): string
    {
        return DB::transaction(function () use ($actor, $gate, $target, $session, $source): string {
            [$guard, $station] = $this->scope($actor, $gate, $source);
            $this->owner($guard, $station, $target, $session, $source);
            $check = $target->credential_id ? $this->verifier->lockedCredential($target->credential_id, $source === 'simulated') : new CredentialCheck('unknown');
            $attempt = ScanAttempt::lockForUpdate()->findOrFail($target->id);
            abort_unless($attempt->review_status === 'pending' && $attempt->effectiveReviewStatus() === 'pending' && $this->reason($check, $source) === 'eligible_for_review' && $check->person->record_version === $attempt->person_version, 404);
            $path = $check->person->photo_path;
            abort_unless($path && preg_match('/^person-photos\/[a-f0-9-]{36}\.jpg$/D', $path) && Storage::disk('local')->exists($path), 404);

            return Storage::disk('local')->path($path);
        }, 3);
    }

    private function scope(User $actor, Gate $gate, string $source): array
    {
        abort_unless(in_array($source, ['physical', 'simulated'], true), 404);
        if ($source === 'simulated') {
            abort_unless(app()->environment(['local', 'testing']) && config('nfcheck.nfc_simulator') && in_array(config('database.connections.mysql.database'), ['nfcheck_testing', 'nfcheck_simulator'], true), 404);
        } else {
            abort_if(config('database.connections.mysql.database') === 'nfcheck_simulator', 403);
        }
        $guard = User::withTrashed()->lockForUpdate()->findOrFail($actor->id);
        abort_unless($guard->isGuard() && ! $guard->must_change_password && $guard->auth_version === $actor->auth_version, 403);
        $station = Gate::withTrashed()->sharedLock()->findOrFail($gate->id);
        abort_unless(! $station->trashed() && $station->status->value === 'active', 403);
        $assignment = GuardGateAssignment::where('guard_id', $guard->id)->where('gate_id', $station->id)->effective()->sharedLock()->first();
        abort_unless($assignment, 403);

        return [$guard, $station, $assignment];
    }

    private function owner(User $guard, Gate $gate, ScanAttempt $attempt, string $session, string $source): void
    {
        abort_unless($attempt->guard_id === $guard->id && $attempt->gate_id === $gate->id && $attempt->source === $source && $attempt->guard_auth_version === $guard->auth_version && hash_equals($attempt->session_digest, $this->sessionDigest($session)), 404);
    }

    private function resolve(User $guard, Gate $gate, ScanAttempt $target, string $session, string $source): array
    {
        $this->owner($guard, $gate, $target, $session, $source);
        $check = $target->credential_id ? $this->verifier->lockedCredential($target->credential_id, $source === 'simulated') : new CredentialCheck($target->reason);
        $attempt = ScanAttempt::lockForUpdate()->findOrFail($target->id);
        $this->settle($guard, $attempt, $check, $source);

        return $this->data($attempt, $check, $source);
    }

    private function settle(User $guard, ScanAttempt $attempt, CredentialCheck $check, string $source): void
    {
        if ($attempt->review_status !== 'pending') {
            return;
        }
        if ($attempt->effectiveReviewStatus() === 'expired') {
            $this->transition($attempt, 'expired', 'review_expired', $guard);
        } elseif ($this->reason($check, $source) !== 'eligible_for_review') {
            $this->transition($attempt, 'cancelled', 'recheck_'.$this->reason($check, $source), $guard);
        } elseif ($check->person->record_version !== $attempt->person_version) {
            $this->transition($attempt, 'cancelled', 'person_record_changed', $guard);
        }
    }

    private function transition(ScanAttempt $attempt, string $status, string $reason, ?User $guard, ?AccessEvent $duplicate = null): void
    {
        $attempt->forceFill(['review_status' => $status, 'review_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $guard?->id, 'duplicate_of_event_id' => $duplicate?->id])->save();
        $this->audit->record('verification.review_'.$status, $guard, $attempt, ['reason' => $reason, 'direction' => $attempt->direction, 'source' => $attempt->source]);
    }

    public function expire(ScanAttempt $target): bool
    {
        return DB::transaction(function () use ($target): bool {
            if ($target->person_id) {
                Person::withTrashed()->lockForUpdate()->findOrFail($target->person_id);
            }
            $attempt = ScanAttempt::lockForUpdate()->findOrFail($target->id);
            if ($attempt->review_status !== 'pending' || $attempt->effectiveReviewStatus() !== 'expired') {
                return false;
            }
            $this->transition($attempt, 'expired', 'review_expired', null);

            return true;
        }, 3);
    }

    private function reason(CredentialCheck $check, string $source): string
    {
        return $check->eligible() && $check->credential->source !== $source ? 'source_mismatch' : $check->reason;
    }

    private function data(ScanAttempt $attempt, CredentialCheck $check, string $source): array
    {
        $reason = $this->reason($check, $source);
        if ($reason === 'eligible_for_review' && $check->person->record_version !== $attempt->person_version) {
            $reason = 'person_record_changed';
        }
        $pending = $attempt->review_status === 'pending' && $attempt->effectiveReviewStatus() === 'pending' && $reason === 'eligible_for_review';
        $base = $source === 'simulated' ? '/simulator/guard/gates' : '/guard/gates';

        return ['id' => $attempt->public_id, 'request_id' => $attempt->request_id, 'gate_id' => $attempt->gate_id, 'direction' => $attempt->direction, 'source' => $attempt->source, 'server_time' => now()->toISOString(),
            'verification' => ['outcome' => $attempt->outcome, 'reason' => $attempt->reason, 'received_at' => $attempt->received_at->toISOString()],
            'current_check' => ['eligible' => $reason === 'eligible_for_review', 'reason' => $reason],
            'review' => ['status' => $attempt->effectiveReviewStatus(), 'expires_at' => $attempt->review_expires_at?->toISOString(), 'reviewed_at' => $attempt->reviewed_at?->toISOString(), 'reason' => $attempt->review_reason],
            'identity' => $pending ? ['name' => $check->person->full_name, 'number' => $check->person->campus_number, 'kind' => $check->person->person_type->value, 'photo_url' => $check->person->photo_path ? $base.'/'.$attempt->gate_id.'/attempts/'.$attempt->public_id.'/photo' : null, 'sequence_notice' => $this->sequence($attempt->person_id, $attempt->direction, $source)] : null,
            'passage' => $this->eventData($attempt->passage()->sharedLock()->first()), 'duplicate_of' => $this->eventData($attempt->duplicate()->sharedLock()->first())];
    }

    private function eventData(?AccessEvent $event): ?array
    {
        return $event ? ['id' => $event->public_id, 'direction' => $event->direction, 'confirmed_at' => $event->confirmed_at->toISOString(), 'source' => $event->source, 'identity_method' => $event->identity_method, 'sequence_notice' => $event->sequence_notice] : null;
    }

    private function sequence(int $personId, string $direction, string $source): ?string
    {
        $last = AccessEvent::where('person_id', $personId)->where('source', $source)->latest('confirmed_at')->latest('id')->sharedLock()->first();

        return $last ? ($last->direction === $direction ? 'repeated_'.$direction : null) : ($direction === 'exit' ? 'exit_without_recorded_entry' : null);
    }

    private function setting(string $name, int $min, int $max): int
    {
        $value = config('nfcheck.'.$name);
        if (! is_int($value) || $value < $min || $value > $max) {
            throw new \LogicException('Invalid gate policy configuration.');
        }

        return $value;
    }

    private function sessionDigest(string $id): string
    {
        return hash_hmac('sha256', $id, (string) config('app.key'), true);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
