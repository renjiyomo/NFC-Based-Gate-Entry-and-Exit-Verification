<?php

namespace App\Services\GateOperations;

use App\Support\DatabaseIdentity;
use Illuminate\Support\Facades\Cache;

final class MissingExitSweep
{
    public function run(): array
    {
        $name = DatabaseIdentity::name();
        if (! in_array($name, ['nfcheck', 'nfcheck_testing', 'nfcheck_simulator', 'nfcheck_hardware', 'nfcheck_online'], true)) {
            throw new \LogicException('Missing-exit sweep requires a recognized workspace.');
        }
        $source = $name === 'nfcheck_simulator' ? 'simulated' : 'physical';
        $key = 'missing-exit-cursor:'.$name.':'.hash('sha256', (string) config('app.key'));
        $result = app(MissingExits::class)->sweep($source, (int) Cache::get($key, 0));
        Cache::forever($key, $result['next']);

        return $result;
    }
}
