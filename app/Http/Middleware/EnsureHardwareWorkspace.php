<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHardwareWorkspace
{
    public static function available(Request $request): bool
    {
        return app()->environment(['local', 'testing']) && config('nfcheck.hardware_lab')
            && config('database.connections.mysql.database') === 'nfcheck_hardware'
            && in_array($request->ip(), ['127.0.0.1', '::1'], true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (config('database.connections.mysql.database') === 'nfcheck_hardware') {
            abort_unless(self::available($request), 404);
        }

        return $next($request);
    }
}
