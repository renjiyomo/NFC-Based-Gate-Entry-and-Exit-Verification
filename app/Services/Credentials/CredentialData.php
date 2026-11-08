<?php

namespace App\Services\Credentials;

use App\Models\NfcCredential;

class CredentialData
{
    public static function one(NfcCredential $credential): array
    {
        $assignment = $credential->assignment;
        $person = $assignment->person;

        return ['id' => $credential->public_id, 'label' => $credential->label, 'status' => $credential->effectiveStatus(), 'source' => $credential->source,
            'person' => ['id' => $person->id, 'name' => $person->full_name, 'number' => $person->campus_number, 'kind' => $person->person_type->value, 'status' => $person->status->value, 'archived' => $person->trashed(), 'record_version' => (int) $person->record_version],
            'pending_expires_at' => $credential->pending_expires_at->toIso8601String(), 'read_back_at' => $credential->read_back_at?->toIso8601String(), 'activated_at' => $credential->activated_at?->toIso8601String(), 'valid_until' => $credential->valid_until?->toIso8601String(),
            'closed_at' => $credential->closed_at?->toIso8601String(), 'close_reason' => $credential->close_reason, 'assignment_ended_at' => $assignment->ended_at?->toIso8601String(), 'selected' => $person->active_credential_assignment_id === $assignment->id,
            'replaces_id' => $credential->replaces?->public_id, 'created_at' => $credential->created_at->toIso8601String()];
    }
}
