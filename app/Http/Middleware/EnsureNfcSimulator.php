<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNfcSimulator
{
    public static function available(Request $request): bool
    {
        return app()->environment(['local', 'testing']) && config('nfcheck.nfc_simulator')
            && in_array($request->ip(), ['127.0.0.1', '::1'], true)
            && in_array(config('database.connections.mysql.database'), ['nfcheck_simulator', 'nfcheck_testing'], true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::available($request), 404);

        return $next($request);
    }
}
