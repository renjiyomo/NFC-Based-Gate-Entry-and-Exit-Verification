<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController
{
    public function request(): Response
    {
        abort_unless(config('nfcheck.password_recovery'), 404);

        return Inertia::render('auth/ForgotPassword');
    }

    public function send(ForgotPasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless(config('nfcheck.password_recovery'), 404);
        $validated = $request->validated();
        $email = mb_strtolower(trim($validated['email']));
        try {
            Password::sendResetLink(['email' => $email, 'status' => 'active']);
            $audit->record('password.reset_requested', metadata: ['subject_hash' => $audit->fingerprint($email)]);
        } catch (TransportExceptionInterface) {
            $audit->record('password.delivery_failed', metadata: ['subject_hash' => $audit->fingerprint($email)], outcome: 'failure');
        }

        return redirect()->route('password.request')->with('status', 'If this account is eligible, password reset instructions will arrive shortly.');
    }

    public function form(Request $request, string $token): Response
    {
        abort_unless(config('nfcheck.password_recovery'), 404);

        return Inertia::render('auth/ResetPassword', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(PasswordResetRequest $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless(config('nfcheck.password_recovery'), 404);
        $status = DB::transaction(function () use ($request, $audit): string {
            User::where('email', $request->validated('email'))->lockForUpdate()->first();

            return Password::reset([...$request->validated(), 'status' => 'active'], function (User $user, string $password) use ($audit): void {
                $user->forceFill(['password' => $password, 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $audit->record('password.reset_completed', $user, $user);
                event(new PasswordReset($user));
            });
        }, 3);
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Request a new link.'])->redirectTo($request->resetFormUrl());
        }

        Inertia::clearHistory();

        return redirect()->route('login')->with('status', 'Your password was reset. Sign in with the new password.');
    }
}
