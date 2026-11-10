<?php

namespace App\Console\Commands;

use App\Services\GateOperations\MissingExitSweep;
use Illuminate\Console\Command;

class DetectMissingExits extends Command
{
    protected $signature = 'nfcheck:missing-exits';

    protected $description = 'Append a bounded page of overdue Missing Exit flags in the current workspace';

    public function handle(MissingExitSweep $sweep): int
    {
        $this->line(json_encode($sweep->run(), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
