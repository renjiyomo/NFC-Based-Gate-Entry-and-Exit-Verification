<?php

namespace App\Policies;

use App\Enums\RecordStatus;
use App\Models\Gate;
use App\Models\User;

class GatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Gate $gate): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Gate $gate): bool
    {
        return $user->isAdmin();
    }

    public function operate(User $user, Gate $gate): bool
    {
        return $user->isGuard() && ! $gate->trashed() && $gate->status === RecordStatus::Active
            && ($user->gate_id ? $user->gate_id === $gate->id : $user->guardAssignments()->where('gate_id', $gate->id)->effective()->exists());
    }

    public function archive(User $user, Gate $gate): bool
    {
        return $user->isAdmin();
    }
}
