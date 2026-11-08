<?php

namespace App\Console\Commands;

use App\Models\NfcCredential;
use App\Services\Credentials\CredentialLifecycle;
use Illuminate\Console\Command;

class ExpireCredentials extends Command
{
    protected $signature = 'nfcheck:expire-credentials';

    protected $description = 'Close expired pending/active credentials while retaining ownership and audit history';

    public function handle(CredentialLifecycle $lifecycle): int
    {
        $closed = 0;
        NfcCredential::whereIn('status', ['pending', 'active'])->orderBy('id')->chunkById(100, function ($credentials) use ($lifecycle, &$closed): void {
            foreach ($credentials as $credential) {
                if ($credential->effectiveStatus() === 'expired' && $lifecycle->expire($credential)) {
                    $closed++;
                }
            }
        });
        $this->info('Closed '.$closed.' expired credentials. Verification rejects expired records even before this cleanup runs.');

        return self::SUCCESS;
    }
}
