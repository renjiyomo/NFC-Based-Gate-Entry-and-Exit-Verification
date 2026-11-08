<?php

namespace App\Console\Commands;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Console\Command;

class ChangeAccountAccess extends Command
{
    protected $signature = 'nfcheck:account-access {email} {--role=} {--status=}';

    protected $description = 'Audit a private account role/status change and invalidate its sessions';

    public function handle(AccountService $accounts): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->firstOrFail();
        $role = $this->option('role') ? UserRole::tryFrom($this->option('role')) : $user->role;
        $status = $this->option('status') ? RecordStatus::tryFrom($this->option('status')) : $user->status;
        if (! $role || ! $status) {
            $this->error('Role/status is not valid.');

            return self::FAILURE;
        }
        $accounts->changeAccess($user, $role, $status);
        $this->info('Account access updated and audited. Existing sessions will be rejected.');

        return self::SUCCESS;
    }
}
