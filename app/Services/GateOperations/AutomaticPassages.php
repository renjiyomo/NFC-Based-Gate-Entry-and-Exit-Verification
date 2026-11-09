<?php

namespace App\Services\GateOperations;

use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Credentials\CredentialVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutomaticPassages
{
    public function scope(User $actor, Gate $gate, string $source): User
    {
        $user = User::withTrashed()->lockForUpdate()->findOrFail($actor->id);
        $station = Gate::withTrashed()->sharedLock()->findOrFail($gate->id);
        abort_unless($user->isGuard() && ! $user->must_change_password && $user->gate_id === $station->id && $user->auth_version === $actor->auth_version && ! $station->trashed() && $station->status->value === 'active', 403);
        if ($source === 'simulated') {
            abort_unless(app()->environment(['local', 'testing']) && config('nfcheck.nfc_simulator') && in_array(config('database.connections.mysql.database'), ['nfcheck_testing', 'nfcheck_simulator'], true), 404);
        } else {
            abort_unless($source === 'physical' && config('database.connections.mysql.database') !== 'nfcheck_simulator', 403);
        }

        return $user;
    }

    public function scan(User $actor, Gate $gate, array $data, string $session, string $source): array
    {
        return DB::transaction(function () use ($actor, $gate, $data, $session, $source): array {
            $user = $this->scope($actor, $gate, $source);
            $fingerprint = hash_hmac('sha256', $data['direction']."\0".$data['payload'], (string) config('app.key'), true);
            $existing = ScanAttempt::where('guard_id', $user->id)->where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($existing) {
                $this->owner($existing, $user, $gate, $session, $source);
                if (! hash_equals($existing->payload_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['request_id' => 'This request already belongs to a different scan.']);
                }

                return $this->result($existing);
            }
            $check = app(CredentialVerifier::class)->lockedPayload($data['payload'], $source === 'simulated', false);
            $allowed = $check->eligible() && $check->credential->source === $source;
            $reason = $allowed ? 'credential_valid' : ($check->eligible() ? 'source_mismatch' : $check->reason);
            $window = config('nfcheck.duplicate_seconds');
            if (! is_int($window) || $window < 1 || $window > 120) {
                throw new \LogicException('Invalid duplicate policy.');
            }
            $at = now();
            $duplicate = $allowed ? AccessEvent::effective()->where('person_id', $check->person->id)->where('gate_id', $gate->id)->where('direction', $data['direction'])->where('source', $source)->where('confirmed_at', '>', $at->copy()->subSeconds($window)->format('Y-m-d H:i:s.u'))->where('confirmed_at', '<=', $at->format('Y-m-d H:i:s.u'))->latest('confirmed_at')->latest('id')->sharedLock()->first() : null;
            $attempt = ScanAttempt::create(['public_id' => (string) Str::uuid(), 'request_id' => $data['request_id'], 'guard_id' => $user->id, 'guard_auth_version' => $user->auth_version, 'session_digest' => hash_hmac('sha256', $session, (string) config('app.key'), true), 'gate_id' => $gate->id, 'gate_assignment_id' => null, 'payload_fingerprint' => $fingerprint,
                'direction' => $data['direction'], 'source' => $source, 'person_id' => $check->person?->id, 'person_version' => $check->person?->record_version, 'credential_id' => $check->credential?->id, 'credential_assignment_id' => $check->assignment?->id, 'authorization_id' => null, 'outcome' => $allowed ? 'allowed' : 'denied', 'reason' => $reason, 'policy_version' => 'gate-auto-v2', 'duplicate_window_seconds' => $window, 'received_at' => $at, 'review_expires_at' => null,
                'review_status' => $allowed ? ($duplicate ? 'cancelled' : 'confirmed') : 'not_applicable', 'reviewed_at' => $allowed ? $at : null, 'reviewed_by' => $allowed ? $user->id : null, 'review_reason' => $allowed ? ($duplicate ? 'duplicate_passage' : 'automatic_nfc_scan') : null, 'duplicate_of_event_id' => $duplicate?->id]);
            app(AuditLogger::class)->record('verification.received', $user, $attempt, ['reason' => $reason, 'direction' => $data['direction'], 'source' => $source], $allowed ? 'success' : 'denied');
            if ($allowed && ! $duplicate) {
                $last = AccessEvent::effective()->where('person_id', $check->person->id)->where('source', $source)->latest('confirmed_at')->latest('id')->sharedLock()->first();
                $notice = $last ? ($last->direction === $data['direction'] ? 'repeated_'.$data['direction'] : null) : ($data['direction'] === 'exit' ? 'exit_without_recorded_entry' : null);
                $event = AccessEvent::create(['public_id' => (string) Str::uuid(), 'scan_attempt_id' => $attempt->id, 'person_id' => $check->person->id, 'credential_assignment_id' => $check->assignment->id, 'guard_id' => $user->id, 'gate_id' => $gate->id, 'direction' => $data['direction'], 'source' => $source, 'policy_version' => 'gate-auto-v2', 'identity_method' => 'nfc_scan', 'sequence_notice' => $notice, 'confirmed_at' => $at]);
                app(AuditLogger::class)->record('passage.recorded', $user, $event, ['direction' => $data['direction'], 'source' => $source, 'reason' => 'automatic_nfc_scan']);
            }

            return $this->result($attempt);
        }, 3);
    }

    public function recover(User $actor, Gate $gate, string $requestId, string $session, string $source): array
    {
        return DB::transaction(function () use ($actor, $gate, $requestId, $session, $source): array {
            $user = $this->scope($actor, $gate, $source);
            $attempt = ScanAttempt::where('guard_id', $user->id)->where('request_id', $requestId)->sharedLock()->firstOrFail();
            $this->owner($attempt, $user, $gate, $session, $source);

            return $this->result($attempt);
        }, 3);
    }

    private function owner(ScanAttempt $a, User $user, Gate $gate, string $session, string $source): void
    {
        abort_unless($a->policy_version === 'gate-auto-v2' && $a->guard_id === $user->id && $a->gate_id === $gate->id && $a->source === $source && $a->guard_auth_version === $user->auth_version && hash_equals($a->session_digest, hash_hmac('sha256', $session, (string) config('app.key'), true)), 404);
    }

    public function photo(User $actor, Gate $gate, ScanAttempt $attempt, string $session, string $source): string
    {
        return DB::transaction(function () use ($actor, $gate, $attempt, $session, $source): string {
            $user = $this->scope($actor, $gate, $source);
            $this->owner($attempt, $user, $gate, $session, $source);
            abort_unless($attempt->outcome === 'allowed' && $attempt->received_at->greaterThan(now()->subMinutes(config('nfcheck.guard_activity_minutes'))), 404);
            $path = $attempt->person?->photo_path;
            abort_unless($path && preg_match('#^person-photos/[a-f0-9-]{36}\.jpg$#D', $path) && Storage::disk('local')->exists($path), 404);

            return Storage::disk('local')->path($path);
        }, 3);
    }

    private function result(ScanAttempt $a): array
    {
        $event = $a->passage()->first();
        $duplicate = $a->duplicate()->first();
        $person = $a->outcome === 'allowed' && $a->received_at->greaterThan(now()->subMinutes(config('nfcheck.guard_activity_minutes'))) ? $a->person : null;
        $photoBase = $a->source === 'simulated' ? '/simulator/guard/gates' : '/guard/gates';

        return ['id' => $a->public_id, 'request_id' => $a->request_id, 'gate_id' => $a->gate_id, 'direction' => $a->direction, 'source' => $a->source, 'server_time' => now()->toISOString(), 'verification' => ['outcome' => $a->outcome, 'reason' => $a->reason, 'received_at' => $a->received_at->toISOString()],
            'review' => ['status' => $a->review_status, 'expires_at' => null, 'reason' => $a->review_reason, 'reviewed_at' => $a->reviewed_at?->toISOString()], 'current_check' => ['eligible' => $a->outcome === 'allowed', 'reason' => $a->reason], 'identity' => $person ? ['name' => $person->full_name, 'number' => $person->campus_number, 'kind' => $person->person_type->value, 'photo_url' => $person->photo_path ? $photoBase.'/'.$a->gate_id.'/attempts/'.$a->public_id.'/photo' : null, 'sequence_notice' => $event?->sequence_notice] : null,
            'passage' => $event ? ['id' => $event->public_id, 'direction' => $event->direction, 'confirmed_at' => $event->confirmed_at->toISOString(), 'source' => $event->source, 'identity_method' => 'nfc_scan', 'sequence_notice' => $event->sequence_notice, 'voided' => $event->latestDecision?->action === 'void'] : null, 'duplicate_of' => $duplicate ? ['id' => $duplicate->public_id, 'direction' => $duplicate->direction, 'confirmed_at' => $duplicate->confirmed_at->toISOString()] : null];
    }
}
