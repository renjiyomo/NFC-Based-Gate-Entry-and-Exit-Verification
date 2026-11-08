<?php

use App\Http\Middleware\EnsureAccountSession;
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
        $middleware->prepend(SecurityHeaders::class);
        $middleware->prependToPriorityList(StartSession::class, EnsureLocalPreview::class);
        $middleware->prependToPriorityList(StartSession::class, EnsureNfcSimulator::class);
        $middleware->web(append: [EnsureAccountSession::class, HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()->isAdmin() ? '/admin/dashboard' : '/guard/scanner');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'payload', 'completion_nonce']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
