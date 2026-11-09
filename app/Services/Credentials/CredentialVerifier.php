<?php

namespace App\Services\Credentials;

use App\Models\NfcCredential;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class CredentialVerifier
{
    public function __construct(private CredentialToken $tokens) {}

    public function inspect(#[\SensitiveParameter] string $payload, bool $allowSimulated = false): array
    {
        return DB::transaction(function () use ($payload, $allowSimulated): array {
            $check = $this->lockedPayload($payload, $allowSimulated, false);
            if (! $check->eligible()) {
                return ['eligible' => false, 'reason' => $check->reason];
            }

            return ['eligible' => true, 'reason' => $check->reason, 'source' => $check->credential->source, 'credential' => ['id' => $check->credential->public_id, 'label' => $check->credential->label], 'person' => ['id' => $check->person->id, 'name' => $check->person->full_name, 'number' => $check->person->campus_number, 'kind' => $check->person->person_type->value]];
        }, 3);
    }

    // Transaction lock order: person -> credential -> assignment -> grant.
    public function lockedPayload(#[\SensitiveParameter] string $payload, bool $allowSimulated = false, bool $requireGrant = true): CredentialCheck
    {
        $digest = $this->tokens->digest($payload);
        if (! $digest) {
            return new CredentialCheck('malformed');
        }
        $id = NfcCredential::where('token_digest', $digest)->value('id');

        return $id ? $this->lockedCredential((int) $id, $allowSimulated, $requireGrant) : new CredentialCheck('unknown');
    }

    public function lockedCredential(int $id, bool $allowSimulated = false, bool $requireGrant = true): CredentialCheck
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Credential decisions require a transaction.');
        }
        $candidate = NfcCredential::find($id);
        $owner = $candidate?->assignment()->first();
        $person = $owner ? Person::withTrashed()->lockForUpdate()->find($owner->person_id) : null;
        $credential = $candidate ? NfcCredential::lockForUpdate()->find($id) : null;
        if (! $credential) {
            return new CredentialCheck('unknown');
        }
        $assignment = $credential->assignment()->lockForUpdate()->first();
        $denied = fn (string $reason) => new CredentialCheck($reason, $credential, $person, $assignment);
        if ($credential->source === 'simulated' && ! $allowSimulated) {
            return $denied('simulated_not_permitted');
        }
        if ($credential->effectiveStatus() !== 'active') {
            return $denied($credential->effectiveStatus());
        }
        if (! $person || $person->trashed() || $person->status->value !== 'active') {
            return $denied('person_inactive');
        }
        if (! $assignment || $assignment->ended_at || $person->active_credential_assignment_id !== $assignment->id) {
            return $denied('not_selected');
        }
        if (! $requireGrant) {
            return new CredentialCheck('eligible_for_review', $credential, $person, $assignment);
        }
        $grant = $person->authorizations()->effective()->lockForUpdate()->first();

        return $grant ? new CredentialCheck('eligible_for_review', $credential, $person, $assignment, $grant) : $denied('access_not_authorized');
    }
}
