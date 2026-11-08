<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class GuardManagement
{
    public function __construct(private ManagementGuard $guard, private AuditLogger $audit, private AccountService $accounts) {}

    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $actor = $this->guard->actor($actor);

            return $this->accounts->create(['name' => $data['name'], 'email' => $data['email'], 'role' => 'guard', 'password' => bin2hex(random_bytes(32)), 'must_change_password' => true], $actor)->fresh();
        }, 3);
    }

    public function setupLink(User $actor, User $target): bool
    {
        return DB::transaction(function () use ($actor, $target): bool {
            $actor = $this->guard->actor($actor);
            $user = User::lockForUpdate()->findOrFail($target->id);
            abort_unless($user->isGuard(), 403);
            try {
                $status = Password::sendResetLink(['email' => $user->email, 'status' => 'active']);
                $this->audit->record('guard.setup_requested', $actor, $user);

                return in_array($status, [Password::ResetLinkSent, Password::ResetThrottled], true);
            } catch (TransportExceptionInterface) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $this->audit->record('guard.setup_delivery_failed', $actor, $user, outcome: 'failure');

                return false;
            }
        }, 3);
    }

    public function update(User $actor, User $target, array $data, ?bool $restore = null): User
    {
        return DB::transaction(function () use ($actor, $target, $data, $restore): User {
            $actor = $this->guard->actor($actor);
            $user = User::withTrashed()->lockForUpdate()->findOrFail($target->id);
            abort_unless($user->role === UserRole::Guard, 403);
            $this->guard->version($user, (int) $data['record_version']);
            if (($restore === true && ! $user->trashed()) || ($restore === false && $user->trashed())) {
                return $user;
            }
            if ($restore === null) {
                $this->guard->editable($user);
                $user->fill(['name' => $data['name'], 'status' => $data['status']]);
            } else {
                $user->status = 'inactive';
            }
            $user->forceFill(['record_version' => (int) $user->record_version + 1])->save();
            if ($restore === true) {
                $user->restore();
            }
            if ($restore === false) {
                $user->guardAssignments()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now()]);
                $user->delete();
            }
            if (! $user->isActive()) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }
            $this->audit->record(match ($restore) {
                true => 'guard.restored', false => 'guard.archived', null => 'guard.updated'
            }, $actor, $user, ['status' => $user->status->value]);

            return $user;
        }, 3);
    }
}
