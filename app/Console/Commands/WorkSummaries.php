<?php

namespace App\Console\Commands;

use App\Models\SummaryTask;
use App\Services\Summaries\Generation;
use Illuminate\Console\Command;

class WorkSummaries extends Command
{
    protected $signature = 'nfcheck:work-summaries {--limit=1} {--watch}';

    protected $description = 'Process bounded queued summary requests once, outside gate HTTP serving';

    public function handle(Generation $generation): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! $limit || $limit < 1 || $limit > 3) {
            $this->error('Use a limit from 1 to 3.');

            return self::FAILURE;
        }
        do {
            $tasks = SummaryTask::whereNull('claimed_at')->whereDoesntHave('request.result')->orderBy('id')->limit($limit)->get();
            $count = 0;
            foreach ($tasks as $task) {
                try {
                    if ($generation->process($task)) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    $this->error('A summary worker operation failed. Recover interrupted requests from the administrator screen; no automatic retry.');

                    return self::FAILURE;
                }
            }
            if ($count || ! $this->option('watch')) {
                $this->info('Processed '.$count.' summary tasks. No automatic retries or paid fallback.');
            }
            if ($this->option('watch')) {
                sleep(2);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
