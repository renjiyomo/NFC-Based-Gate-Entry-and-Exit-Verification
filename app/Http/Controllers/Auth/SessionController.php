<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController
{
    public function create(): Response
    {
        return Inertia::render('Login', ['preview' => false]);
    }

    public function store(LoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validated();
        $subject = $audit->fingerprint($credentials['email']);
        $ip = $audit->fingerprint((string) $request->ip());
        $key = 'login:'.$subject.':'.$ip;
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts('login-ip:'.$ip, 30)) {
            $audit->record('login.throttled', metadata: ['subject_hash' => $subject, 'ip_hash' => $ip], outcome: 'denied');
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Wait a minute and try again.'])->redirectTo(route('login'));
        }
        try {
            $authenticated = DB::transaction(function () use ($credentials, $audit, $subject, $ip): bool {
                User::where('email', $credentials['email'])->lockForUpdate()->first();
                if (! Auth::attempt([...$credentials, 'status' => 'active'], false)) {
                    $audit->record('login.failed', metadata: ['subject_hash' => $subject, 'ip_hash' => $ip], outcome: 'failure');

                    return false;
                }
                $user = Auth::user();
                $user->forceFill(['last_login_at' => now()])->save();
                $audit->record('login.succeeded', $user, $user);

                return true;
            }, 3);
        } catch (\Throwable $exception) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw $exception;
        }
        if (! $authenticated) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit('login-ip:'.$ip, 60);
            throw ValidationException::withMessages(['email' => 'These credentials could not be verified.'])->redirectTo(route('login'));
        }
        RateLimiter::clear($key);
        $request->session()->regenerate(true);
        $request->session()->put('auth_version', Auth::user()->auth_version);

        return redirect()->route(Auth::user()->must_change_password ? 'password.edit' : (Auth::user()->isAdmin() ? 'admin.dashboard' : 'guard.scanner'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        try {
            $audit->record('logout', $request->user(), $request->user());
        } finally {
            try {
                Auth::logout();
            } finally {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        Inertia::clearHistory();

        return redirect()->route('login');
    }
}
