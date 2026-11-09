<?php

namespace App\Services;

use App\Models\Gate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GateAccounts
{
    public function save(User $actor, Gate $gate, array $data): User
    {
        return DB::transaction(function () use ($actor, $gate, $data): User {
            $admin = app(ManagementGuard::class)->actor($actor);
            $gate = Gate::lockForUpdate()->findOrFail($gate->id);
            $account = User::withTrashed()->where('gate_id', $gate->id)->lockForUpdate()->first();
            if (! $account && empty($data['password'])) {
                throw ValidationException::withMessages(['password' => 'Set the gate account password.']);
            }
            if ($account?->trashed()) {
                throw ValidationException::withMessages(['gate_id' => 'Restore this gate account before editing it.']);
            }
            $account ??= new User(['email' => 'gate-'.Str::uuid().'@accounts.nfcheck.invalid', 'role' => 'guard', 'gate_id' => $gate->id]);
            $account->fill(['name' => $gate->name, 'username' => strtolower($data['username']), 'status' => $gate->status->value, 'must_change_password' => false]);
            if (! empty($data['password'])) {
                $account->password = $data['password'];
            }
            $account->save();
            app(AuditLogger::class)->record('gate.account_saved', $admin, $account, ['status' => $account->status->value]);

            return $account;
        }, 3);
    }
}
