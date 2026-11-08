<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Rules\NotCommonPassword;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(array $attributes, ?User $actor = null): User
    {
        $this->authorizeActor($actor);
        $attributes['email'] = mb_strtolower(trim($attributes['email'] ?? ''));
        $validated = Validator::make($attributes, ['name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'max:128', Password::min(15), new NotCommonPassword],
            'role' => ['required', Rule::enum(UserRole::class)], 'must_change_password' => ['sometimes', 'boolean']])->validate();

        return DB::transaction(function () use ($validated, $actor): User {
            $user = User::create($validated);
            $this->audit->record('account.created', $actor, $user, ['role' => $user->role->value]);

            return $user;
        }, 3);
    }

    public function changeAccess(User $target, UserRole $role, RecordStatus $status, ?User $actor = null): User
    {
        $this->authorizeActor($actor);

        return DB::transaction(function () use ($target, $role, $status, $actor): User {
            $admins = User::where('role', UserRole::Admin)->where('status', RecordStatus::Active)->orderBy('id')->lockForUpdate()->get();
            $user = User::lockForUpdate()->findOrFail($target->id);
            if ($user->isAdmin() && ($role !== UserRole::Admin || $status !== RecordStatus::Active) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'The last active administrator must remain available.']);
            }
            $user->fill(['role' => $role, 'status' => $status])->save();
            $this->audit->record('account.access_changed', $actor, $user, ['role' => $role->value, 'status' => $status->value]);

            return $user;
        }, 3);
    }

    private function authorizeActor(?User $actor): void
    {
        if (($actor && ! $actor->isAdmin()) || (! $actor && ! app()->runningInConsole())) {
            throw new AuthorizationException('Administrator authorization required.');
        }
    }
}
