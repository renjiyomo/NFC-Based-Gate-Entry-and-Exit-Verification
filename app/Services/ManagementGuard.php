<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManagementGuard
{
    public function actor(User $actor): User
    {
        $fresh = User::lockForUpdate()->findOrFail($actor->id);
        Gate::forUser($fresh)->authorize('admin.workspace');

        return $fresh;
    }

    public function version(Model $record, int $version): void
    {
        if ((int) $record->record_version !== $version) {
            throw ValidationException::withMessages(['record_version' => 'This record changed while you were editing. Reload it before saving again.']);
        }
    }

    public function editable(Model $record): void
    {
        if ($record->trashed()) {
            throw ValidationException::withMessages(['record' => 'Restore this archived record before making changes.']);
        }
    }
}
