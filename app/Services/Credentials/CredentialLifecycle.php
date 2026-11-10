<?php

namespace App\Services\Credentials;

use App\Models\CredentialAssignment;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ManagementGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CredentialLifecycle
{
    public function __construct(private CredentialToken $tokens, private ManagementGuard $guard, private AuditLogger $audit) {}

    public function issue(User $actor, array $data, string $sessionId, string $source): array
    {
        return DB::transaction(function () use ($actor, $data, $sessionId, $source): array {
            $actor = $this->guard->actor($actor);
            $person = Person::lockForUpdate()->findOrFail($data['person_id']);
            $this->guard->version($person, (int) $data['record_version']);
            $this->activePerson($person);
            if (NfcCredential::where('created_by', $actor->id)->where('issuance_request_id', $data['request_id'])->exists()) {
                $this->fail('request_id', 'This issuance request was already used. Open the credential registry; a secret cannot be delivered again.');
            }
            $until = empty($data['valid_until']) ? null : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['valid_until'], 'Asia/Manila')->utc();
            if ($until && $until->lessThanOrEqualTo(now())) {
                $this->fail('valid_until', 'Choose a credential expiry in the future.');
            }
            $pending = NfcCredential::whereHas('assignment', fn ($q) => $q->where('person_id', $person->id))->where('status', 'pending')->lockForUpdate()->get();
            foreach ($pending as $credential) {
                if ($credential->pending_expires_at->lessThanOrEqualTo(now())) {
                    $this->close($person, $credential, 'expired', 'pending_timeout', $actor);
                } else {
                    $this->fail('person_id', 'This person already has a pending issuance. Cancel it or wait for its expiry.');
                }
            }
            $old = null;
            if ($person->active_credential_assignment_id) {
                $active = CredentialAssignment::findOrFail($person->active_credential_assignment_id);
                $old = NfcCredential::lockForUpdate()->findOrFail($active->credential_id);
                if ($old->effectiveStatus() === 'expired') {
                    $this->close($person, $old, 'expired', 'validity_ended', $actor);
                    $old = null;
                }
            }
            $mode = $data['mode'];
            $replaces = null;
            if ($mode === 'new' && $old) {
                $this->fail('mode', 'A current credential already exists. Use replacement.');
            }
            if ($mode === 'replace') {
                if (! $old || $old->public_id !== ($data['replaces_id'] ?? null) || $old->source !== $source) {
                    $this->fail('mode', 'The selected current credential changed. Reload this person before replacement.');
                }
                $replaces = $old;
            }
            if ($mode === 'lost') {
                $replaces = NfcCredential::where('public_id', $data['replaces_id'] ?? '')->whereHas('assignment', fn ($q) => $q->where('person_id', $person->id))->lockForUpdate()->first();
                if ($old || ! $replaces || $replaces->status !== 'revoked' || $replaces->close_reason !== 'lost' || $replaces->source !== $source) {
                    $this->fail('mode', 'Report the old credential lost first. Its revocation must finish before a lost-tag replacement starts.');
                }
            }
            $payload = $this->tokens->generate();
            $nonce = bin2hex(random_bytes(32));
            $person->forceFill(['record_version' => (int) $person->record_version + 1])->save();
            $credential = NfcCredential::create(['public_id' => (string) Str::uuid(), 'label' => app(CredentialLabels::class)->next($person->person_type->value), 'token_digest' => $this->tokens->digest($payload), 'status' => 'pending', 'source' => $source,
                'created_by' => $actor->id, 'issuer_auth_version' => $actor->auth_version, 'issuance_request_id' => $data['request_id'], 'completion_digest' => hash('sha256', $nonce, true), 'issuer_session_digest' => $this->sessionDigest($sessionId),
                'person_version' => $person->record_version, 'issuance_mode' => $mode, 'replaces_credential_id' => $replaces?->id, 'pending_expires_at' => now()->addMinutes(10), 'valid_until' => $until]);
            CredentialAssignment::create(['person_id' => $person->id, 'credential_id' => $credential->id, 'assigned_by' => $actor->id, 'assigned_at' => now()]);
            $this->audit->record('credential.issued_pending', $actor, $credential, ['source' => $source]);

            return ['credential' => CredentialData::one($credential->load('assignment.person')), 'payload' => $payload, 'completion_nonce' => $nonce];
        }, 3);
    }

    public function readBack(User $actor, NfcCredential $target, string $sessionId, #[\SensitiveParameter] string $payload, #[\SensitiveParameter] string $nonce, string $source): NfcCredential
    {
        return $this->complete($actor, $target, $sessionId, $payload, $nonce, $source, false);
    }

    public function activate(User $actor, NfcCredential $target, string $sessionId, #[\SensitiveParameter] string $payload, #[\SensitiveParameter] string $nonce, string $source): NfcCredential
    {
        return $this->complete($actor, $target, $sessionId, $payload, $nonce, $source, true);
    }

    private function complete(User $actor, NfcCredential $target, string $sessionId, #[\SensitiveParameter] string $payload, #[\SensitiveParameter] string $nonce, string $source, bool $activate): NfcCredential
    {
        return DB::transaction(function () use ($actor, $target, $sessionId, $payload, $nonce, $source, $activate): NfcCredential {
            $actor = $this->guard->actor($actor);
            $assignment = $target->assignment()->firstOrFail();
            $person = Person::lockForUpdate()->findOrFail($assignment->person_id);
            $credential = NfcCredential::lockForUpdate()->findOrFail($target->id);
            $this->activePerson($person);
            $digest = $this->tokens->digest($payload);
            if ($credential->source !== $source || $credential->created_by !== $actor->id || $credential->issuer_auth_version !== $actor->auth_version || ! hash_equals($credential->issuer_session_digest, $this->sessionDigest($sessionId)) || ! hash_equals($credential->completion_digest, hash('sha256', $nonce, true))) {
                $this->fail('issuance', 'This issuance belongs to another or ended session. Cancel it and start again.');
            }
            if (! $digest || ! hash_equals($credential->token_digest, $digest)) {
                $this->fail('payload', 'Read-back does not match this pending credential. Read the intended tag again.');
            }
            if ($credential->status === 'active' && $person->active_credential_assignment_id === $assignment->id && ! $assignment->ended_at && $credential->effectiveStatus() === 'active') {
                return $credential;
            }
            if ($credential->status !== 'pending' || $credential->pending_expires_at->lessThanOrEqualTo(now())) {
                $this->fail('issuance', 'This pending issuance is closed or expired. Cancel it and issue a new reference.');
            }
            if ((int) $person->record_version !== $credential->person_version) {
                $this->fail('record_version', 'The person record changed. Cancel this issuance and review the latest person details.');
            }
            if ($credential->valid_until?->lessThanOrEqualTo(now())) {
                $this->fail('valid_until', 'The credential validity period has ended. Start a fresh issuance.');
            }
            if (! $activate) {
                if (! $credential->read_back_at) {
                    $credential->read_back_at = now();
                    $credential->save();
                    $this->audit->record('credential.read_back_matched', $actor, $credential, ['source' => $source]);
                }

                return $credential;
            }
            if (! $credential->read_back_at) {
                $this->fail('issuance', 'A matching read-back is required before activation.');
            }
            if ($credential->issuance_mode === 'replace') {
                $oldAssignment = $person->active_credential_assignment_id ? CredentialAssignment::find($person->active_credential_assignment_id) : null;
                if (! $oldAssignment || $oldAssignment->credential_id !== $credential->replaces_credential_id) {
                    $this->fail('issuance', 'The current credential changed. Cancel and review the person record.');
                }
                $old = NfcCredential::lockForUpdate()->findOrFail($oldAssignment->credential_id);
                $this->close($person, $old, 'revoked', 'replaced', $actor);
            } elseif ($person->active_credential_assignment_id) {
                $this->fail('issuance', 'Another credential was activated. Review the person record.');
            }
            $credential->forceFill(['status' => 'active', 'activated_at' => now()])->save();
            $person->forceFill(['active_credential_assignment_id' => $assignment->id, 'record_version' => (int) $person->record_version + 1])->save();
            $this->audit->record('credential.activated', $actor, $credential, ['source' => $source]);

            return $credential;
        }, 3);
    }

    public function terminate(User $actor, NfcCredential $target, string $action, string $reason): NfcCredential
    {
        return DB::transaction(function () use ($actor, $target, $action, $reason): NfcCredential {
            $actor = $this->guard->actor($actor);
            $person = Person::withTrashed()->lockForUpdate()->findOrFail($target->assignment()->firstOrFail()->person_id);
            $credential = NfcCredential::lockForUpdate()->findOrFail($target->id);
            if (in_array($credential->status, ['revoked', 'cancelled', 'expired'], true)) {
                return $credential;
            }
            if ($action === 'cancel' && $credential->status !== 'pending') {
                $this->fail('credential', 'An active credential must be revoked, not cancelled.');
            }
            $this->close($person, $credential, $action === 'cancel' ? 'cancelled' : 'revoked', $reason, $actor);
            $person->forceFill(['record_version' => (int) $person->record_version + 1])->save();

            return $credential;
        }, 3);
    }

    public function archivePerson(Person $person, User $actor): void
    {
        foreach (NfcCredential::whereHas('assignment', fn ($q) => $q->where('person_id', $person->id))->whereIn('status', ['pending', 'active'])->orderBy('id')->lockForUpdate()->get() as $credential) {
            $this->close($person, $credential, $credential->status === 'pending' ? 'cancelled' : 'revoked', 'person_archived', $actor);
        }
    }

    public function expire(NfcCredential $target): bool
    {
        return DB::transaction(function () use ($target): bool {
            $person = Person::withTrashed()->lockForUpdate()->findOrFail($target->assignment()->firstOrFail()->person_id);
            $credential = NfcCredential::lockForUpdate()->findOrFail($target->id);
            if (! in_array($credential->status, ['pending', 'active'], true) || $credential->effectiveStatus() !== 'expired') {
                return false;
            }
            $this->close($person, $credential, 'expired', $credential->status === 'pending' ? 'pending_timeout' : 'validity_ended', null);
            $person->forceFill(['record_version' => (int) $person->record_version + 1])->save();

            return true;
        }, 3);
    }

    private function close(Person $person, NfcCredential $credential, string $status, string $reason, ?User $actor): void
    {
        $assignment = $credential->assignment()->lockForUpdate()->firstOrFail();
        $credential->forceFill(['status' => $status, 'closed_at' => now(), 'closed_by' => $actor?->id, 'close_reason' => $reason])->save();
        if (! $assignment->ended_at) {
            $assignment->forceFill(['ended_at' => now(), 'ended_by' => $actor?->id, 'end_reason' => $reason])->save();
        }
        if ($person->active_credential_assignment_id === $assignment->id) {
            $person->active_credential_assignment_id = null;
            $person->save();
        }
        $this->audit->record('credential.'.$status, $actor, $credential, ['reason' => $reason, 'source' => $credential->source]);
    }

    private function activePerson(Person $person): void
    {
        if ($person->trashed() || $person->status->value !== 'active') {
            $this->fail('person_id', 'Credential issuance requires an active, unarchived person.');
        }
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
