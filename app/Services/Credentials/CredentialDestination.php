<?php

namespace App\Services\Credentials;

use App\Casts\BinaryDigest;
use App\Models\NfcCredential;

class CredentialDestination
{
    public function __construct(private CredentialToken $tokens) {}

    public function inspect(string $kind, #[\SensitiveParameter] ?string $payload, string $source): array
    {
        if ($kind === 'empty') {
            return ['allowed' => true, 'reason' => 'empty', 'message' => 'Empty coin checked. Keep this coin separate for issuance.', 'credential' => null];
        }
        $blocked = ['allowed' => false, 'reason' => 'unrecognized', 'message' => 'This coin contains unrecognized data. Use a blank coin, or preserve and inspect its old contents first.', 'credential' => null];
        $digest = $kind === 'reference' && $payload ? $this->tokens->digest($payload) : null;
        if (! $digest) {
            return $blocked;
        }
        $credential = NfcCredential::with('assignment.person')->where('token_digest', BinaryDigest::binding($digest))->first();
        if (! $credential || $credential->source !== $source) {
            return [...$blocked, 'reason' => 'unknown_reference', 'message' => 'This NFCheck reference is unknown in this workspace. Its contents will not be overwritten.'];
        }
        $status = $credential->effectiveStatus();
        // Live references must be explicitly retired. Expiry already makes a
        // reference unusable; this read never changes its retained history.
        $allowed = in_array($status, ['revoked', 'cancelled', 'expired'], true);
        $person = $credential->assignment->person;

        return ['allowed' => $allowed, 'reason' => $allowed ? 'retired' : 'registered',
            'message' => $allowed ? 'Retired or expired reference checked. This coin can receive a fresh credential.' : 'This coin already holds a registered credential. Use another coin, or retire this credential as withdrawn before reusing it.',
            'credential' => ['id' => $credential->public_id, 'label' => $credential->label, 'status' => $status,
                'person' => ['name' => $person->full_name, 'number' => $person->campus_number]]];
    }
}
