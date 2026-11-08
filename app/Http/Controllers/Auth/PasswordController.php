<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\PasswordChangeRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController
{
    public function edit(): Response
    {
        return Inertia::render('auth/ChangePassword');
    }

    public function update(PasswordChangeRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $user = DB::transaction(function () use ($request, $validated, $audit): User {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->isActive() && $request->session()->get('auth_version') === $user->auth_version, 403);
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password could not be verified.'])->redirectTo(route('password.edit'));
            }
            $user->forceFill(['password' => $validated['password'], 'must_change_password' => false])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $audit->record('password.changed', $user, $user);

            return $user;
        }, 3);
        $request->session()->regenerate(true);
        Auth::setUser($user);
        $request->session()->put('auth_version', $user->auth_version);
        $request->session()->put('password_hash_web', Auth::guard()->hashPasswordForCookie($user->password));

        return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'guard.scanner')->with('status', 'Password updated. Other sessions were revoked.');
    }
}
