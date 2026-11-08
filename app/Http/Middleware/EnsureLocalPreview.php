<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            app()->environment(['local', 'testing'])
                && config('nfcheck.ui_preview')
                && in_array($request->ip(), ['127.0.0.1', '::1'], true),
            404,
        );

        return $next($request);
    }
}
