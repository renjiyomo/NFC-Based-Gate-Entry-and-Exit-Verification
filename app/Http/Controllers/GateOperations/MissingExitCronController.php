<?php

namespace App\Http\Controllers\GateOperations;

use App\Services\GateOperations\MissingExitSweep;
use Illuminate\Http\Request;

final class MissingExitCronController
{
    public function __invoke(Request $request, MissingExitSweep $sweep)
    {
        $secret = (string) config('nfcheck.cron_secret');
        abort_unless(strlen($secret) >= 32, 503);
        abort_unless(hash_equals($secret, $request->bearerToken() ?? ''), 403);

        return response()->json($sweep->run())->header('Cache-Control', 'private, no-store');
    }
}
