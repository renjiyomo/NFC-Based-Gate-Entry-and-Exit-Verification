<?php

use App\Http\Middleware\EnsureAccountSession;
use App\Http\Middleware\EnsureHardwareWorkspace;
use App\Http\Middleware\EnsureLocalPreview;
use App\Http\Middleware\EnsureNfcSimulator;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (filter_var(env('NFCHECK_CLOUD', false), FILTER_VALIDATE_BOOL)) {
            $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('NFCHECK_TRUSTED_PROXIES', '')))));
            $middleware->trustProxies(at: $proxies, headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
            $host = parse_url((string) env('APP_URL'), PHP_URL_HOST);
            $middleware->trustHosts(at: $host ? ['^'.preg_quote($host, '/').'$'] : [], subdomains: false);
        }
        $middleware->prepend(SecurityHeaders::class);
        $middleware->prependToPriorityList(StartSession::class, EnsureLocalPreview::class);
        $middleware->prependToPriorityList(StartSession::class, EnsureNfcSimulator::class);
        $middleware->prependToPriorityList(StartSession::class, EnsureHardwareWorkspace::class);
        $middleware->web(prepend: [EnsureHardwareWorkspace::class], append: [EnsureAccountSession::class, HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()->isAdmin() ? '/admin/dashboard' : '/guard/scanner');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'payload', 'completion_nonce', 'q', 'reason', 'api_key', 'prompt']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
