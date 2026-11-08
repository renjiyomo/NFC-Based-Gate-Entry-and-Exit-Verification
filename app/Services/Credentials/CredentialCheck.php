<?php

namespace App\Services\Credentials;

use App\Models\AccessAuthorization;
use App\Models\CredentialAssignment;
use App\Models\NfcCredential;
use App\Models\Person;

readonly class CredentialCheck
{
    public function __construct(public string $reason, public ?NfcCredential $credential = null, public ?Person $person = null, public ?CredentialAssignment $assignment = null, public ?AccessAuthorization $grant = null) {}

    public function eligible(): bool
    {
        return $this->reason === 'eligible_for_review';
    }
}
