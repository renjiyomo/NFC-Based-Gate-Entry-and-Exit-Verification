<?php

namespace App\Console\Commands;

use App\Services\AccountService;
use Illuminate\Console\Command;

class ProvisionUser extends Command
{
    protected $signature = 'nfcheck:provision-user {email} {--name=} {--role=admin} {--generate : Generate a local initial credential into the protected handoff file}';

    protected $description = 'Privately provision an operator account; no public registration exists';

    public function handle(AccountService $accounts): int
    {
        if ($this->option('role') !== 'admin') {
            $this->error('Create gate accounts together with their gates in Gate stations.');

            return self::FAILURE;
        }
        if ($this->option('generate') && ! app()->environment(['local', 'testing'])) {
            $this->error('Generated-file handoff is local-only.');

            return self::FAILURE;
        }
        $password = $this->option('generate') ? rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') : $this->secret('Initial password (at least 15 characters)');
        $email = mb_strtolower(trim($this->argument('email')));
        $name = $this->option('name') ?: ucfirst(explode('@', $email)[0]);
        $user = $accounts->create(['email' => $email, 'name' => $name, 'password' => $password, 'role' => $this->option('role'), 'must_change_password' => true]);
        if ($this->option('generate')) {
            $path = base_path('.tools/secrets/initial-accounts.json');
            if (! is_dir(dirname($path))) {
                throw new \RuntimeException('Protected handoff directory is required. Start the local services setup first.');
            }
            $handoff = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
            $handoff[$email] = ['name' => $name, 'email' => $email, 'role' => $user->role->value, 'password' => $password, 'change_required' => true];
            file_put_contents($path, json_encode($handoff, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
            $this->info('Created '.$email.'. Initial credential saved to the protected local handoff file; it was not printed.');
        } else {
            $this->info('Created '.$email.'. Password change is required at first sign-in.');
        }

        return self::SUCCESS;
    }
}
