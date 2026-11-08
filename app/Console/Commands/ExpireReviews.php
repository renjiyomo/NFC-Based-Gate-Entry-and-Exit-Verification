<?php

namespace App\Console\Commands;

use App\Models\ScanAttempt;
use App\Services\GateOperations\GateOperations;
use Illuminate\Console\Command;

class ExpireReviews extends Command
{
    protected $signature = 'nfcheck:expire-reviews';

    protected $description = 'Close expired reviews; expiry is already enforced during every operation';

    public function handle(GateOperations $operations): int
    {
        $closed = 0;
        ScanAttempt::where('review_status', 'pending')->where('review_expires_at', '<=', now())->chunkById(100, function ($attempts) use ($operations, &$closed): void {
            foreach ($attempts as $attempt) {
                if ($operations->expire($attempt)) {
                    $closed++;
                }
            }
        });
        $this->info('Expired '.$closed.' reviews; no passage was created.');

        return self::SUCCESS;
    }
}
