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
        $digest = $this->tokens->digest($payload);
        if (! $digest) {
            return $this->denied('malformed');
        }

        return DB::transaction(function () use ($digest, $allowSimulated): array {
            $candidate = NfcCredential::where('token_digest', $digest)->first();
            if (! $candidate) {
                return $this->denied('unknown');
            }
            $owner = $candidate->assignment()->first();
            $person = $owner ? Person::withTrashed()->lockForUpdate()->find($owner->person_id) : null;
            $credential = NfcCredential::lockForUpdate()->find($candidate->id);
            if (! $credential) {
                return $this->denied('unknown');
            }
            if ($credential->source === 'simulated' && ! $allowSimulated) {
                return $this->denied('simulated_not_permitted');
            }
            $status = $credential->effectiveStatus();
            if ($status !== 'active') {
                return $this->denied($status);
            }
            $assignment = $credential->assignment()->lockForUpdate()->first();
            if (! $person || $person->trashed() || $person->status->value !== 'active') {
                return $this->denied('person_inactive');
            }
            if ($assignment->ended_at || $person->active_credential_assignment_id !== $assignment->id) {
                return $this->denied('not_selected');
            }
            if (! $person->authorizations()->effective()->lockForUpdate()->first()) {
                return $this->denied('access_not_authorized');
            }

            return ['eligible' => true, 'reason' => 'eligible_for_review', 'source' => $credential->source, 'credential' => ['id' => $credential->public_id, 'label' => $credential->label], 'person' => ['id' => $person->id, 'name' => $person->full_name, 'number' => $person->campus_number, 'kind' => $person->person_type->value]];
        }, 3);
    }

    private function denied(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason];
    }
}
