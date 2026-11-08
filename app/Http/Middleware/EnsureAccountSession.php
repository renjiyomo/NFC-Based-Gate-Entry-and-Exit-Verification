<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->isActive() || $request->session()->get('auth_version') !== $user->auth_version)) {
            app(AuditLogger::class)->record('session.revoked', $user, $user, ['reason' => 'account_state_changed']);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Inertia::clearHistory();

            return redirect()->route('login')->with('status', 'Your session ended. Sign in again.');
        }

        return $next($request);
    }
}
