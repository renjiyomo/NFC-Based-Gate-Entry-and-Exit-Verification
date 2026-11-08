<?php

namespace App\Policies;

use App\Models\NfcCredential;
use App\Models\User;

class NfcCredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, NfcCredential $credential): bool
    {
        return $user->isAdmin();
    }

    public function manage(User $user, NfcCredential $credential): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, NfcCredential $credential): bool
    {
        return false;
    }
}
